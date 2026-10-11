<?php

namespace App\Services\Discounts\Data;

use App\Services\Discounts\Money;

/**
 * A single rule contribution to one cart line.
 */
final readonly class AppliedRule
{
    public function __construct(
        public int $ruleId,
        public string $ruleName,
        public int $amountCents,
    ) {}

    /**
     * @return array{rule_id: int, rule_name: string, amount: string}
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'amount' => Money::centsToDecimal($this->amountCents),
        ];
    }
}
