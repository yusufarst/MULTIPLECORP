<?php

namespace App\Modules\CAT\Data;

use InvalidArgumentException;

/**
 * Base-unit quantity arithmetic (BR-INV-03; L-31; DATABASE §7; numeric annex 10–11): an entered quantity converts to
 * the base by multiplication by its unit's integer ratio only — exact decimal strings, never floats, never division —,
 * and a base quantity is storable only at its product's scale, so a fractional base quantity of a counted or
 * serialized product (scale 0) is refused rather than rounded.
 */
final class Quantity
{
    private const PATTERN = '/^(\d{1,15})(?:\.(\d{1,3}))?$/';

    /** The entered quantity times the ratio, exactly, with three decimals. */
    public static function toBase(string $entered, int $ratio): string
    {
        if ($ratio < 1 || preg_match(self::PATTERN, $entered, $parts) !== 1) {
            throw new InvalidArgumentException('A quantity is a non-negative decimal of at most three places, its ratio a positive integer (C-09).');
        }

        $thousandths = self::multiply($parts[1].str_pad($parts[2] ?? '', 3, '0'), (string) $ratio);
        $thousandths = str_pad(ltrim($thousandths, '0'), 4, '0', STR_PAD_LEFT);

        return substr($thousandths, 0, -3).'.'.substr($thousandths, -3);
    }

    /** Whether the base quantity carries no digit beyond the product's scale (0–3). */
    public static function atScale(string $base, int $scale): bool
    {
        if (preg_match(self::PATTERN, $base, $parts) !== 1 || $scale < 0 || $scale > 3) {
            return false;
        }

        return rtrim(substr($parts[2] ?? '', $scale), '0') === '';
    }

    /** Schoolbook multiplication of two non-negative integer strings. */
    private static function multiply(string $a, string $b): string
    {
        $digits = array_fill(0, strlen($a) + strlen($b), 0);

        for ($i = strlen($a) - 1; $i >= 0; $i--) {
            for ($j = strlen($b) - 1; $j >= 0; $j--) {
                $sum = (int) $a[$i] * (int) $b[$j] + $digits[$i + $j + 1];
                $digits[$i + $j + 1] = $sum % 10;
                $digits[$i + $j] += intdiv($sum, 10);
            }
        }

        $product = ltrim(implode('', $digits), '0');

        return $product === '' ? '0' : $product;
    }
}
