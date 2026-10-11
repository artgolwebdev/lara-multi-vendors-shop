<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
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
            'sub_order_id' => SubOrder::factory(),
            'product_id' => Product::factory(),
            'vendor_id' => Vendor::factory(),
            'product_name' => fake()->words(2, true),
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_category' => fake()->word(),
            'unit_price' => '10.00',
            'quantity' => 1,
            'discount_amount' => '0.00',
            'applied_rule_id' => null,
            'applied_rule_name' => null,
            'applied_rule_type' => null,
            'applied_rules' => null,
            'line_total' => '10.00',
        ];
    }
}
