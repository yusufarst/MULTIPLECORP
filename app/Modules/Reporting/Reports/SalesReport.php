<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The sales report (RD-04; CALC-08; CALC-13; BR-FIN-01; BR-FIN-17; REF-016): each company's issued, non-void LIVE
 * invoices whose current version's business date falls in the period — the sales value, never cash, the output tax
 * shown as recorded beside it and never revenue —; an opening receivable moves no live-period revenue (BR-XD-06). The
 * company's invoices joined to their current versions (PERFORMANCE §12's sales-path candidate). One statement.
 */
final class SalesReport implements Report
{
    /** An invoice's number: its Invoice or Faktur document's, the issued one first (PG-041's own). */
    public const NUMBER = "(select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number";

    public function code(): string
    {
        return 'penjualan';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::SalesReceivablesCash->admits($viewer) ? ['finance.view'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $companies = $filters->companyIds();
        $query = DB::table('invoices as i')->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')
            ->leftJoin('client_organizations as o', 'o.id', '=', 'i.client_organization_id')->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->whereIn('i.company_id', $companies)->where('i.state', 'ISSUED')->where('i.origin', 'LIVE')->whereBetween('v.business_date', [$filters->period->from, $filters->period->to])
            ->select(['i.id', 'i.company_id', 'v.business_date', 'o.name as client', 'p.project_number', 'v.sales_value', 'v.output_tax_value', 'v.gross_value', DB::raw(self::NUMBER)]);

        return [new Table('penjualan', [new Column('number'), new Column('date', 'date'), new Column('client'), new Column('project'),
            new Column('sales_value', 'money', true), new Column('output_tax', 'money', true), new Column('gross', 'money', true)], $companies,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['i.company_id' => 'company_id', 'v.business_date' => 'business_date', 'i.id' => 'id']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::nullable($row, 'number'), Row::text($row, 'business_date'), Row::nullable($row, 'client'), Row::nullable($row, 'project_number'),
                        Row::text($row, 'sales_value'), Row::text($row, 'output_tax_value'), Row::text($row, 'gross_value')]];
                }
            },
            fn (): int => (clone $query)->count())];
    }
}
