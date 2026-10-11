<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Data\DiscountHint;
use Illuminate\Support\Collection;

/**
 * Builds display-only quantity hints from the active quantity rules. All pricing
 * still flows through {@see DiscountEngine}; this resolver only reports the
 * lowest-priority threshold a shopper could reach for a product/vendor pair.
 */
class DiscountHintResolver
{
    /**
     * @var Collection<int, DiscountRule>|null
     */
    private ?Collection $quantityRules = null;

    public function __construct(private readonly DiscountEngine $engine) {}

    public function hintFor(int $productId, int $vendorId): ?DiscountHint
    {
        foreach ($this->quantityRules() as $rule) {
            if (! $this->matches($rule, $productId, $vendorId)) {
                continue;
            }

            return new DiscountHint(
                (int) $rule->min_quantity,
                $this->label($rule),
            );
        }

        return null;
    }

    /**
     * @return Collection<int, DiscountRule>
     */
    private function quantityRules(): Collection
    {
        return $this->quantityRules ??= $this->engine
            ->activeRules()
            ->where('type', DiscountRule::TYPE_QUANTITY)
            ->values();
    }

    private function matches(DiscountRule $rule, int $productId, int $vendorId): bool
    {
        if ($rule->min_quantity === null || (int) $rule->min_quantity < 2) {
            return false;
        }

        if ($rule->product_id !== null && (int) $rule->product_id !== $productId) {
            return false;
        }

        if ($rule->vendor_id !== null && (int) $rule->vendor_id !== $vendorId) {
            return false;
        }

        return true;
    }

    private function label(DiscountRule $rule): string
    {
        $minimumQuantity = (int) $rule->min_quantity;

        if ($rule->discount_type === DiscountRule::DISCOUNT_TYPE_FIXED) {
            return sprintf('Buy %d+ and save $%s each', $minimumQuantity, $rule->value);
        }

        return sprintf('Buy %d+ and get %s%% off', $minimumQuantity, $this->trimPercentage($rule->value));
    }

    private function trimPercentage(string $value): string
    {
        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
