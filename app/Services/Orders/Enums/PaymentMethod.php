<?php

namespace App\Services\Orders\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
        };
    }
}
