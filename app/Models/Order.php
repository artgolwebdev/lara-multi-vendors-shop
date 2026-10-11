<?php

namespace App\Models;

use App\Services\Orders\Enums\OrderStatus;
use App\Services\Orders\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'guest_token',
        'status',
        'payment_method',
        'subtotal',
        'discount_total',
        'total',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'delivery_name',
        'delivery_email',
        'delivery_phone',
        'delivery_address',
        'notes',
        'placed_at',
        'idempotency_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Recompute the main status from the sub-orders and persist it when changed.
     */
    public function syncStatusFromSubOrders(): void
    {
        $status = OrderStatus::deriveFromSubOrders($this->subOrders()->pluck('status')->all());

        if ($this->status !== $status) {
            $this->status = $status;
            $this->save();
        }
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }
}
