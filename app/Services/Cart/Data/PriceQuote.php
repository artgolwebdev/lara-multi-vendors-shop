<?php

namespace App\Services\Cart\Data;

use App\Services\Discounts\Money;

/**
 * Immutable snapshot-ready cart quote. All money is integer cents and is
 * serialized as decimal strings.
 */
final readonly class PriceQuote
{
    /**
     * @param  list<PriceQuoteLine>  $lines
     * @param  list<VendorQuoteGroup>  $vendors
     */
    public function __construct(
        public array $lines,
        public array $vendors,
        public int $subtotalCents,
        public int $discountTotalCents,
        public int $grandTotalCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(
                static fn (PriceQuoteLine $line): array => $line->toArray(),
                $this->lines,
            ),
            'vendors' => array_map(
                static fn (VendorQuoteGroup $vendor): array => $vendor->toArray(),
                $this->vendors,
            ),
            'subtotal' => Money::centsToDecimal($this->subtotalCents),
            'discount_total' => Money::centsToDecimal($this->discountTotalCents),
            'grand_total' => Money::centsToDecimal($this->grandTotalCents),
        ];
    }
}
