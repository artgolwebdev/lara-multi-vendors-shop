<?php

namespace App\Models;

use App\Observers\SubOrderObserver;
use App\Services\Orders\Enums\NotificationStatus;
use App\Services\Orders\Enums\OrderStatus;
use Database\Factories\SubOrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(SubOrderObserver::class)]
class SubOrder extends Model
{
    /** @use HasFactory<SubOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_id',
        'vendor_name',
        'vendor_email',
        'vendor_phone',
        'status',
        'subtotal',
        'discount_total',
        'total',
        'notified_at',
        'notification_status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'notification_status' => NotificationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'notified_at' => 'datetime',
        ];
    }
}
