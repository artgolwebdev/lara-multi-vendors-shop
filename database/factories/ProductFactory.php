<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'description' => fake()->optional()->paragraph(),
            'category_id' => Category::factory(),
            'quantity' => fake()->numberBetween(0, 200),
            'is_active' => true,
        ];
    }
}
