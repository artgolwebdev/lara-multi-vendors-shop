<?php

namespace App\Services\Discounts\Implementations;

use App\Models\User;
use App\Services\Discounts\Contracts\CustomerOrderHistory;

class DefaultCustomerOrderHistory implements CustomerOrderHistory
{
    public function completedOrdersCount(User $user): int
    {
        return 0; // Stub until orders table exists
    }
}
