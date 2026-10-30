<?php

namespace App\Support\Finance;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A receivable's age (BR-FIN-15; CALC-07; ARCHITECTURE §6): whole days past its due date between two business dates — WIB days already (BR-DT-06), so no
 * clock or zone enters here —, in the buckets current, 1–30, 31–60, 61–90 and >90; an opening receivable without a due date in the `migrasi` bucket
 * (BR-FIN-14). Pure, and the same buckets in SQL for a group over many rows (QB-07), held to one result by its unit test.
 */
final class AgingCalculator
{
    public const MIGRASI = 'MIGRASI';

    public const CURRENT = 'CURRENT';

    public const DAYS_1_30 = 'DAYS_1_30';

    public const DAYS_31_60 = 'DAYS_31_60';

    public const DAYS_61_90 = 'DAYS_61_90';

    public const OVER_90 = 'OVER_90';

    public const BUCKETS = [self::MIGRASI, self::CURRENT, self::DAYS_1_30, self::DAYS_31_60, self::DAYS_61_90, self::OVER_90];

    /** Whole days past the due date, zero on it and before it. */
    public static function daysPast(string $dueDate, string $today): int
    {
        return max(0, (int) self::day($dueDate)->diff(self::day($today))->format('%r%a'));
    }

    public static function bucket(?string $dueDate, string $today): string
    {
        if ($dueDate === null) {
            return self::MIGRASI;
        }

        $days = self::daysPast($dueDate, $today);

        return match (true) {
            $days === 0 => self::CURRENT,
            $days <= 30 => self::DAYS_1_30,
            $days <= 60 => self::DAYS_31_60,
            $days <= 90 => self::DAYS_61_90,
            default => self::OVER_90,
        };
    }

    /** The bucket of a date column as a SQL expression, for one group over the page's rows. */
    public static function bucketSql(string $column, string $today): string
    {
        if (preg_match('/^[a-z_]+(\.[a-z_]+)?$/', $column) !== 1) {
            throw new InvalidArgumentException('A bucket reads a named date column.');
        }

        $days = "(date '{$today}' - {$column})";
        self::day($today);

        return "case when {$column} is null then '".self::MIGRASI."' when {$days} <= 0 then '".self::CURRENT."' when {$days} <= 30 then '".self::DAYS_1_30."' when {$days} <= 60 then '".self::DAYS_31_60."' when {$days} <= 90 then '".self::DAYS_61_90."' else '".self::OVER_90."' end";
    }

    private static function day(string $day): DateTimeImmutable
    {
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC')) : false;

        return $parsed !== false ? $parsed : throw new InvalidArgumentException('A business date is Y-m-d.');
    }
}
