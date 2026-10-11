<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Concerns\CalculatesLineDiscount;
use App\Services\Discounts\Data\DiscountContext;
use Illuminate\Support\Collection;

/**
 * Category rules discount every line whose product belongs to the configured
 * category, optionally constrained to a single vendor.
 */
class CategoryDiscountRule implements DiscountRuleInterface
{
    use CalculatesLineDiscount;

    public function supports(DiscountRule $rule): bool
    {
        return $rule->type === DiscountRule::TYPE_CATEGORY;
    }

    public function apply(Collection $cartLines, DiscountRule $rule, ?DiscountContext $context = null): array
    {
        if ($rule->category_id === null) {
            return [];
        }

        $discounts = [];

        foreach ($cartLines as $index => $line) {
            if ($line->categoryId !== $rule->category_id) {
                continue;
            }

            if ($rule->vendor_id !== null && $rule->vendor_id !== $line->vendorId) {
                continue;
            }

            $discounts[$index] = $this->lineDiscount($line, $rule);
        }

        return $discounts;
    }
}
