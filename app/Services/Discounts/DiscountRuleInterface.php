<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Data\CartLine;
use Illuminate\Support\Collection;

/**
 * A stateless, deterministic transformer for one discount rule type. The engine
 * resolves implementations dynamically so it never branches on rule types.
 */
interface DiscountRuleInterface
{
    public function supports(DiscountRule $rule): bool;

    /**
     * @param  Collection<int, CartLine>  $cartLines
     * @return array<int, int> Cart line index to discount amount in cents.
     */
    public function apply(Collection $cartLines, DiscountRule $rule): array;
}
