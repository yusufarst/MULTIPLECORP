<?php

namespace App\Modules\INV\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * PT-17's quantity block from the guard's base quantities (CALC-01; BR-INV-01; annex 7): AVAILABLE = ON HAND − RESERVED − UNUSABLE, in base units with the alternate unit as a hint, never rounded up.
 */
final class StockFigures
{
    /** @return array{physical: string, reserved: string, unusable: string, available: string, unit: string, alternate?: array{physical: string, reserved: string, unusable: string, available: string, unit: string}} */
    public static function of(string $onHand, string $reserved, string $unusable, string $unit, ?string $alternateUnit = null, ?int $ratio = null): array
    {
        $available = (string) BigDecimal::of($onHand)->minus($reserved)->minus($unusable);
        $figures = ['physical' => self::plain($onHand), 'reserved' => self::plain($reserved), 'unusable' => self::plain($unusable), 'available' => self::plain($available), 'unit' => $unit];

        if ($alternateUnit !== null && $ratio !== null && $ratio > 1) {
            $figures['alternate'] = ['physical' => self::hint($onHand, $ratio), 'reserved' => self::hint($reserved, $ratio), 'unusable' => self::hint($unusable, $ratio), 'available' => self::hint($available, $ratio), 'unit' => $alternateUnit];
        }

        return $figures;
    }

    /** A base quantity without trailing zeros. */
    public static function plain(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }

    /** The alternate unit's hint, whole alternates and a part, down to three places. */
    public static function hint(string $base, int $ratio): string
    {
        return self::plain((string) BigDecimal::of($base)->dividedBy($ratio, 3, RoundingMode::Down));
    }
}
