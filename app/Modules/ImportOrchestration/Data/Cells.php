<?php

namespace App\Modules\ImportOrchestration\Data;

use App\Modules\ImportOrchestration\Services\SourceTable;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * One source row's cells read as typed values (WF-MIG-01; FL-06): each reader returns the value or null and records,
 * for a value that cannot be read, the column and its reason in business words — `import.reason.*` — so the row is
 * surfaced with its number and every reason at once, never guessed at.
 */
final class Cells
{
    /** @var list<array{field: string, reason: string}> */
    public array $errors = [];

    /** @param  array<string, string>  $cells */
    public function __construct(public readonly array $cells) {}

    public function text(string $name, bool $required = false, int $maximum = 200): ?string
    {
        $value = $this->raw($name);

        if ($value === null) {
            return $required ? $this->fail($name, 'required') : null;
        }

        return mb_strlen($value) > $maximum ? $this->fail($name, 'too_long') : $value;
    }

    /** A decimal of at most `$scale` places: "1250000", "1250000.50" or "1.250.000,50" are read; a sign only where allowed. */
    public function decimal(string $name, int $scale, bool $required = false, bool $positive = false, bool $zero = true): ?string
    {
        $value = $this->raw($name);

        if ($value === null) {
            return $required ? $this->fail($name, 'required') : null;
        }

        $plain = self::plainNumber($value);

        try {
            $number = $plain === null ? null : BigDecimal::of($plain);
        } catch (MathException) {
            $number = null;
        }

        if ($number === null) {
            return $this->fail($name, 'number');
        }

        if ($number->strippedOfTrailingZeros()->getScale() > $scale) {
            return $this->fail($name, 'scale');
        }

        if (($positive && $number->isNegative()) || (! $zero && $number->isZero())) {
            return $this->fail($name, $number->isZero() ? 'zero' : 'negative');
        }

        return (string) $number->toScale(max(0, $scale));
    }

    public function integer(string $name, bool $required = false, int $minimum = 0): ?int
    {
        $value = $this->decimal($name, 0, $required);

        if ($value === null) {
            return null;
        }

        return (int) $value < $minimum ? $this->fail($name, 'negative') : (int) $value;
    }

    /** A date as `YYYY-MM-DD`, `DD/MM/YYYY` or a spreadsheet's day number. */
    public function date(string $name, bool $required = false): ?string
    {
        $value = $this->raw($name);

        if ($value === null) {
            return $required ? $this->fail($name, 'required') : null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            } elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value) === 1) {
                $date = CarbonImmutable::createFromFormat('!d/m/Y', $value);
            } elseif (preg_match('/^\d{5}$/', $value) === 1) {
                $date = CarbonImmutable::create(1899, 12, 30)?->addDays((int) $value);
            } else {
                $date = null;
            }
        } catch (Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable) {
            return $this->fail($name, 'date');
        }

        $text = $date->format('Y-m-d');

        // A date the calendar does not hold — 2026-02-31 — rolls over in the parser; it is refused, never moved.
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && $text !== $value ? $this->fail($name, 'date') : $text;
    }

    /**
     * One of the allowed words, matched without case; `$map` maps the words a person writes to the stored code.
     *
     * @param  array<string, string>  $map
     */
    public function choice(string $name, array $map, bool $required = false, ?string $default = null): ?string
    {
        $value = $this->raw($name);

        if ($value === null) {
            return $default ?? ($required ? $this->fail($name, 'required') : null);
        }

        return $map[mb_strtolower($value)] ?? $this->fail($name, 'choice');
    }

    public function flag(string $name): bool
    {
        return in_array(mb_strtolower($this->raw($name) ?? ''), ['ya', 'y', 'yes', 'true', '1'], true);
    }

    /** A cell's value, null when empty; a formula is refused, never evaluated (FL-06). */
    public function raw(string $name): ?string
    {
        $value = $this->cells[$name] ?? '';

        if ($value === SourceTable::FORMULA) {
            $this->fail($name, 'formula');

            return null;
        }

        return $value === '' ? null : $value;
    }

    /** Values beyond the header's columns and formulas anywhere refuse the row. */
    public function checkShape(): void
    {
        foreach ($this->cells as $name => $value) {
            if (str_starts_with($name, '#')) {
                $this->fail('', 'extra_column');

                return;
            }

            if ($value === SourceTable::FORMULA) {
                $this->fail($name, 'formula');
            }
        }
    }

    public function fail(string $field, string $reason): null
    {
        foreach ($this->errors as $error) {
            if ($error['field'] === $field && $error['reason'] === $reason) {
                return null;
            }
        }

        $this->errors[] = ['field' => $field, 'reason' => $reason];

        return null;
    }

    /** "1.250.000,50", "250.000", "12,5", "1,250,000.50" or "1250000.50" as a plain decimal; anything else null. */
    public static function plainNumber(string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", 'Rp', 'rp'], '', $value);

        // The Indonesian convention of a source typed by a person: a dot groups thousands, a comma marks the decimals (XLSX
        // numbers arrive with a comma, SourceTable). "250.000" is two hundred and fifty thousand, never 250.
        if (preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+(,\d+)?$/', $value) === 1 || preg_match('/^-?\d+,\d+$/', $value) === 1) {
            return str_replace(['.', ','], ['', '.'], $value);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $value) === 1) {
            return str_replace(',', '', $value);
        }

        return null;
    }
}
