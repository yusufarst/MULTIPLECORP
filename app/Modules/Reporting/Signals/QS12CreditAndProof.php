<?php

namespace App\Modules\Reporting\Signals;

use App\Modules\FIN\Queries\PaymentList;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-12 (WORKFLOWS §10; IA §9.1): unapplied customer credit — a credit linked to a written-off invoice included —,
 * payments flagged without proof and bank payments citing no statement line yet, and bank lines not fully cited, in
 * one statement per company: the source guard and the two payment lists on IX-14, the bank lines from the workspace's
 * open rows of `evidence_balances` before the company filter of the same statement (PF-24) — RD-04; oldest first;
 * the credit on PG-048, the payment on PG-045, the bank line on PG-050 for `bank_statement.record`, for another
 * viewer the bank-line row is text without a link.
 */
final class QS12CreditAndProof extends Signal
{
    public function code(): string
    {
        return 'QS-12';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        $credit = DB::table('application_source_balances as a')->join('client_organizations as o', 'o.id', '=', 'a.client_organization_id')
            ->where('a.company_id', $company)->where('a.capacity_amount', '>', 0)
            ->selectRaw("'c' || a.id as key, a.company_id, o.name as subject, 'credit' as ref, a.capacity_amount::text as value, null::text as unit, a.recorded_at::text as at, null::text as target");
        $payments = DB::table('payments as p')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('p.company_id', $company)
            ->whereRaw('(('.PaymentList::WITHOUT_PROOF.') or (p.company_bank_account_id is not null and p.bank_statement_line_id is null))')
            ->selectRaw("'p' || p.id as key, p.company_id, o.name as subject, 'payment' as ref, p.amount::text as value, null::text as unit, p.business_date::text as at, p.public_id::text as target");
        $lines = DB::table('evidence_balances as e')->join('bank_statement_lines as l', 'l.id', '=', 'e.bank_statement_line_id')
            ->whereColumn('e.cited_amount', '<', 'e.proven_amount')->where('l.state', 'ACTIVE')->where('l.company_id', $company)
            ->selectRaw("'b' || l.id as key, l.company_id, coalesce(l.line_reference, '') as subject, 'bank_line' as ref, (e.proven_amount - e.cited_amount)::text as value, null::text as unit, l.business_date::text as at, null::text as target");

        return DB::query()->fromSub($credit->unionAll($payments)->unionAll($lines), 'x')->orderBy('x.at')->select('x.*');
    }

    protected function href(Context $context, object $row): ?string
    {
        return match (Row::nullable($row, 'ref')) {
            'credit' => '/keuangan/kredit-pelanggan',
            'payment' => '/keuangan/pembayaran?tab=tanpa_bukti',
            default => $context->viewer->allows('bank_statement.record', self::companyOf($row)) ? '/keuangan/mutasi-bank' : null,
        };
    }

    public function seeAll(Context $context): string
    {
        return '/keuangan/pembayaran?tab=tanpa_bukti';
    }
}
