<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DiscountRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountRule extends Model
{
    /** @use HasFactory<DiscountRuleFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_QUANTITY = 'quantity';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_LOYALTY = 'loyalty';

    public const LOYALTY_CONDITION_FIRST_ORDER = 'first_order';

    public const AUDIENCE_ALL_CUSTOMERS = 'all_customers';

    public const AUDIENCE_SELECTED_CUSTOMERS = 'selected_customers';

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const DISCOUNT_TYPE_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'description',
        'type',
        'loyalty_condition',
        'audience',
        'discount_type',
        'value',
        'min_quantity',
        'vendor_id',
        'product_id',
        'category_id',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_QUANTITY, self::TYPE_CATEGORY, self::TYPE_LOYALTY];
    }

    /**
     * @return list<string>
     */
    public static function discountTypes(): array
    {
        return [self::DISCOUNT_TYPE_PERCENTAGE, self::DISCOUNT_TYPE_FIXED];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isActiveAt(?CarbonInterface $moment = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $moment ??= now();

        if ($this->starts_at !== null && $this->starts_at->greaterThan($moment)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lessThan($moment)) {
            return false;
        }

        return true;
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->ends_at !== null && $this->ends_at->lessThan($now)) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'loyalty_condition' => 'string',
            'audience' => 'string',
        ];
    }

    public function customers()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
