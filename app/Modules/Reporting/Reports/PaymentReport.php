<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The payment report (RD-04; CALC-14's Cash-In; CALC-13; BR-FIN-17): each company's payments of the period by payment
 * date — the amount received, its contras and the net, the method and reference as recorded. One statement.
 */
final class PaymentReport implements Report
{
    private const CONTRA = 'coalesce((select sum(f.amount) from financial_contras f where f.payment_id = p.id), 0)';

    public function code(): string
    {
        return 'pembayaran';
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
        $query = DB::table('payments as p')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->whereIn('p.company_id', $companies)->whereBetween('p.business_date', [$filters->period->from, $filters->period->to])
            ->select(['p.id', 'p.company_id', 'p.business_date', 'o.name as client', 'p.method', 'p.reference', 'p.amount', DB::raw(self::CONTRA.' as contra'), DB::raw('p.amount - '.self::CONTRA.' as net')]);

        return [new Table('pembayaran', [new Column('date', 'date'), new Column('client'), new Column('method', 'label'), new Column('reference'),
            new Column('amount', 'money', true), new Column('contra', 'money', true), new Column('net', 'money', true)], $companies,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['p.company_id' => 'company_id', 'p.business_date' => 'business_date', 'p.id' => 'id']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'business_date'), Row::nullable($row, 'client'), 'payments.method.'.Row::text($row, 'method'), Row::nullable($row, 'reference'),
                        Row::text($row, 'amount'), Row::text($row, 'contra'), Row::text($row, 'net')]];
                }
            },
            fn (): int => (clone $query)->count())];
    }
}
