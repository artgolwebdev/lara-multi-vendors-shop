<?php

namespace App\Observers;

use App\Models\SubOrder;

class SubOrderObserver
{
    public function saved(SubOrder $subOrder): void
    {
        $subOrder->order?->syncStatusFromSubOrders();
    }

    public function deleted(SubOrder $subOrder): void
    {
        $subOrder->order?->syncStatusFromSubOrders();
    }
}
