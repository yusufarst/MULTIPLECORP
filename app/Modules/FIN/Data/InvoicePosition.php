<?php

namespace App\Modules\FIN\Data;

use App\Support\Money;

/**
 * An invoice's shown state, derived each time from its record and guard, never stored (L-01; BR-FIN-02; DESIGN_SYSTEM §12.1): a draft or a void
 * as their record says; issued, *Belum Ditagihkan* until the billing act; then outstanding, past its due date or zero, a dispute hold and a
 * write-off shown for what they are.
 */
final class InvoicePosition
{
    public const DRAFT = 'DRAFT';

    public const VOIDED = 'VOIDED';

    public const UNBILLED = 'ISSUED_NOT_BILLED';

    public const BILLED = 'BILLED_OUTSTANDING';

    public const PAST_DUE = 'PAST_DUE_DATE';

    public const ZERO = 'OUTSTANDING_ZERO';

    public const HOLD = 'DISPUTE_HOLD';

    public const WRITTEN_OFF = 'WRITTEN_OFF';

    public static function of(string $state, bool $billed, ?string $dueDate, string $today, string $outstanding, string $writtenOff, bool $hold): string
    {
        if ($state === 'DRAFT') {
            return self::DRAFT;
        }

        if ($state === 'VOIDED') {
            return self::VOIDED;
        }

        if (! $billed) {
            return self::UNBILLED;
        }

        if (Money::of($outstanding)->isZero()) {
            return Money::of($writtenOff)->isPositive() ? self::WRITTEN_OFF : self::ZERO;
        }

        if ($hold) {
            return self::HOLD;
        }

        return $dueDate !== null && $dueDate < $today ? self::PAST_DUE : self::BILLED;
    }
}
