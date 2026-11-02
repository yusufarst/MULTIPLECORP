<?php

namespace App\Modules\PUR\Data;

use App\Support\Money;

/**
 * A purchase return's figures, pure (WF-PUR-03; NX-16; C-24; C-46): the part of its returned cost still open — returned − refunded − replaced − unrecovered, zero when the case may close — and an amount spread over its consumptions in order, each never above what it carries.
 */
final class ReturnSettlement
{
    /** What the returned cost still awaits; the case closes only at zero (C-46). */
    public static function open(Money $returned, Money $refunded, Money $replaced, Money $unrecovered): Money
    {
        return $returned->minus($refunded)->minus($replaced)->minus($unrecovered);
    }

    /**
     * The amount spread over the consumptions in the order given, each its own part up to what it carries; null when they carry less than the amount (C-24).
     *
     * @param  array<int, Money>  $carried  consumption => what it carries
     * @return array<int, Money>|null consumption => its part, the ones with none left out
     */
    public static function split(Money $amount, array $carried): ?array
    {
        $left = $amount;
        $parts = [];

        foreach ($carried as $consumption => $carries) {
            $part = Money::min($carries, $left);

            if ($part->isPositive()) {
                $parts[$consumption] = $part;
                $left = $left->minus($part);
            }
        }

        return $left->isPositive() ? null : $parts;
    }
}
