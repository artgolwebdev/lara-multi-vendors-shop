<?php

namespace App\Models;

use Database\Factories\ProductOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOffer extends Model
{
    /** @use HasFactory<ProductOfferFactory> */
    use HasFactory;

    protected $table = 'product_vendor';

    protected $fillable = [
        'product_id',
        'vendor_id',
        'price',
        'vendor_sku',
        'is_active',
    ];

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
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
