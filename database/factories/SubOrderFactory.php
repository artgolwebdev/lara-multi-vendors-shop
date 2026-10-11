<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Services\Orders\Enums\NotificationStatus;
use App\Services\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubOrder>
 */
class SubOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'vendor_name' => fake()->company(),
            'vendor_email' => fake()->unique()->companyEmail(),
            'vendor_phone' => fake()->phoneNumber(),
            'status' => OrderStatus::Pending,
            'subtotal' => '50.00',
            'discount_total' => '0.00',
            'total' => '50.00',
            'notified_at' => null,
            'notification_status' => NotificationStatus::Pending,
        ];
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (): array => [
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'vendor_email' => $vendor->email,
            'vendor_phone' => $vendor->phone,
        ]);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
