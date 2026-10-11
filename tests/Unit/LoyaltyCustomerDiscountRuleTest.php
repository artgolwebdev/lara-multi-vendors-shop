<?php

namespace Tests\Unit;

use App\Models\DiscountRule;
use App\Models\User;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Data\DiscountContext;
use App\Services\Discounts\LoyaltyCustomerDiscountRule;
use Tests\TestCase;

class LoyaltyCustomerDiscountRuleTest extends TestCase
{
    public function test_supports_loyalty_rules(): void
    {
        $rule = new DiscountRule(['type' => DiscountRule::TYPE_LOYALTY]);
        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 0;
            }
        };
        $this->assertTrue((new LoyaltyCustomerDiscountRule($history))->supports($rule));
    }

    public function test_applies_for_eligible_first_order_customer(): void
    {
        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 0;
            }
        };
        $rule = new DiscountRule([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);
        $customer = new User(['id' => 1, 'is_admin' => false]);
        $lines = collect([
            new CartLine(productId: 1, vendorId: 1, quantity: 2, unitPriceCents: 1000, categoryId: 1), // $20
        ]);
        $result = (new LoyaltyCustomerDiscountRule($history))->apply($lines, $rule, new DiscountContext($customer));
        $this->assertArrayHasKey(0, $result);
        $this->assertEquals(200, $result[0]); // 10% of 2000 cents = 200
    }

    public function test_does_not_apply_for_guest(): void
    {
        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 0;
            }
        };
        $rule = new DiscountRule([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);
        $lines = collect([new CartLine(productId: 1, vendorId: 1, quantity: 1, unitPriceCents: 1000, categoryId: 1)]);
        $result = (new LoyaltyCustomerDiscountRule($history))->apply($lines, $rule, new DiscountContext(null));
        $this->assertEmpty($result);
    }

    public function test_does_not_apply_for_admin(): void
    {
        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 0;
            }
        };
        $rule = new DiscountRule([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);
        $customer = new User;
        $customer->id = 1;
        $customer->is_admin = true;
        $lines = collect([new CartLine(productId: 1, vendorId: 1, quantity: 1, unitPriceCents: 1000, categoryId: 1)]);
        $result = (new LoyaltyCustomerDiscountRule($history))->apply($lines, $rule, new DiscountContext($customer));
        $this->assertEmpty($result);
    }

    public function test_does_not_apply_when_customer_has_orders(): void
    {
        $history = new class implements CustomerOrderHistory
        {
            public function completedOrdersCount(User $user): int
            {
                return 5;
            }
        };
        $rule = new DiscountRule([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);
        $customer = new User(['id' => 1, 'is_admin' => false]);
        $lines = collect([new CartLine(productId: 1, vendorId: 1, quantity: 1, unitPriceCents: 1000, categoryId: 1)]);
        $result = (new LoyaltyCustomerDiscountRule($history))->apply($lines, $rule, new DiscountContext($customer));
        $this->assertEmpty($result);
    }
}
