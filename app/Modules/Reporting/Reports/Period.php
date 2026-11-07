<?php

namespace App\Modules\Reporting\Reports;

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;

/**
 * A report's period (GR-19; PF-27; CALC-13): a preset bound to the repository's periods — this month, last month, this
 * quarter and this year, each a run of whole calendar months in WIB — or a custom range of business dates, both
 * whitelisted address keys (`periode`, `dari`, `sampai`). An unknown preset or an unreadable range answers as this
 * month, a range is put in order and bounded to one year, so no request text reaches a statement but a parsed date.
 */
final class Period
{
    public const PRESETS = ['bulan-ini', 'bulan-lalu', 'kuartal-ini', 'tahun-ini', 'rentang'];

    public const DEFAULT = 'bulan-ini';

    /** The longest custom range, in days: a year and a day, so a leap year's whole year fits. */
    public const MAXIMUM_DAYS = 366;

    private function __construct(public readonly string $preset, public readonly string $from, public readonly string $to) {}

    public static function of(?string $preset, ?string $from, ?string $to, ?string $today = null): self
    {
        $day = CarbonImmutable::parse($today ?? BusinessDate::today());
        $preset = in_array($preset, self::PRESETS, true) ? $preset : self::DEFAULT;

        if ($preset === 'rentang') {
            $start = self::date($from);
            $end = self::date($to);

            if ($start === null || $end === null) {
                return self::of(self::DEFAULT, null, null, $day->toDateString());
            }

            [$start, $end] = $start->greaterThan($end) ? [$end, $start] : [$start, $end];

            if ($start->diffInDays($end) >= self::MAXIMUM_DAYS) {
                $start = $end->subDays(self::MAXIMUM_DAYS - 1);
            }

            return new self('rentang', $start->toDateString(), $end->toDateString());
        }

        return match ($preset) {
            'bulan-lalu' => new self($preset, $day->subMonthNoOverflow()->startOfMonth()->toDateString(), $day->subMonthNoOverflow()->endOfMonth()->toDateString()),
            'kuartal-ini' => new self($preset, $day->startOfQuarter()->toDateString(), $day->endOfQuarter()->toDateString()),
            'tahun-ini' => new self($preset, $day->startOfYear()->toDateString(), $day->endOfYear()->toDateString()),
            default => new self(self::DEFAULT, $day->startOfMonth()->toDateString(), $day->endOfMonth()->toDateString()),
        };
    }

    /** @return array{periode: string, dari: string, sampai: string} */
    public function keys(): array
    {
        return ['periode' => $this->preset, 'dari' => $this->from, 'sampai' => $this->to];
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->toDateString() === $value ? $date : null;
    }
}
