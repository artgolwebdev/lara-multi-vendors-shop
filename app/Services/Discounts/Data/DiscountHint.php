<?php

namespace App\Services\Discounts\Data;

/**
 * A lightweight, display-only hint for a quantity rule that cannot yet be
 * reflected at the current quantity (for example "Buy 2+ and get 10% off").
 */
final readonly class DiscountHint
{
    public function __construct(
        public int $minQuantity,
        public string $label,
    ) {}

    /**
     * @return array{min_quantity: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'min_quantity' => $this->minQuantity,
            'label' => $this->label,
        ];
    }
}
