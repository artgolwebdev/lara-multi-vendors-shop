<?php

namespace App\Services\Discounts\Implementations;

use App\Models\User;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use App\Services\Orders\Enums\OrderStatus;

class DatabaseCustomerOrderHistory implements CustomerOrderHistory
{
    public function completedOrdersCount(User $user): int
    {
        return $user->orders()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->count();
    }
}
