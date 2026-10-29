<?php

namespace App\Support\Costing;

use App\Support\Money;

/**
 * The contra of part of a consumption (ARCHITECTURE §6; DATABASE §6.2; L-31; GAP-025): a restoration,
 * reclassification or substitution of `qty` units takes the proportional share of the consumption's net cost of the
 * class it reverses over its open quantity — quantity less restored, reclassified and substituted —, the event that
 * zeroes the open quantity taking exactly what is left. A total that includes another class is never the base, so a
 * return after a loss-in-transit reclassification never contras the reclassified loss again.
 */
final class ProportionalContra
{
    public static function of(Money $netCost, string $qty, string $openQty): Money
    {
        return CostSplitter::draw($netCost, $qty, $openQty);
    }
}
