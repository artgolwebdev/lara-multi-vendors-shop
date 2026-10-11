<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => DiscountRule::TYPE_QUANTITY,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => fake()->randomFloat(2, 1, 30),
            'min_quantity' => fake()->numberBetween(2, 10),
            'vendor_id' => null,
            'product_id' => null,
            'category_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 20),
        ];
    }

    public function quantity(?int $minimumQuantity = null): static
    {
        return $this->state(fn (): array => [
            'type' => DiscountRule::TYPE_QUANTITY,
            'min_quantity' => $minimumQuantity ?? fake()->numberBetween(2, 10),
        ]);
    }

    public function category(?Category $category = null): static
    {
        return $this->state(fn (): array => [
            'type' => DiscountRule::TYPE_CATEGORY,
            'min_quantity' => null,
            'category_id' => $category?->id ?? Category::factory(),
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (): array => [
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => [
            'discount_type' => DiscountRule::DISCOUNT_TYPE_FIXED,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => ['product_id' => $product->id]);
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (): array => ['vendor_id' => $vendor->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
