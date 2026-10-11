<?php

namespace App\Services\Orders;

use App\Jobs\NotifyVendorJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Cart\CartQuoter;
use App\Services\Cart\Data\CartLineInput;
use App\Services\Cart\Data\PriceQuote;
use App\Services\Discounts\Data\AppliedRule;
use App\Services\Discounts\Money;
use App\Services\Orders\Data\OrderParty;
use App\Services\Orders\Enums\NotificationStatus;
use App\Services\Orders\Enums\OrderStatus;
use App\Services\Orders\Enums\PaymentMethod;
use App\Services\Orders\Exceptions\IdempotencyConflictException;
use App\Services\Orders\Exceptions\OutOfStockException;
use App\Services\Orders\Exceptions\PriceChangedException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single creation path for orders. It prices the cart through the same
 * {@see CartQuoter} the storefront uses, locks stock, writes a full snapshot and
 * queues one vendor notification per sub-order.
 */
class PlaceOrderService
{
    public function __construct(private readonly CartQuoter $quoter) {}

    /**
     * @param  Collection<int, CartLineInput>  $lines
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $contact
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $delivery
     */
    public function placeOrder(
        ?User $customer,
        array $contact,
        Collection $lines,
        array $delivery,
        ?string $expectedTotal = null,
        ?string $idempotencyKey = null,
        ?string $notes = null,
    ): Order {
        $existing = $this->findIdempotentOrder($customer, $idempotencyKey);

        if ($existing !== null) {
            return $existing->load('subOrders.items');
        }

        try {
            return $this->createOrder(
                $customer,
                $contact,
                $lines->values(),
                $delivery,
                $expectedTotal,
                $idempotencyKey,
                $notes,
            );
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findIdempotentOrder($customer, $idempotencyKey);

            if ($existing !== null) {
                return $existing->load('subOrders.items');
            }

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, CartLineInput>  $lines
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $contact
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $delivery
     */
    private function createOrder(
        ?User $customer,
        array $contact,
        Collection $lines,
        array $delivery,
        ?string $expectedTotal,
        ?string $idempotencyKey,
        ?string $notes,
    ): Order {
        return DB::transaction(function () use ($customer, $contact, $lines, $delivery, $expectedTotal, $idempotencyKey, $notes): Order {
            if ($customer !== null) {
                User::query()->whereKey($customer->id)->lockForUpdate()->first();
            }

            $lines = $this->applyVendorSelectionPolicy($lines);
            $quote = $this->quoter->quote($lines, $customer);

            if ($expectedTotal !== null && Money::decimalToCents($expectedTotal) !== $quote->grandTotalCents) {
                throw new PriceChangedException($quote);
            }

            $products = $this->lockAndValidateStock($lines);

            $order = $this->persistOrder($customer, $contact, $delivery, $quote, $idempotencyKey, $notes, $products);

            foreach ($order->subOrders as $subOrder) {
                NotifyVendorJob::dispatch($subOrder->id)->afterCommit();
            }

            return $order->load('subOrders.items');
        });
    }

    /**
     * @param  Collection<int, CartLineInput>  $lines
     * @return Collection<int, CartLineInput>
     */
    private function applyVendorSelectionPolicy(Collection $lines): Collection
    {
        if (config('shop.vendor_selection') === 'lowest_price') {
            return $lines->map(
                fn (CartLineInput $line): CartLineInput => new CartLineInput($line->productId, $line->quantity),
            );
        }

        return $lines;
    }

    /**
     * Locks the products and rejects the order when any requested quantity is no
     * longer available, keying errors on the originating line indexes.
     *
     * @param  Collection<int, CartLineInput>  $lines
     * @return Collection<int, Product>
     */
    private function lockAndValidateStock(Collection $lines): Collection
    {
        /** @var array<int, list<int>> $indexesByProduct */
        $indexesByProduct = [];
        /** @var array<int, int> $quantities */
        $quantities = [];

        foreach ($lines as $index => $line) {
            $indexesByProduct[$line->productId][] = $index;
            $quantities[$line->productId] = ($quantities[$line->productId] ?? 0) + $line->quantity;
        }

        $products = Product::query()
            ->whereIn('id', array_keys($quantities))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            foreach ($indexesByProduct[$productId] as $index) {
                if ($product === null) {
                    $errors["lines.{$index}.product_id"][] = 'This product is no longer available.';

                    continue;
                }

                if ($quantity > $product->quantity) {
                    $errors["lines.{$index}.quantity"][] = sprintf(
                        'Only %d left in stock for this product.',
                        $product->quantity,
                    );
                }
            }
        }

        if ($errors !== []) {
            throw new OutOfStockException($errors);
        }

        foreach ($quantities as $productId => $quantity) {
            $products->get($productId)->decrement('quantity', $quantity);
        }

        return $products;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $contact
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $delivery
     * @param  Collection<int, Product>  $products
     */
    private function persistOrder(
        ?User $customer,
        array $contact,
        array $delivery,
        PriceQuote $quote,
        ?string $idempotencyKey,
        ?string $notes,
        Collection $products,
    ): Order {
        $customerParty = OrderParty::fromArray($contact);
        $deliveryParty = OrderParty::fromArray($delivery);

        $order = Order::create([
            'user_id' => $customer?->id,
            'guest_token' => $customer === null ? Str::random(40) : null,
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'subtotal' => Money::centsToDecimal($quote->subtotalCents),
            'discount_total' => Money::centsToDecimal($quote->discountTotalCents),
            'total' => Money::centsToDecimal($quote->grandTotalCents),
            'customer_name' => $customerParty->name,
            'customer_email' => $customerParty->email,
            'customer_phone' => $customerParty->phone,
            'customer_address' => $customerParty->address,
            'delivery_name' => $deliveryParty->name,
            'delivery_email' => $deliveryParty->email,
            'delivery_phone' => $deliveryParty->phone,
            'delivery_address' => $deliveryParty->address,
            'notes' => $notes,
            'placed_at' => now(),
            'idempotency_key' => $idempotencyKey,
        ]);

        $order->forceFill([
            'order_number' => 'GG-'.now()->year.'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ])->save();

        $vendors = Vendor::query()
            ->whereIn('id', collect($quote->vendors)->pluck('vendorId'))
            ->get()
            ->keyBy('id');

        foreach ($quote->vendors as $group) {
            $vendor = $vendors->get($group->vendorId);

            $subOrder = $order->subOrders()->create([
                'vendor_id' => $group->vendorId,
                'vendor_name' => $group->vendorName,
                'vendor_email' => (string) ($vendor?->email ?? ''),
                'vendor_phone' => $vendor?->phone,
                'status' => OrderStatus::Pending,
                'subtotal' => Money::centsToDecimal($group->subtotalCents),
                'discount_total' => Money::centsToDecimal($group->discountTotalCents),
                'total' => Money::centsToDecimal($group->totalCents),
                'notification_status' => NotificationStatus::Pending,
            ]);

            foreach ($group->lineIndexes as $lineIndex) {
                $line = $quote->lines[$lineIndex];
                $primaryRule = $line->appliedRules[0] ?? null;

                $subOrder->items()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->productId,
                    'vendor_id' => $group->vendorId,
                    'product_name' => $line->productName,
                    'product_sku' => $products->get($line->productId)?->sku,
                    'product_category' => $line->category['name'] ?? null,
                    'unit_price' => Money::centsToDecimal($line->unitPriceCents),
                    'quantity' => $line->quantity,
                    'discount_amount' => Money::centsToDecimal($line->discountAmountCents),
                    'applied_rule_id' => $primaryRule?->ruleId,
                    'applied_rule_name' => $primaryRule?->ruleName,
                    'applied_rule_type' => $primaryRule?->ruleType,
                    'applied_rules' => $this->rulesToArray($line->appliedRules),
                    'line_total' => Money::centsToDecimal($line->lineTotalCents),
                ]);
            }
        }

        return $order->load('subOrders.items');
    }

    /**
     * @param  list<AppliedRule>  $rules
     * @return list<array{rule_id: int, rule_name: string, rule_type: string, amount: string}>
     */
    private function rulesToArray(array $rules): array
    {
        return array_map(
            static fn (AppliedRule $rule): array => $rule->toArray(),
            $rules,
        );
    }

    private function findIdempotentOrder(?User $customer, ?string $key): ?Order
    {
        if ($key === null) {
            return null;
        }

        $order = Order::query()->where('idempotency_key', $key)->first();

        if ($order === null) {
            return null;
        }

        $ownsOrder = $customer !== null
            ? $order->user_id === $customer->id
            : $order->user_id === null;

        if (! $ownsOrder) {
            throw new IdempotencyConflictException;
        }

        return $order;
    }
}
