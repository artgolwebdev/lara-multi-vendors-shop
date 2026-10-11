<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\Discounts\Money;
use App\Services\Orders\Enums\NotificationStatus;
use App\Services\Orders\Enums\OrderStatus;
use App\Services\Orders\Enums\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            //return;
        }

        $customer = User::query()->where('email', 'casey@example.com')->first();

        $offers = ProductOffer::query()
            ->with(['product.category', 'vendor'])
            ->inRandomOrder()
            ->take(6)
            ->get();

        if ($offers->count() < 4) {
            return;
        }

        $this->createOrder($customer, $offers->take(4), OrderStatus::Confirmed, NotificationStatus::Sent);

        $this->createOrder($customer, $offers->skip(4)->take(2)->values(), OrderStatus::Pending, NotificationStatus::Failed);
    }

    /**
     * @param  Collection<int, ProductOffer>  $offers
     */
    private function createOrder(?User $customer, Collection $offers, OrderStatus $status, NotificationStatus $notificationStatus): void
    {
        $order = Order::factory()->make([
            'status' => $status,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'subtotal' => '0.00',
            'total' => '0.00',
        ]);

        if ($customer !== null) {
            $order->user_id = $customer->id;
            $order->guest_token = null;
            $order->customer_name = $customer->name;
            $order->customer_email = $customer->email;
            $order->customer_phone = $customer->phone;
            $order->customer_address = $customer->address;
            $order->delivery_name = $customer->name;
            $order->delivery_email = $customer->email;
            $order->delivery_phone = $customer->phone;
            $order->delivery_address = $customer->address ?? $order->customer_address;
        }

        $order->save();

        $subtotalCents = 0;

        foreach ($offers->groupBy('vendor_id') as $vendorOffers) {
            /** @var ProductOffer $firstOffer */
            $firstOffer = $vendorOffers->first();
            $vendor = $firstOffer->vendor;

            $subOrder = $order->subOrders()->create([
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'vendor_email' => $vendor->email,
                'vendor_phone' => $vendor->phone,
                'status' => $status,
                'subtotal' => '0.00',
                'discount_total' => '0.00',
                'total' => '0.00',
                'notification_status' => $notificationStatus,
                'notified_at' => $notificationStatus === NotificationStatus::Sent ? now() : null,
            ]);

            $subOrderCents = 0;

            foreach ($vendorOffers as $offer) {
                $quantity = 1;
                $unitCents = Money::decimalToCents($offer->price);
                $lineCents = $unitCents * $quantity;
                $subOrderCents += $lineCents;

                $subOrder->items()->create([
                    'order_id' => $order->id,
                    'product_id' => $offer->product_id,
                    'vendor_id' => $vendor->id,
                    'product_name' => $offer->product->name,
                    'product_sku' => $offer->product->sku,
                    'product_category' => $offer->product->category?->name,
                    'unit_price' => Money::centsToDecimal($unitCents),
                    'quantity' => $quantity,
                    'discount_amount' => '0.00',
                    'line_total' => Money::centsToDecimal($lineCents),
                ]);
            }

            $subOrder->update([
                'subtotal' => Money::centsToDecimal($subOrderCents),
                'total' => Money::centsToDecimal($subOrderCents),
            ]);

            $subtotalCents += $subOrderCents;
        }

        $order->update([
            'subtotal' => Money::centsToDecimal($subtotalCents),
            'total' => Money::centsToDecimal($subtotalCents),
        ]);
    }
}
