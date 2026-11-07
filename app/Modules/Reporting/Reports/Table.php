<?php

namespace App\Modules\Reporting\Reports;

use Closure;

/**
 * One table of a report, read by the page, its print view and its export alike (ARCHITECTURE §14; PF-28): its columns,
 * its sections — one per company for a company's figures (D-IA-12; CS-08), or one pool section with no company for the
 * physical pool (RD-02; CS-05) —, its rows as a stream of keyed chunks, each tagged with its section's company and
 * ordered by section, and the bounded count of those rows an export's admission checks (PF-28).
 */
final class Table
{
    /**
     * @param  list<Column>  $columns
     * @param  ?list<int>  $sections  the companies of its sections, in order; null for the one pool section
     * @param  Closure(): iterable<array{0: ?int, 1: list<?string>}>  $rows
     * @param  Closure(): int  $count
     */
    public function __construct(
        public readonly string $key,
        public readonly array $columns,
        public readonly ?array $sections,
        public readonly Closure $rows,
        public readonly Closure $count,
    ) {}

    /** @return iterable<array{0: ?int, 1: list<?string>}> */
    public function rows(): iterable
    {
        return ($this->rows)();
    }

    public function count(): int
    {
        return ($this->count)();
    }
}
