<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * Amounts are stored as integers in minor units (kopecks, cents) so sums never drift.
 */
final class Minor
{
    /** "1234.5" becomes 123450; a third decimal is rounded half up. */
    public static function fromDecimal(string $value): int
    {
        $value = trim($value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $m)) {
            throw new InvalidArgumentException("Not a decimal amount: {$value}");
        }

        $fraction = str_pad($m[3] ?? '', 3, '0');
        $minor = (int) $m[2] * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $m[1] === '-' ? -$minor : $minor;
    }

    public static function tryFromDecimal(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return self::fromDecimal($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
