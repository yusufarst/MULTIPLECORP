<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * Managerial cash flow (RD-04; CALC-14; CALC-13; BR-FIN-01): per company and business date, Cash-In — payments and
 * other cash receipts — against Cash-Out — disbursements —, each net of its contras. A cost recognized creates no
 * Cash-Out; only an evidenced disbursement does, and it never counts a cost twice. One statement.
 */
final class CashFlowReport implements Report
{
    public function code(): string
    {
        return 'arus-kas';
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
        $period = [$filters->period->from, $filters->period->to];
        $facts = DB::table('payments as p')->whereIn('p.company_id', $companies)->whereBetween('p.business_date', $period)
            ->selectRaw('p.company_id, p.business_date, p.amount - coalesce((select sum(f.amount) from financial_contras f where f.payment_id = p.id), 0) as cash_in, 0 as cash_out')
            ->unionAll(DB::table('other_cash_receipts as r')->whereIn('r.company_id', $companies)->whereBetween('r.business_date', $period)
                ->selectRaw('r.company_id, r.business_date, r.amount - coalesce((select sum(f.amount) from financial_contras f where f.other_cash_receipt_id = r.id), 0), 0'))
            ->unionAll(DB::table('disbursements as d')->whereIn('d.company_id', $companies)->whereBetween('d.business_date', $period)
                ->selectRaw('d.company_id, d.business_date, 0, d.amount - coalesce((select sum(f.amount) from financial_contras f where f.disbursement_id = d.id), 0)'));
        $query = DB::query()->fromSub(DB::query()->fromSub($facts, 'c')->groupBy('c.company_id', 'c.business_date')
            ->selectRaw('c.company_id, c.business_date, sum(c.cash_in) as cash_in, sum(c.cash_out) as cash_out, sum(c.cash_in) - sum(c.cash_out) as net'), 'q')->select('q.*');

        return [new Table('arus-kas', [new Column('date', 'date'), new Column('cash_in', 'money', true), new Column('cash_out', 'money', true), new Column('net', 'money', true)], $companies,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['q.company_id' => 'company_id', 'q.business_date' => 'business_date']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'business_date'), Row::text($row, 'cash_in'), Row::text($row, 'cash_out'), Row::text($row, 'net')]];
                }
            },
            fn (): int => (clone $query)->count())];
    }
}
