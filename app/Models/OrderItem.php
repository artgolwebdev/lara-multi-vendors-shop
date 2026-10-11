<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'product_id',
        'vendor_id',
        'product_name',
        'product_sku',
        'product_category',
        'unit_price',
        'quantity',
        'discount_amount',
        'applied_rule_id',
        'applied_rule_name',
        'applied_rule_type',
        'applied_rules',
        'line_total',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'discount_amount' => 'decimal:2',
            'applied_rules' => 'array',
            'line_total' => 'decimal:2',
        ];
    }
}
