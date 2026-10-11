<?php

namespace Database\Factories;

use App\Models\Order;
use App\Services\Orders\Enums\OrderStatus;
use App\Services\Orders\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'GG-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => null,
            'guest_token' => Str::random(40),
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'total' => '100.00',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_address' => fake()->streetAddress(),
            'delivery_name' => fake()->name(),
            'delivery_email' => fake()->safeEmail(),
            'delivery_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->streetAddress(),
            'notes' => null,
            'placed_at' => now(),
            'idempotency_key' => null,
        ];
    }

    public function forCustomer(int $userId): static
    {
        return $this->state(fn (): array => [
            'user_id' => $userId,
            'guest_token' => null,
        ]);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
