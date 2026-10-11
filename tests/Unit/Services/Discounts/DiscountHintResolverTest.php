<?php

namespace Tests\Unit\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\DiscountHintResolver;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DiscountHintResolverTest extends TestCase
{
    private function resolver(DiscountRule ...$rules): DiscountHintResolver
    {
        $engine = new FakeDiscountEngine(collect($rules));

        return new DiscountHintResolver($engine);
    }

    private function quantityRule(array $attributes = []): DiscountRule
    {
        return DiscountRule::factory()->quantity(2)->make(array_merge([
            'name' => 'Volume discount',
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'product_id' => null,
            'vendor_id' => null,
        ], $attributes));
    }

    public function test_it_builds_a_percentage_hint_for_a_quantity_rule(): void
    {
        $hint = $this->resolver($this->quantityRule())->hintFor(1, 1);

        $this->assertNotNull($hint);
        $this->assertSame(2, $hint->minQuantity);
        $this->assertSame('Buy 2+ and get 10% off', $hint->label);
    }

    public function test_it_trims_trailing_zeros_from_the_percentage(): void
    {
        $hint = $this->resolver($this->quantityRule(['value' => '12.50']))->hintFor(1, 1);

        $this->assertNotNull($hint);
        $this->assertSame('Buy 2+ and get 12.5% off', $hint->label);
    }

    public function test_it_builds_a_fixed_hint(): void
    {
        $hint = $this->resolver($this->quantityRule([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_FIXED,
            'value' => '5.00',
        ]))->hintFor(1, 1);

        $this->assertNotNull($hint);
        $this->assertSame('Buy 2+ and save $5.00 each', $hint->label);
    }

    public function test_it_returns_null_when_no_quantity_rule_matches(): void
    {
        $productScoped = $this->quantityRule(['product_id' => 99]);
        $vendorScoped = $this->quantityRule(['vendor_id' => 99]);

        $this->assertNull($this->resolver($productScoped, $vendorScoped)->hintFor(1, 1));
    }

    public function test_it_matches_a_product_scoped_rule(): void
    {
        $hint = $this->resolver($this->quantityRule(['product_id' => 7]))->hintFor(7, 1);

        $this->assertNotNull($hint);
    }

    public function test_it_ignores_non_quantity_rules_and_low_thresholds(): void
    {
        $categoryRule = DiscountRule::factory()->make([
            'type' => DiscountRule::TYPE_CATEGORY,
            'min_quantity' => null,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
        ]);
        $singleQuantity = $this->quantityRule(['min_quantity' => 1]);

        $this->assertNull($this->resolver($categoryRule, $singleQuantity)->hintFor(1, 1));
    }
}

class FakeDiscountEngine extends DiscountEngine
{
    public function __construct(private readonly Collection $rules) {}

    public function activeRules(): Collection
    {
        return $this->rules;
    }
}
