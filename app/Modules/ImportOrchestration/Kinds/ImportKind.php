<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Support\Command\Outcome;
use Closure;

/**
 * One import kind of RELEASE_PLAN IMP-01 (WF-MIG-01): its source layout, read into a normalized payload; the steps
 * that commit a row through its module's ordinary actions, each under the row's deterministic identity; and what it
 * adds to the control totals of its company. One class per kind, no generic ETL (TASK-057).
 */
interface ImportKind
{
    /** The batch kind as the batch and its rows record it. */
    public function code(): string;

    /** Whether consecutive rows sharing an identity are one record — a project's or a purchase's lines. */
    public function grouped(): bool;

    /** Whether each row names its company, every fact it creates belonging to it (CS-03). */
    public function companyBound(): bool;

    /**
     * The row's payload; its errors are recorded on the cells.
     *
     * @param  list<Cells>  $lines  the record's rows, the first one included
     * @return array<string, mixed>
     */
    public function normalize(Cells $head, array $lines): array;

    /**
     * The row's steps, in order, each one ordinary action under its own derived identity; the last one commits the row.
     *
     * @return non-empty-list<Closure(string, int, list<Outcome>): Outcome>
     */
    public function steps(ImportRowData $row): array;

    /**
     * What the row adds to its company's control totals (IMP-04; IMP-11).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function totals(array $payload): array;
}
