<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * A signal of the register (WORKFLOWS §10; PERFORMANCE §7, PF-24; INFORMATION_ARCHITECTURE §9.1): a projection over
 * facts and guards, never stored — no queue, badge, count or "read" mark (D-PF-04). Its statement is one: a bounded
 * branch for each company of its audience — the viewer's scope inside the page's view, narrowed by the signal's read
 * class (CS-07; PJ-13) —, or one branch for a pool signal, which carries no company. A branch reads BOUND + 1 rows on
 * the register's index or open root, so a company's count reads "20+" past the bound and is never summed with
 * another company's (CS-08; PF-15). A row names its record and opens the page of its next action for a viewer who
 * holds that action's capability, otherwise the record's view (INT-019).
 *
 * A branch selects, in one order for all its branches, `key`, `company_id`, `subject`, `ref`, `value`, `unit`, `at`
 * and `target`: the row's identity, its company (none for a pool signal), its record, a second label, its figure — a
 * plain decimal quantity with its base unit, an amount or a count —, the instant or date it is aged and ordered by,
 * and the public id its address names.
 */
abstract class Signal
{
    /** The rows a signal shows; the rest behind "Lihat Semua" (PT-21). */
    public const ROWS = 5;

    /** A company's count past which it reads "20+" (PF-15). */
    public const BOUND = 20;

    /** The register's code, e.g. `QS-01`. */
    abstract public function code(): string;

    /**
     * The companies whose rows the viewer reads; a pool signal answers [0] when the viewer reads it. [] leaves the
     * signal out of the page, with no heading, count or empty sentence (PT-19).
     *
     * @return list<int>
     */
    abstract public function companies(Context $context): array;

    /** One company's branch, ordered by the signal's own order; the read bounds it. */
    abstract protected function branch(Context $context, int $company): Builder;

    /** The row's address for this viewer (INT-019), or null for a row shown as text. */
    abstract protected function href(Context $context, object $row): ?string;

    /** The page behind "Lihat Semua" for this viewer, or null where the signal has none. */
    abstract public function seeAll(Context $context): ?string;

    /** Whether the newest row comes first; the oldest otherwise. */
    protected function newestFirst(): bool
    {
        return false;
    }

    /** The row's second label for this viewer; a signal hides a part the viewer may not read (PJ-13). */
    protected function ref(Context $context, object $row): ?string
    {
        return Row::nullable($row, 'ref');
    }

    /** The row's figure for this viewer; a signal drops a money item outside the viewer's family (PJ-22). */
    protected function value(Context $context, object $row): ?string
    {
        return Row::nullable($row, 'value');
    }

    /**
     * The signal's rows and bounded counts, in one statement; null when the viewer reads none of its companies.
     *
     * @return ?array{code: string, rows: list<array{id: string, company: ?string, subject: string, ref: ?string, value: ?string, unit: ?string, at: ?string, href: ?string}>, counts: list<array{company: string, count: string}>, more: ?string}
     */
    public function read(Context $context): ?array
    {
        $companies = $this->companies($context);

        if ($companies === []) {
            return null;
        }

        // Each branch keeps its own order and bound inside the union (PF-41), as ActivityHistory's branches do.
        $union = $this->branch($context, $companies[0])->limit(self::BOUND + 1);

        foreach (array_slice($companies, 1) as $company) {
            $union->unionAll($this->branch($context, $company)->limit(self::BOUND + 1));
        }

        $rows = $union->get()->all();
        $perCompany = [];

        foreach ($rows as $row) {
            $company = Row::nullable($row, 'company_id');
            $perCompany[$company ?? ''] = ($perCompany[$company ?? ''] ?? 0) + 1;
        }

        $counts = [];

        foreach ($perCompany as $company => $count) {
            $code = $company === '' ? null : $context->code((int) $company);
            $counts[] = ['company' => $code ?? '', 'count' => $count > self::BOUND ? self::BOUND.'+' : (string) $count];
        }

        usort($counts, fn (array $a, array $b): int => strcmp($a['company'], $b['company']));
        usort($rows, fn (object $a, object $b): int => $this->newestFirst()
            ? strcmp((string) Row::nullable($b, 'at'), (string) Row::nullable($a, 'at'))
            : strcmp((string) Row::nullable($a, 'at'), (string) Row::nullable($b, 'at')));

        return [
            'code' => $this->code(),
            'rows' => array_map(fn (object $row): array => [
                'id' => Row::text($row, 'key'),
                'company' => $context->code(Row::nullable($row, 'company_id') === null ? null : (int) Row::text($row, 'company_id')),
                'subject' => Row::text($row, 'subject'),
                'ref' => $this->ref($context, $row),
                'value' => $this->value($context, $row),
                'unit' => Row::nullable($row, 'unit'),
                'at' => Row::nullable($row, 'at'),
                'href' => $this->href($context, $row),
            ], array_slice($rows, 0, self::ROWS)),
            'counts' => $counts,
            'more' => $this->seeAll($context),
        ];
    }

    /** The row's company, as the branch selected it. */
    protected static function companyOf(object $row): int
    {
        return (int) Row::text($row, 'company_id');
    }
}
