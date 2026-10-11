<?php

namespace App\Services\Orders\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function rank(): ?int
    {
        return match ($this) {
            self::Pending => 0,
            self::Confirmed => 1,
            self::Shipped => 2,
            self::Delivered => 3,
            self::Cancelled => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Derive the main order status from its sub-orders: the lowest rank among
     * the non-cancelled sub-orders wins, or cancelled when none remain.
     *
     * @param  iterable<self|string>  $statuses
     */
    public static function deriveFromSubOrders(iterable $statuses): self
    {
        $active = [];

        foreach ($statuses as $status) {
            $status = $status instanceof self ? $status : self::from((string) $status);

            if ($status === self::Cancelled) {
                continue;
            }

            $active[] = $status;
        }

        if ($active === []) {
            return self::Cancelled;
        }

        usort(
            $active,
            static fn (self $first, self $second): int => ($first->rank() ?? PHP_INT_MAX) <=> ($second->rank() ?? PHP_INT_MAX),
        );

        return $active[0];
    }
}
