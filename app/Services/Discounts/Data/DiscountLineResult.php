<?php

namespace App\Services\Discounts\Data;

use App\Services\Discounts\Money;

/**
 * Immutable per-line discount breakdown.
 */
final readonly class DiscountLineResult
{
    /**
     * @param  list<AppliedRule>  $appliedRules
     */
    public function __construct(
        public int $productId,
        public int $vendorId,
        public int $quantity,
        public int $unitPriceCents,
        public int $lineSubtotalCents,
        public int $discountAmountCents,
        public array $appliedRules,
        public int $lineTotalCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'vendor_id' => $this->vendorId,
            'quantity' => $this->quantity,
            'unit_price' => Money::centsToDecimal($this->unitPriceCents),
            'line_subtotal' => Money::centsToDecimal($this->lineSubtotalCents),
            'discount_amount' => Money::centsToDecimal($this->discountAmountCents),
            'applied_rule_id' => $this->appliedRules[0]->ruleId ?? null,
            'applied_rule_name' => $this->appliedRules[0]->ruleName ?? null,
            'applied_rules' => array_map(
                static fn (AppliedRule $rule): array => $rule->toArray(),
                $this->appliedRules,
            ),
            'line_total' => Money::centsToDecimal($this->lineTotalCents),
        ];
    }
}
