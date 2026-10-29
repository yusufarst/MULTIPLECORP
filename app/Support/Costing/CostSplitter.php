<?php

namespace App\Support\Costing;

use App\Support\Money;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * The residual-absorbing Rp1 splits of costing (ARCHITECTURE §6; BR-FIN-13; GAP-004; DATABASE §6.1–§6.3), pure
 * functions over exact decimals: a draw of part of a lot, a cumulative target of part of a line, a total split over
 * weights with the last taking the residual, and an amount spread over quantities by cumulative rounding. The share
 * that zeroes a whole always takes exactly what is left, so the parts sum to the whole to the rupiah.
 */
final class CostSplitter
{
    /**
     * A draw of `qty` units from a lot whose remaining quantity is `remainingQty` and remaining cost `remaining`: the
     * whole remaining cost when the draw zeroes the lot, else its share rounded to Rp1 (DATABASE §6.2; annex 10).
     */
    public static function draw(Money $remaining, string $qty, string $remainingQty): Money
    {
        self::part($qty, $remainingQty);

        return BigDecimal::of($qty)->isEqualTo($remainingQty) ? $remaining : $remaining->share($qty, $remainingQty);
    }

    /**
     * The cumulative target of a value for `done` of `whole` units: exactly the value once done reaches the whole,
     * else its share — a receipt posts its target less what is already posted, so partial receipts sum to the value
     * (DATABASE §6.1; C-57).
     */
    public static function target(Money $value, string $done, string $whole): Money
    {
        self::part($done, $whole);

        return BigDecimal::of($done)->isEqualTo($whole) ? $value : $value->share($done, $whole);
    }

    /**
     * A total split over the weights in their order, each part rounded to Rp1 and the last absorbing the residual —
     * a charge over its lines' basis (annex 14; DATABASE §6.1). A part never takes more than the total leaves, so parts
     * rounded up never push a later one past zero: every part keeps the total's sign.
     *
     * @template K of array-key
     *
     * @param  array<K, string>  $weights  non-negative, at least one above zero
     * @return array<K, Money>
     */
    public static function split(Money $total, array $weights): array
    {
        $whole = self::whole($weights);
        $parts = [];
        $given = Money::zero();
        $last = array_key_last($weights);

        foreach ($weights as $key => $weight) {
            $left = $total->minus($given);
            $share = $total->share($weight, $whole);
            $parts[$key] = $key === $last ? $left : ($total->isNegative() ? Money::max($share, $left) : Money::min($share, $left));
            $given = $given->plus($parts[$key]);
        }

        return $parts;
    }

    /**
     * An amount spread over the quantities in their order by cumulative rounding: each part is the rounded share of
     * the quantities so far less what the earlier parts took, so the last part ends the amount exactly and a zero
     * quantity takes nothing (DATABASE §6.3 step 1).
     *
     * @template K of array-key
     *
     * @param  array<K, string>  $quantities  non-negative, at least one above zero
     * @return array<K, Money>
     */
    public static function spread(Money $amount, array $quantities): array
    {
        $whole = self::whole($quantities);
        $parts = [];
        $done = BigDecimal::zero();
        $given = Money::zero();

        foreach ($quantities as $key => $quantity) {
            $done = $done->plus($quantity);
            $target = self::target($amount, (string) $done, $whole);
            $parts[$key] = $target->minus($given);
            $given = $target;
        }

        return $parts;
    }

    /** @param  array<array-key, string>  $weights */
    private static function whole(array $weights): string
    {
        $whole = BigDecimal::zero();

        foreach ($weights as $weight) {
            if (BigDecimal::of($weight)->isNegative()) {
                throw new InvalidArgumentException('A weight is never negative.');
            }

            $whole = $whole->plus($weight);
        }

        if (! $whole->isPositive()) {
            throw new InvalidArgumentException('A split needs a whole above zero.');
        }

        return (string) $whole;
    }

    private static function part(string $part, string $whole): void
    {
        $part = BigDecimal::of($part);

        if (! BigDecimal::of($whole)->isPositive() || $part->isNegative() || $part->isGreaterThan($whole)) {
            throw new InvalidArgumentException("A part {$part} is taken of a whole above zero it does not exceed ({$whole}).");
        }
    }
}
