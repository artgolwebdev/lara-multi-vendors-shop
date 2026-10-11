<?php

namespace App\Services\Discounts\Data;

use App\Services\Discounts\Money;

/**
 * Immutable snapshot-ready discount calculation result.
 */
final readonly class DiscountResult
{
    /**
     * @param  list<DiscountLineResult>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalCents,
        public int $discountTotalCents,
        public int $totalCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(
                static fn (DiscountLineResult $line): array => $line->toArray(),
                $this->lines,
            ),
            'subtotal' => Money::centsToDecimal($this->subtotalCents),
            'discount_total' => Money::centsToDecimal($this->discountTotalCents),
            'total' => Money::centsToDecimal($this->totalCents),
        ];
    }
}
