<?php

namespace Tests\Unit\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\CategoryDiscountRule;
use App\Services\Discounts\Data\CartLine;
use Tests\TestCase;

class CategoryDiscountRuleTest extends TestCase
{
    public function test_it_supports_only_category_rules(): void
    {
        $rule = new CategoryDiscountRule;

        $this->assertTrue($rule->supports($this->rule()));
        $this->assertFalse($rule->supports($this->rule(['type' => DiscountRule::TYPE_QUANTITY])));
    }

    public function test_it_discounts_matching_category_lines_only(): void
    {
        $rule = new CategoryDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 7),
            new CartLine(productId: 2, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 8),
        ]);

        $this->assertSame([0 => 400], $rule->apply($lines, $this->rule(['value' => 20])));
    }

    public function test_it_returns_nothing_without_a_category(): void
    {
        $rule = new CategoryDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 7),
        ]);

        $this->assertSame([], $rule->apply($lines, $this->rule(['category_id' => null])));
    }

    public function test_vendor_filter_constrains_matching_lines(): void
    {
        $rule = new CategoryDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 1, unitPriceCents: 1000, categoryId: 7),
            new CartLine(productId: 2, vendorId: 6, quantity: 1, unitPriceCents: 1000, categoryId: 7),
        ]);

        $this->assertSame([0 => 100], $rule->apply($lines, $this->rule([
            'value' => 10,
            'vendor_id' => 5,
        ])));
    }

    public function test_fixed_rules_discount_per_unit(): void
    {
        $rule = new CategoryDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 4, unitPriceCents: 1000, categoryId: 7),
        ]);

        $this->assertSame([0 => 800], $rule->apply($lines, $this->rule([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_FIXED,
            'value' => 2,
        ])));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes = []): DiscountRule
    {
        return new DiscountRule(array_merge([
            'name' => 'Category rule',
            'type' => DiscountRule::TYPE_CATEGORY,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'category_id' => 7,
            'is_active' => true,
            'priority' => 0,
        ], $attributes));
    }
}
