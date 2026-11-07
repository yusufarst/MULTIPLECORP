<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-23 (`DIR-077` CA-10; IA §9.1): overdue projects — the company's ACTIVE projects whose one governing deadline is
 * before today at the WIB day boundary (BR-DT-06), on DATABASE §20's partial index `projects (company_id, deadline)
 * WHERE state IN ('DRAFT', 'ACTIVE')`, one branch per company in scope (PF-41); DRAFT, CANCELLED, COMPLETED_NORMAL,
 * COMPLETED_FORCED and projects without a deadline excluded. The Owner's alone (RD-09; PJ-13): an Admin sees the
 * deadline as the project's fact only. The value is the days past the deadline; the oldest deadline first; PG-011.
 */
final class QS23OverdueProjects extends Signal
{
    public function code(): string
    {
        return 'QS-23';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('review.view') ? array_keys($context->companies) : [];
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('projects as p')->where('p.company_id', $company)->where('p.state', 'ACTIVE')->whereNotNull('p.deadline')->where('p.deadline', '<', $context->today)
            ->orderBy('p.deadline')->orderBy('p.id')
            ->selectRaw("'p' || p.id as key, p.company_id, p.project_number as subject, p.title as ref, (cast(? as date) - p.deadline)::text as value, null::text as unit, p.deadline::text as at, p.public_id::text as target", [$context->today]);
    }

    protected function href(Context $context, object $row): string
    {
        return '/proyek/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/proyek';
    }
}
