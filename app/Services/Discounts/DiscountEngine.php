<?php

namespace App\Services\Discounts;

use App\Models\DiscountRule;
use App\Services\Discounts\Data\AppliedRule;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Data\DiscountContext;
use App\Services\Discounts\Data\DiscountLineResult;
use App\Services\Discounts\Data\DiscountResult;
use Illuminate\Support\Collection;

/**
 * Stateless, no-write discount calculator. When no rules are supplied it loads
 * active, in-window rules; every matching rule is stacked cumulatively on each
 * line and the combined discount is capped at the line subtotal.
 */
class DiscountEngine
{
    /**
     * @var list<DiscountRuleInterface>
     */
    private array $ruleImplementations;

    /**
     * @param  iterable<DiscountRuleInterface>  $ruleImplementations
     */
    public function __construct(iterable $ruleImplementations)
    {
        $this->ruleImplementations = is_array($ruleImplementations)
            ? array_values($ruleImplementations)
            : array_values(iterator_to_array($ruleImplementations, false));
    }

    /**
     * @param  Collection<int, CartLine>  $cartLines
     * @param  Collection<int, DiscountRule>|null  $rules
     */
    public function calculate(Collection $cartLines, ?Collection $rules = null, ?DiscountContext $context = null): DiscountResult
    {
        $lines = $cartLines->values();
        $rules ??= $this->activeRules();

        /** @var array<int, list<AppliedRule>> $appliedRules */
        $appliedRules = [];
        /** @var array<int, int> $discounts */
        $discounts = array_fill(0, $lines->count(), 0);

        foreach ($rules as $rule) {
            $implementation = $this->implementationFor($rule);

            if ($implementation === null) {
                continue;
            }

            foreach ($implementation->apply($lines, $rule, $context) as $index => $amount) {
                $remaining = $lines[$index]->subtotalCents() - $discounts[$index];
                $appliedAmount = max(0, min($amount, $remaining));

                if ($appliedAmount === 0) {
                    continue;
                }

                $discounts[$index] += $appliedAmount;
                $appliedRules[$index][] = new AppliedRule($rule->id, $rule->name, $appliedAmount, $rule->type);
            }
        }

        $lineResults = [];
        $subtotalTotal = 0;
        $discountTotal = 0;

        foreach ($lines as $index => $line) {
            $subtotal = $line->subtotalCents();
            $discount = $discounts[$index];

            $lineResults[] = new DiscountLineResult(
                productId: $line->productId,
                vendorId: $line->vendorId,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                lineSubtotalCents: $subtotal,
                discountAmountCents: $discount,
                appliedRules: $appliedRules[$index] ?? [],
                lineTotalCents: $subtotal - $discount,
            );

            $subtotalTotal += $subtotal;
            $discountTotal += $discount;
        }

        return new DiscountResult(
            lines: $lineResults,
            subtotalCents: $subtotalTotal,
            discountTotalCents: $discountTotal,
            totalCents: $subtotalTotal - $discountTotal,
        );
    }

    /**
     * @return Collection<int, DiscountRule>
     */
    public function activeRules(): Collection
    {
        return DiscountRule::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function implementationFor(DiscountRule $rule): ?DiscountRuleInterface
    {
        foreach ($this->ruleImplementations as $implementation) {
            if ($implementation->supports($rule)) {
                return $implementation;
            }
        }

        return null;
    }
}
