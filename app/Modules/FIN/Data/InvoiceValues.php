<?php

namespace App\Modules\FIN\Data;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The invoice's arithmetic (L-16; CALC-05; CALC-08; BR-FIN-13; BR-FIN-17): the value left under the project's cap, the output tax
 * kept apart from the sales value, a proposed tax amount by its configured rate, base fraction and rounding (FS-13: configuration,
 * never a hard-coded rate), and a value-only termin's share of the confirmed tax, the last termin absorbing the residual so the
 * termins sum to it exactly.
 */
final class InvoiceValues
{
    /** What the confirmed value leaves for this invoice (L-16; C-31), never below zero. */
    public static function remaining(string $confirmed, string $invoiced): string
    {
        return Money::max(Money::zero(), Money::of($confirmed)->minus(Money::of($invoiced)))->toString();
    }

    /** Whether Σ non-void sales values stay within the confirmed value with this one, net of the one it replaces. */
    public static function withinCap(string $confirmed, string $invoiced, string $sales, string $released = '0.00'): bool
    {
        return Money::of($invoiced)->minus(Money::of($released))->plus(Money::of($sales))->compareTo(Money::of($confirmed)) <= 0;
    }

    /** The billed sum: the sales value and, apart from it, the output tax (BR-FIN-17). */
    public static function gross(string $sales, string $tax): string
    {
        return Money::of($sales)->plus(Money::of($tax))->toString();
    }

    /**
     * `base × rate% × numerator ÷ denominator`, rounded by the configured mode to the sen. A proposal only: the stored,
     * user-confirmed amount matching the tax document is the truth (DATABASE §12).
     */
    public static function proposedTax(string $base, string $ratePercent, string $numerator, string $denominator, string $rounding): string
    {
        $mode = $rounding === 'DOWN' ? RoundingMode::Down : RoundingMode::HalfUp;
        $amount = BigDecimal::of($base)->multipliedBy($ratePercent)->multipliedBy($numerator)->dividedBy(BigDecimal::of($denominator)->multipliedBy(100), 2, $mode);

        return Money::of((string) $amount)->toString();
    }

    /**
     * A value-only termin's share of the confirmed tax (BR-FIN-13): in proportion to its value, and the one that takes the last
     * of the confirmed value takes the rest of the tax, so Σ termins' tax = the confirmed tax exactly.
     */
    public static function terminTax(string $confirmedValue, string $confirmedTax, string $invoicedValue, string $invoicedTax, string $value): string
    {
        $left = Money::of($confirmedValue)->minus(Money::of($invoicedValue));

        if ($left->isEqualTo(Money::of($value))) {
            return Money::max(Money::zero(), Money::of($confirmedTax)->minus(Money::of($invoicedTax)))->toString();
        }

        return Money::of($confirmedTax)->share(self::cents($value), self::cents($confirmedValue))->toString();
    }

    private static function cents(string $amount): string
    {
        return (string) BigDecimal::of($amount)->multipliedBy(100)->toScale(0, RoundingMode::Down);
    }
}
