<?php

namespace App\Modules\OPS\Queries;

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The UTC bounds of a WIB business day for the activity and security log filters (DATABASE §17; GAP-016):
 * a day starts at 00:00 WIB, 17:00 UTC of the day before.
 */
final class BusinessDay
{
    public static function start(string $day): CarbonImmutable
    {
        return self::parse($day)->utc();
    }

    /** The first instant after the day. */
    public static function end(string $day): CarbonImmutable
    {
        return self::parse($day)->addDay()->utc();
    }

    private static function parse(string $day): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            throw new InvalidArgumentException('A business day is a Y-m-d day.');
        }

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, BusinessDate::TIMEZONE);

        if ($start === null || $start->toDateString() !== $day) {
            throw new InvalidArgumentException('A business day is a Y-m-d day.');
        }

        return $start;
    }
}
