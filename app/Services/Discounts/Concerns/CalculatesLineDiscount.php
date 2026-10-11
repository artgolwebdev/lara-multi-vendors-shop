<?php

namespace App\Services\Discounts\Concerns;

use App\Models\DiscountRule;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Money;

trait CalculatesLineDiscount
{
    private function lineDiscount(CartLine $line, DiscountRule $rule): int
    {
        if ($rule->discount_type === DiscountRule::DISCOUNT_TYPE_FIXED) {
            return Money::decimalToCents($rule->value) * $line->quantity;
        }

        return Money::applyPercentage(
            $line->subtotalCents(),
            Money::percentageToBasisPoints($rule->value),
        );
    }
}
