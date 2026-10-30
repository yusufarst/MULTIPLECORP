<?php

namespace App\Modules\FUL\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A service line's remaining-to-hand-over figure in base units (C-09; L-39): the pinned demand less what is handed over
 * and cancelled — reversal lines having already moved the guard back —, never below zero; and that figure in the line's
 * entered unit, rounded down so the form never offers more than the remainder.
 */
final class HandoverProgress
{
    public static function remainder(string $demand, string $handedOver, string $cancelled): string
    {
        $left = BigDecimal::of($demand)->minus($handedOver)->minus($cancelled);

        return (string) ($left->isNegative() ? BigDecimal::zero()->toScale(3) : $left->toScale(3));
    }

    public static function inEnteredUnit(string $base, int $ratio, int $scale): string
    {
        return (string) BigDecimal::of($base)->dividedBy($ratio, max(0, $scale), RoundingMode::Down);
    }
}
