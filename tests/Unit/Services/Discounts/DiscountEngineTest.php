<?php

namespace Tests\Unit\Services\Discounts;

use App\Models\DiscountRule;
use App\Models\User;
use App\Services\Discounts\CategoryDiscountRule;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Data\DiscountContext;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\LoyaltyCustomerDiscountRule;
use App\Services\Discounts\QuantityDiscountRule;
use Tests\TestCase;

class DiscountEngineTest extends TestCase
{
    public function test_it_stacks_all_matching_rules_cumulatively(): void
    {
        $result = $this->engine()->calculate(
            collect([$this->line()]),
            collect([
                $this->rule(1, ['value' => 10]),
                $this->rule(2, [
                    'type' => DiscountRule::TYPE_CATEGORY,
                    'category_id' => 7,
                    'value' => 25,
                ]),
            ]),
        );

        $this->assertSame(700, $result->discountTotalCents);
        $this->assertSame(1300, $result->totalCents);
        $this->assertSame(2000, $result->subtotalCents);
        $this->assertCount(2, $result->lines[0]->appliedRules);
        $this->assertSame(700, $result->lines[0]->discountAmountCents);
    }

    public function test_it_caps_the_combined_discount_at_the_line_subtotal(): void
    {
        $result = $this->engine()->calculate(
            collect([$this->line()]),
            collect([
                $this->rule(1, ['value' => 100]),
                $this->rule(2, ['value' => 100]),
            ]),
        );

        $this->assertSame(2000, $result->discountTotalCents);
        $this->assertSame(0, $result->totalCents);
        $this->assertCount(1, $result->lines[0]->appliedRules);
        $this->assertSame(1, $result->lines[0]->appliedRules[0]->ruleId);
    }

    public function test_it_preserves_rule_order_in_the_applied_breakdown(): void
    {
        $result = $this->engine()->calculate(
            collect([$this->line()]),
            collect([
                $this->rule(9, ['name' => 'First', 'priority' => 50]),
                $this->rule(3, ['name' => 'Second', 'priority' => 10]),
            ]),
        );

        $names = array_map(
            static fn ($applied): string => $applied->ruleName,
            $result->lines[0]->appliedRules,
        );

        $this->assertSame(['First', 'Second'], $names);
    }

    public function test_it_aggregates_totals_across_lines(): void
    {
        $result = $this->engine()->calculate(
            collect([
                $this->line(),
                new CartLine(productId: 2, vendorId: 6, quantity: 1, unitPriceCents: 500, categoryId: 8),
            ]),
            collect([$this->rule(1, ['value' => 10])]),
        );

        $this->assertSame(2500, $result->subtotalCents);
        $this->assertSame(250, $result->discountTotalCents);
        $this->assertSame(2250, $result->totalCents);
        $this->assertSame(200, $result->lines[0]->discountAmountCents);
        $this->assertSame(50, $result->lines[1]->discountAmountCents);
    }

    public function test_it_resolves_tagged_implementations_from_the_container(): void
    {
        $this->assertCount(3, iterator_to_array(app()->tagged('discount.rules')));

        $result = app(DiscountEngine::class)->calculate(
            collect([$this->line()]),
            collect([$this->rule(1, ['value' => 10])]),
        );

        $this->assertSame(200, $result->discountTotalCents);
    }

    public function test_rule_activation_respects_status_and_window(): void
    {
        $this->assertFalse($this->rule(1, ['is_active' => false])->isActiveAt());

        $scheduled = $this->rule(1, ['starts_at' => now()->addDay()]);
        $this->assertFalse($scheduled->isActiveAt());
        $this->assertSame(DiscountRule::STATUS_SCHEDULED, $scheduled->status());

        $expired = $this->rule(1, ['ends_at' => now()->subDay()]);
        $this->assertFalse($expired->isActiveAt());
        $this->assertSame(DiscountRule::STATUS_EXPIRED, $expired->status());

        $active = $this->rule(1, [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $this->assertTrue($active->isActiveAt());
        $this->assertSame(DiscountRule::STATUS_ACTIVE, $active->status());
    }

    public function test_it_stacks_loyalty_with_category_rules_via_tagged_binding(): void
    {
        $customer = new User;
        $customer->id = 1;
        $customer->is_admin = false;

        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 0;
            }
        };

        $engine = new DiscountEngine([
            new QuantityDiscountRule,
            new CategoryDiscountRule,
            new LoyaltyCustomerDiscountRule($history),
        ]);

        $result = $engine->calculate(
            collect([$this->line()]),
            collect([
                $this->rule(2, [
                    'type' => DiscountRule::TYPE_CATEGORY,
                    'category_id' => 7,
                    'value' => 25,
                ]),
                $this->rule(3, [
                    'type' => DiscountRule::TYPE_LOYALTY,
                    'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
                    'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
                    'value' => 10,
                ]),
            ]),
            new DiscountContext($customer),
        );

        $this->assertSame(700, $result->discountTotalCents);
        $this->assertCount(2, $result->lines[0]->appliedRules);
    }

    private function engine(): DiscountEngine
    {
        return new DiscountEngine([
            new QuantityDiscountRule,
            new CategoryDiscountRule,
        ]);
    }

    private function line(): CartLine
    {
        return new CartLine(productId: 1, vendorId: 5, quantity: 2, unitPriceCents: 1000, categoryId: 7);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(int $id, array $attributes = []): DiscountRule
    {
        $rule = new DiscountRule(array_merge([
            'name' => 'Rule '.$id,
            'type' => DiscountRule::TYPE_QUANTITY,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'min_quantity' => 1,
            'category_id' => 7,
            'is_active' => true,
            'priority' => 0,
        ], $attributes));

        $rule->id = $id;

        return $rule;
    }
}
