<?php

namespace App\Modules\FUL\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A shipment line's delivery figures, pure (PX-08; C-30; C-09): net delivered = its deliveries less their exact reversals;
 * what a refusal or an undelivered closure still holds back is the closed quantity the dispatch's restoration has not yet
 * brought back (SF-RESTORE); what can still be delivered is net shipped less both. A quantity entered at the shipment's
 * unit converts at its snapshot and must be whole in the product's scale.
 */
final class ShipmentFigures
{
    /**
     * @param  list<array{qty: string, reversal: bool}>  $lines  the line's delivery lines in base units
     */
    public static function delivered(array $lines): BigDecimal
    {
        $net = BigDecimal::zero();

        foreach ($lines as $line) {
            $net = $line['reversal'] ? $net->minus($line['qty']) : $net->plus($line['qty']);
        }

        return $net;
    }

    /** The closed quantity still out: closed for refusal or non-delivery, less what came back undelivered since. */
    public static function held(BigDecimal $closed, BigDecimal $returned): BigDecimal
    {
        return BigDecimal::max(BigDecimal::zero(), $closed->minus($returned));
    }

    /** What can still be delivered or closed: net shipped − net delivered − held, never below zero. */
    public static function remainder(BigDecimal $shipped, BigDecimal $delivered, BigDecimal $held): BigDecimal
    {
        return BigDecimal::max(BigDecimal::zero(), $shipped->minus($delivered)->minus($held));
    }

    /** An entered quantity in base units at the shipment's snapshot; null when it is not positive or not whole in the scale (C-09). */
    public static function base(string $entered, int $ratio, int $scale): ?BigDecimal
    {
        $base = BigDecimal::of($entered)->multipliedBy(max(1, $ratio));

        return $base->isPositive() && $base->isEqualTo($base->toScale(max(0, $scale), RoundingMode::Down)) ? $base : null;
    }
}
