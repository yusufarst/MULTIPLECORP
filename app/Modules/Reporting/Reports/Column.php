<?php

namespace App\Modules\Reporting\Reports;

/**
 * One column of a report table: its key — the interface's `pages.pg_051.col.{key}` and the export's Indonesian header
 * (LOCALIZATION §3.4) — and its kind: `text` and `date` as written, `label` a catalogue key the reader translates,
 * `company` a company's code or, empty, *Gabungan* (CALC-12),
 * `qty`, `money` and `percent` numbers, written to an export as typed numeric cells (FL-09). A `total` column is summed
 * per section, never across companies (CS-08).
 */
final class Column
{
    public const KINDS = ['text', 'label', 'company', 'date', 'qty', 'money', 'percent'];

    public function __construct(public readonly string $key, public readonly string $kind = 'text', public readonly bool $total = false) {}

    public function numeric(): bool
    {
        return in_array($this->kind, ['qty', 'money', 'percent'], true);
    }

    /** @return array{key: string, kind: string, total: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'kind' => $this->kind, 'total' => $this->total];
    }
}
