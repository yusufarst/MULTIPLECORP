<?php

namespace App\Support\Costing;

use App\Support\Money;

/**
 * SF-CHARGE's arithmetic (ARCHITECTURE §6; DATABASE §6.1; AX-29; L-47): an attributable charge split over its
 * purchase lines on their basis, the last line in line order absorbing the residual, and each line's share
 * allocated over the ordered quantity by cumulative rounding — a receipt posts its share's target less what is
 * posted, never more than what is still pending on the line, so allocated + pending + expensed is the charge at every
 * step and a share already re-spread or expensed is never posted again.
 */
final class ChargeAllocator
{
    /**
     * The charge's share of each line, by the lines' basis values in line order (annex 14).
     *
     * @template K of array-key
     *
     * @param  array<K, string>  $basis
     * @return array<K, Money>
     */
    public static function shares(Money $charge, array $basis): array
    {
        return CostSplitter::split($charge, $basis);
    }

    /** What is still pending on a line: its share less what is posted, re-spread out and expensed out. */
    public static function pending(Money $share, Money $posted, Money $respreadOut, Money $expensedOut): Money
    {
        return $share->minus($posted)->minus($respreadOut)->minus($expensedOut);
    }

    /**
     * What a receipt posts of a line's share once `received` of its `ordered` units are received: the share's
     * cumulative target less what is posted, at least nothing and at most what is pending.
     */
    public static function posting(Money $share, string $received, string $ordered, Money $posted, Money $respreadOut, Money $expensedOut): Money
    {
        $due = CostSplitter::target($share, $received, $ordered)->minus($posted);

        return Money::max(Money::zero(), Money::min($due, self::pending($share, $posted, $respreadOut, $expensedOut)));
    }
}
