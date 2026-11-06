<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-07 (WORKFLOWS §10; IA §9.1): open discrepancies and correction cases with their residuals — the company's OPEN
 * cases on the partial index `correction_cases_open (company_id, id)` (DATABASE §20) — RD-03, a case's money residual
 * — the refund shortfall and the RESIDUAL settlements assigned to it — only with `finance.view` in its company (RD-04;
 * PJ-22); oldest first; the case on PG-040.
 */
final class QS07OpenCases extends Signal
{
    public function code(): string
    {
        return 'QS-07';
    }

    public function companies(Context $context): array
    {
        return $context->holding('projects.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('correction_cases as k')->leftJoin('projects as p', 'p.id', '=', 'k.project_id')
            ->where('k.company_id', $company)->where('k.state', 'OPEN')->orderBy('k.id')
            ->selectRaw("'k' || k.id as key, k.company_id, k.case_kind as subject, p.project_number as ref, nullif(k.unbacked_refund_amount + k.residual_settlement_amount, 0)::text as value, null::text as unit, k.recorded_at::text as at, k.public_id::text as target");
    }

    /** The residual is a money item: without `finance.view` in the company it is not part of the row (PJ-22). */
    protected function value(Context $context, object $row): ?string
    {
        return $context->viewer->allows('finance.view', self::companyOf($row)) ? Row::nullable($row, 'value') : null;
    }

    protected function href(Context $context, object $row): string
    {
        return '/dokumen/kasus?kasus='.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/dokumen/kasus';
    }
}
