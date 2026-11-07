<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-13 (WORKFLOWS §10; IA §9.1): claimed remittance deductions not yet evidenced — a source guard whose settled
 * amount is under its claimed deductions (claimed − evidenced; C-22), on IX-14's partial index of open sources —
 * RD-04; oldest first; PG-047 for `settlement.record`, otherwise the payments of PG-045.
 */
final class QS13DeductionsUnevidenced extends Signal
{
    public function code(): string
    {
        return 'QS-13';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('application_source_balances as a')->join('client_organizations as o', 'o.id', '=', 'a.client_organization_id')
            ->where('a.company_id', $company)->whereColumn('a.settled_amount', '<', 'a.claimed_deductions_amount')->orderBy('a.id')
            ->selectRaw("'a' || a.id as key, a.company_id, o.name as subject, null::text as ref, (a.claimed_deductions_amount - a.settled_amount)::text as value, null::text as unit, a.recorded_at::text as at, null::text as target");
    }

    protected function href(Context $context, object $row): string
    {
        return $context->viewer->allows('settlement.record', self::companyOf($row)) ? '/keuangan/potongan' : '/keuangan/pembayaran';
    }

    public function seeAll(Context $context): string
    {
        return $context->holding('settlement.record') !== [] ? '/keuangan/potongan' : '/keuangan/pembayaran';
    }
}
