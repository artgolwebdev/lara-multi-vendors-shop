<?php

namespace App\Services\Discounts\Contracts;

use App\Models\User;

interface CustomerOrderHistory
{
    public function completedOrdersCount(User $user): int;
}
