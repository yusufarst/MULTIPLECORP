<?php

namespace App\Modules\ORG\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A numbering period (DATABASE §17; NM-03): `YYYY` under a YEAR scheme, `YYYY-MM` under a MONTH one, taken from a
 * WIB business date (BR-DT-06; GAP-016) — a YEAR key and a MONTH key never coincide —, with its first and last day.
 */
final class NumberingPeriod
{
    public const GRANULARITIES = ['YEAR', 'MONTH'];

    private function __construct(public readonly string $key, public readonly string $granularity) {}

    /** The period a business day falls into under a granularity. */
    public static function of(string $day, string $granularity): self
    {
        $date = self::day($day);

        return match ($granularity) {
            'YEAR' => new self($date->format('Y'), 'YEAR'),
            'MONTH' => new self($date->format('Y-m'), 'MONTH'),
            default => throw new InvalidArgumentException("{$granularity} is not a period granularity."),
        };
    }

    public static function fromKey(string $key): self
    {
        return match (true) {
            preg_match('/^\d{4}$/', $key) === 1 => new self($key, 'YEAR'),
            preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key) === 1 => new self($key, 'MONTH'),
            default => throw new InvalidArgumentException("{$key} is not a period key (YYYY or YYYY-MM)."),
        };
    }

    public static function isKey(string $key): bool
    {
        return preg_match('/^\d{4}(-(0[1-9]|1[0-2]))?$/', $key) === 1;
    }

    /** The first day, `Y-m-d`. */
    public function firstDay(): string
    {
        return $this->granularity === 'YEAR' ? "{$this->key}-01-01" : "{$this->key}-01";
    }

    /** The last day, `Y-m-d`. */
    public function lastDay(): string
    {
        return $this->granularity === 'YEAR' ? "{$this->key}-12-31" : self::day($this->firstDay())->endOfMonth()->toDateString();
    }

    public static function day(string $day): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $day, 'UTC');

        if ($date === null || $date->toDateString() !== $day) {
            throw new InvalidArgumentException("{$day} is not a Y-m-d day.");
        }

        return $date;
    }
}
