<?php

namespace Tests\Unit\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\QuantityDiscountRule;
use Tests\TestCase;

class QuantityDiscountRuleTest extends TestCase
{
    public function test_it_supports_only_quantity_rules(): void
    {
        $rule = new QuantityDiscountRule;

        $this->assertTrue($rule->supports($this->rule()));
        $this->assertFalse($rule->supports($this->rule(['type' => DiscountRule::TYPE_CATEGORY])));
    }

    public function test_it_discounts_every_unit_of_a_qualifying_group(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 3, unitPriceCents: 1000, categoryId: 9),
        ]);

        $this->assertSame([0 => 300], $rule->apply($lines, $this->rule(['value' => 10])));
    }

    public function test_it_ignores_groups_below_the_minimum_quantity(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 9),
        ]);

        $this->assertSame([], $rule->apply($lines, $this->rule(['min_quantity' => 3])));
    }

    public function test_it_aggregates_duplicate_lines_before_matching(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 9),
            new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 9),
        ]);

        $this->assertSame([0 => 200, 1 => 200], $rule->apply($lines, $this->rule(['min_quantity' => 3])));
    }

    public function test_product_and_vendor_filters_constrain_eligible_groups(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 3, unitPriceCents: 1000),
            new CartLine(productId: 2, vendorId: 5, quantity: 3, unitPriceCents: 1000),
            new CartLine(productId: 1, vendorId: 6, quantity: 3, unitPriceCents: 1000),
        ]);

        $this->assertSame([0 => 300], $rule->apply($lines, $this->rule([
            'product_id' => 1,
            'vendor_id' => 5,
        ])));
    }

    public function test_fixed_rules_discount_per_unit(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 3, unitPriceCents: 1000),
        ]);

        $this->assertSame([0 => 450], $rule->apply($lines, $this->rule([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_FIXED,
            'value' => 1.50,
        ])));
    }

    public function test_percentage_rules_round_half_up_once_per_line(): void
    {
        $rule = new QuantityDiscountRule;
        $lines = collect([
            new CartLine(productId: 1, vendorId: 5, quantity: 1, unitPriceCents: 333),
        ]);

        $this->assertSame([0 => 50], $rule->apply($lines, $this->rule([
            'value' => 15,
            'min_quantity' => 1,
        ])));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes = []): DiscountRule
    {
        return new DiscountRule(array_merge([
            'name' => 'Quantity rule',
            'type' => DiscountRule::TYPE_QUANTITY,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'min_quantity' => 3,
            'is_active' => true,
            'priority' => 0,
        ], $attributes));
    }
}
