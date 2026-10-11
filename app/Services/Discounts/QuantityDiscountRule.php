<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Concerns\CalculatesLineDiscount;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Data\DiscountContext;
use Illuminate\Support\Collection;

/**
 * Quantity rules aggregate units per (product, vendor) before matching
 * min_quantity, then discount every unit of each qualifying group. Optional
 * product and vendor filters constrain which groups are eligible.
 */
class QuantityDiscountRule implements DiscountRuleInterface
{
    use CalculatesLineDiscount;

    public function supports(DiscountRule $rule): bool
    {
        return $rule->type === DiscountRule::TYPE_QUANTITY;
    }

    public function apply(Collection $cartLines, DiscountRule $rule, ?DiscountContext $context = null): array
    {
        $minimumQuantity = (int) ($rule->min_quantity ?? 0);

        if ($minimumQuantity < 1) {
            return [];
        }

        $groups = [];
        $groupLines = [];

        foreach ($cartLines as $index => $line) {
            if (! $this->matchesFilters($line, $rule)) {
                continue;
            }

            $key = $line->productId.'|'.$line->vendorId;
            $groups[$key] = ($groups[$key] ?? 0) + $line->quantity;
            $groupLines[$key][] = $index;
        }

        $discounts = [];

        foreach ($groups as $key => $quantity) {
            if ($quantity < $minimumQuantity) {
                continue;
            }

            foreach ($groupLines[$key] as $index) {
                $discounts[$index] = $this->lineDiscount($cartLines[$index], $rule);
            }
        }

        return $discounts;
    }

    private function matchesFilters(CartLine $line, DiscountRule $rule): bool
    {
        if ($rule->product_id !== null && $rule->product_id !== $line->productId) {
            return false;
        }

        if ($rule->vendor_id !== null && $rule->vendor_id !== $line->vendorId) {
            return false;
        }

        return true;
    }
}
