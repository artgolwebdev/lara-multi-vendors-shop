<?php

namespace App\Services\Cart\Data;

use App\Services\Discounts\Money;

/**
 * Totals for the lines a single vendor fulfils. This becomes a sub-order when
 * order creation is implemented.
 */
final readonly class VendorQuoteGroup
{
    /**
     * @param  list<int>  $lineIndexes
     */
    public function __construct(
        public int $vendorId,
        public string $vendorName,
        public int $subtotalCents,
        public int $discountTotalCents,
        public int $totalCents,
        public array $lineIndexes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vendor_id' => $this->vendorId,
            'vendor_name' => $this->vendorName,
            'subtotal' => Money::centsToDecimal($this->subtotalCents),
            'discount_total' => Money::centsToDecimal($this->discountTotalCents),
            'total' => Money::centsToDecimal($this->totalCents),
            'line_indexes' => $this->lineIndexes,
        ];
    }
}
