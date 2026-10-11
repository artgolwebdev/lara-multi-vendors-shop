<?php

namespace App\Services\Cart\Data;

use App\Services\Discounts\Data\AppliedRule;
use App\Services\Discounts\Data\DiscountHint;
use App\Services\Discounts\Money;

/**
 * Immutable priced cart line. Every monetary value is integer cents and is
 * serialized as a decimal string.
 */
final readonly class PriceQuoteLine
{
    /**
     * @param  list<AppliedRule>  $appliedRules
     * @param  list<array{vendor_id: int, vendor_name: string, price: string, discounted_price: string, is_lowest: bool}>  $vendorOptions
     * @param  array{id: int, name: string}|null  $category
     * @param  array{id: int, name: string}  $selectedVendor
     * @param  array{id: int, name: string}  $lowestVendor
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public string $productSlug,
        public ?string $image,
        public ?array $category,
        public array $selectedVendor,
        public int $unitPriceCents,
        public int $lowestPriceCents,
        public array $lowestVendor,
        public int $quantity,
        public int $stock,
        public int $lineSubtotalCents,
        public int $discountAmountCents,
        public array $appliedRules,
        public int $lineTotalCents,
        public ?DiscountHint $discountHint,
        public array $vendorOptions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $primaryRule = $this->appliedRules[0] ?? null;

        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_slug' => $this->productSlug,
            'image' => $this->image,
            'category' => $this->category,
            'selected_vendor' => $this->selectedVendor,
            'unit_price' => Money::centsToDecimal($this->unitPriceCents),
            'lowest_price' => Money::centsToDecimal($this->lowestPriceCents),
            'lowest_vendor' => $this->lowestVendor,
            'quantity' => $this->quantity,
            'stock' => $this->stock,
            'line_subtotal' => Money::centsToDecimal($this->lineSubtotalCents),
            'discount_amount' => Money::centsToDecimal($this->discountAmountCents),
            'applied_rule' => $primaryRule === null ? null : [
                'id' => $primaryRule->ruleId,
                'name' => $primaryRule->ruleName,
            ],
            'applied_rules' => array_map(
                static fn (AppliedRule $rule): array => $rule->toArray(),
                $this->appliedRules,
            ),
            'line_total' => Money::centsToDecimal($this->lineTotalCents),
            'discount_hint' => $this->discountHint?->toArray(),
            'vendor_options' => $this->vendorOptions,
        ];
    }
}
