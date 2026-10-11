<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOffer>
 */
class ProductOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'vendor_id' => Vendor::factory(),
            'price' => fake()->randomFloat(2, 1, 500),
            'vendor_sku' => fake()->optional()->bothify('VSKU-####??'),
            'is_active' => true,
        ];
    }
}
