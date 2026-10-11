<?php

namespace App\Services\Discounts;

/**
 * Deterministic integer-cents money math. Values never pass through floats so
 * persisted decimal strings convert to cents and back without precision loss.
 */
final class Money
{
    public static function decimalToCents(int|float|string $amount): int
    {
        $value = trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if ($value === '') {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = (string) preg_replace('/\D/', '', $fraction);

        $cents = (int) ($whole === '' ? '0' : $whole) * 100;
        $cents += (int) substr(str_pad($fraction, 2, '0'), 0, 2);

        if (strlen($fraction) > 2 && (int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * Converts a percentage (for example "12.50") into basis points (1250).
     */
    public static function percentageToBasisPoints(int|float|string $percentage): int
    {
        return self::decimalToCents($percentage);
    }

    /**
     * Applies a percentage to an amount, rounding half-up exactly once.
     */
    public static function applyPercentage(int $cents, int $basisPoints): int
    {
        return intdiv($cents * $basisPoints + self::HALF_BASIS_POINT, self::BASIS_POINT_SCALE);
    }

    private const BASIS_POINT_SCALE = 10000;

    private const HALF_BASIS_POINT = 5000;
}
