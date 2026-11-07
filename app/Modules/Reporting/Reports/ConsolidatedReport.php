<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Modules\Reporting\Queries\PeriodFigures;

/**
 * PG-052's consolidated figures (RD-07; CALC-12; CS-08; PF-25; J-FIN-02; OD-14): the Owner's alone — each company's
 * period figures, CALC-08–CALC-14 each on its own business date (CALC-13), and *Gabungan*, their sums, with the
 * receivables outstanding as of today. *Beranda*'s own query class (PeriodFigures), so the two never disagree; six
 * statements in one snapshot.
 */
final class ConsolidatedReport implements Report
{
    public function __construct(private readonly PeriodFigures $figures) {}

    public function code(): string
    {
        return 'gabungan';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::Consolidated->admits($viewer) ? ['reports.consolidated'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $codes = array_map(fn (array $company): string => $company['code'], $filters->companies);
        $columns = [new Column('company', 'company'), new Column('revenue', 'money'), new Column('direct_cost', 'money'), new Column('project_profit', 'money'), new Column('profit', 'money'),
            new Column('cash_in', 'money'), new Column('cash_out', 'money'), new Column('receivable', 'money')];

        return [new Table('angka', $columns, null,
            function () use ($codes, $filters): iterable {
                $read = $this->figures->read($codes, $filters->period->from, $filters->period->to, $filters->today, true);
                $rows = [...$read['companies'], ...($read['consolidated'] === null ? [] : [$read['consolidated']])];

                foreach ($rows as $row) {
                    yield [null, [$row['company'], $row['revenue'], $row['direct_cost'], $row['project_profit'], $row['profit'], $row['cash_in'], $row['cash_out'], $row['receivable']['outstanding']]];
                }
            },
            fn (): int => count($codes) + 1)];
    }
}
