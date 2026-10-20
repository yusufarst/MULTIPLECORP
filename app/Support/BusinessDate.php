<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The business date of DATABASE §17 (BR-DT-05, BR-DT-06): a WIB calendar day, stored as `date`. The
 * application runs in UTC and records instants in UTC; a business date is always the day in WIB, so
 * 23:30 UTC is already tomorrow's business day.
 */
final class BusinessDate
{
    public const TIMEZONE = 'Asia/Jakarta';

    /** Today's business date, `Y-m-d`, from the application's clock. */
    public static function today(): string
    {
        return self::of(CarbonImmutable::now());
    }

    /** The business date of an instant, `Y-m-d`. */
    public static function of(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::TIMEZONE)->toDateString();
    }
}
