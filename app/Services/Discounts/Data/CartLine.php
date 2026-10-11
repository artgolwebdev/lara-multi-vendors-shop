<?php

namespace App\Services\Discounts\Data;

/**
 * Immutable discount-engine input line. All money is expressed in integer cents.
 */
final readonly class CartLine
{
    public function __construct(
        public int $productId,
        public int $vendorId,
        public int $quantity,
        public int $unitPriceCents,
        public ?int $categoryId = null,
    ) {}

    public function subtotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }
}
