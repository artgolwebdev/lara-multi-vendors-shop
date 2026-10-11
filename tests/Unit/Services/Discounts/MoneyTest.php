<?php

namespace Tests\Unit\Services\Discounts;

use App\Services\Discounts\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_decimal_strings_to_cents_without_floats(): void
    {
        $this->assertSame(1250, Money::decimalToCents('12.50'));
        $this->assertSame(1000, Money::decimalToCents('10'));
        $this->assertSame(5, Money::decimalToCents('0.05'));
        $this->assertSame(0, Money::decimalToCents(''));
        $this->assertSame(-320, Money::decimalToCents('-3.20'));
    }

    public function test_it_rounds_extra_precision_half_up(): void
    {
        $this->assertSame(1235, Money::decimalToCents('12.345'));
        $this->assertSame(1234, Money::decimalToCents('12.344'));
    }

    public function test_it_serializes_cents_back_to_decimal_strings(): void
    {
        $this->assertSame('12.50', Money::centsToDecimal(1250));
        $this->assertSame('0.00', Money::centsToDecimal(0));
        $this->assertSame('0.05', Money::centsToDecimal(5));
        $this->assertSame('-3.20', Money::centsToDecimal(-320));
    }

    public function test_it_applies_percentages_with_half_up_rounding(): void
    {
        $this->assertSame(100, Money::applyPercentage(1000, 1000));
        $this->assertSame(50, Money::applyPercentage(333, 1500));
        $this->assertSame(5, Money::applyPercentage(101, 500));
    }
}
