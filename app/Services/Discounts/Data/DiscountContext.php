<?php

namespace App\Services\Discounts\Data;

use App\Models\User;

final readonly class DiscountContext
{
    public function __construct(
        public ?User $customer = null,
    ) {}
}
