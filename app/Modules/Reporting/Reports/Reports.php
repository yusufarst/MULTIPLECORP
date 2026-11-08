<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Support\Money;
use Illuminate\Contracts\Container\Container;

/**
 * The report register (ARCHITECTURE §14; IA §8.6): PG-051's seven reports and PG-052's two, by their address keys, and
 * the one reading every reader shares — the page, its print view and the export (PF-28): each table's rows in its
 * sections, a `total` column summed per section and never across companies (CS-08; D-IA-12).
 */
final class Reports
{
    /** @var list<class-string<Report>> */
    public const PAGE = [StockReport::class, PurchaseReport::class, SalesReport::class, ReceivableReport::class, PaymentReport::class, ProfitReport::class, CashFlowReport::class];

    /** @var list<class-string<Report>> */
    public const CONSOLIDATED = [ConsolidatedReport::class, AllocationReport::class];

    public function __construct(private readonly Container $app) {}

    /**
     * The reports of a page the viewer may open, in order.
     *
     * @param  list<class-string<Report>>  $page
     * @return list<Report>
     */
    public function visible(Authority $viewer, array $page): array
    {
        $reports = [];

        foreach ($page as $class) {
            $report = $this->app->make($class);

            if ($report instanceof Report && $report->opens($viewer) !== []) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    /** A report by its address key, and whether it is PG-052's. */
    public function find(string $code): ?Report
    {
        foreach ([...self::PAGE, ...self::CONSOLIDATED] as $class) {
            $report = $this->app->make($class);

            if ($report->code() === $code) {
                return $report;
            }
        }

        return null;
    }

    public static function consolidated(Report $report): bool
    {
        return in_array($report::class, self::CONSOLIDATED, true);
    }

    /**
     * The report's tables read in one snapshot, as the page and its print view show them.
     *
     * @return list<array{key: string, columns: list<array{key: string, kind: string, total: bool}>, sections: list<array{company: ?string, name: ?string, rows: list<list<?string>>, total: ?list<?string>}>}>
     */
    public static function read(Report $report, Authority $viewer, Filters $filters): array
    {
        return Snapshot::read(function () use ($report, $viewer, $filters): array {
            $tables = [];

            foreach ($report->tables($viewer, $filters) as $table) {
                $sections = [];

                foreach ($table->sections ?? [null] as $company) {
                    $sections[$company ?? 0] = ['company' => $company === null ? null : $filters->code($company), 'name' => $company === null ? null : ($filters->companies[$company]['name'] ?? null), 'rows' => []];
                }

                foreach ($table->rows() as [$company, $cells]) {
                    if (isset($sections[$company ?? 0])) {
                        $sections[$company ?? 0]['rows'][] = $cells;
                    }
                }

                $tables[] = [
                    'key' => $table->key,
                    'columns' => array_map(fn (Column $column): array => $column->toArray(), $table->columns),
                    'sections' => array_values(array_map(fn (array $section): array => [...$section, 'total' => self::total($table->columns, $section['rows'])], $sections)),
                ];
            }

            return $tables;
        });
    }

    /**
     * A section's totals: each `total` column summed, the others empty; none for a section without rows.
     *
     * @param  list<Column>  $columns
     * @param  list<list<?string>>  $rows
     * @return ?list<?string>
     */
    public static function total(array $columns, array $rows): ?array
    {
        if ($rows === [] || ! in_array(true, array_map(fn (Column $column): bool => $column->total, $columns), true)) {
            return null;
        }

        $totals = [];

        foreach ($columns as $index => $column) {
            $totals[] = $column->total ? Money::sum(...array_map(fn (array $row): Money => Money::of($row[$index] ?? '0'), $rows))->toString() : null;
        }

        return $totals;
    }

    /**
     * The total of all a report's rows an export would write — its admission's bound (PF-28) —, read in one snapshot.
     */
    public static function count(Report $report, Authority $viewer, Filters $filters): int
    {
        return Snapshot::read(fn (): int => array_sum(array_map(fn (Table $table): int => $table->count(), $report->tables($viewer, $filters))));
    }
}
