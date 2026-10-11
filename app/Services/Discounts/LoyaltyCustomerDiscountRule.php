<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Concerns\CalculatesLineDiscount;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use App\Services\Discounts\Data\DiscountContext;
use Illuminate\Support\Collection;

class LoyaltyCustomerDiscountRule implements DiscountRuleInterface
{
    use CalculatesLineDiscount;

    public function __construct(private readonly CustomerOrderHistory $orderHistory) {}

    public function supports(DiscountRule $rule): bool
    {
        return $rule->type === DiscountRule::TYPE_LOYALTY;
    }

    public function apply(Collection $cartLines, DiscountRule $rule, ?DiscountContext $context = null): array
    {
        $customer = $context?->customer;
        if ($customer === null || $customer->is_admin) {
            return [];
        }

        if ($rule->loyalty_condition !== DiscountRule::LOYALTY_CONDITION_FIRST_ORDER) {
            return [];
        }

        // Check audience
        if ($rule->audience === DiscountRule::AUDIENCE_SELECTED_CUSTOMERS) {
            if (! $rule->customers()->where('user_id', $customer->id)->exists()) {
                return [];
            }
        }

        // Check first order condition
        if ($this->orderHistory->completedOrdersCount($customer) > 0) {
            return [];
        }

        $discounts = [];
        foreach ($cartLines as $index => $line) {
            // Apply to all lines by default; filters (vendor_id/category_id) if set on rule
            if ($rule->category_id !== null && $line->categoryId !== $rule->category_id) {
                continue;
            }
            if ($rule->vendor_id !== null && $line->vendorId !== $rule->vendorId) {
                continue;
            }
            $discounts[$index] = $this->lineDiscount($line, $rule);
        }

        return $discounts;
    }
}
