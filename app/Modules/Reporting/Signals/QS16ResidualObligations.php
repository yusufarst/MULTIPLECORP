<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-16 (WORKFLOWS §10; IA §9.1): residual obligations of force-completed or cancelled projects — the company's
 * COMPLETED_FORCED and CANCELLED projects on `projects (company_id, state, id)` (IX-14) joined to their open guards:
 * reservations still to release and, a money item read only with `finance.view` in the company, receivables still
 * outstanding (RD-03; RD-04; PJ-22). One set query whose cost follows the number of such projects (HO-16). The value
 * is the number of reservations still to release; the project on PG-011.
 */
final class QS16ResidualObligations extends Signal
{
    private const RESERVATIONS = "(select count(*) from project_lines l join reservations r on r.project_line_id = l.id and r.state = 'ACTIVE' where l.project_id = p.id and r.remaining_qty_base > 0)";

    private const RECEIVABLES = "exists (select 1 from invoices i join invoice_balances b on b.invoice_id = i.id where i.project_id = p.id and i.state = 'ISSUED' and b.outstanding > 0)";

    public function code(): string
    {
        return 'QS-16';
    }

    public function companies(Context $context): array
    {
        return $context->holding('projects.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        $money = $context->viewer->allows('finance.view', $company);

        return DB::table('projects as p')->where('p.company_id', $company)->whereIn('p.state', ['COMPLETED_FORCED', 'CANCELLED'])
            ->whereRaw($money ? '('.self::RESERVATIONS.' > 0 or '.self::RECEIVABLES.')' : self::RESERVATIONS.' > 0')->orderBy('p.id')
            ->selectRaw("'p' || p.id as key, p.company_id, p.project_number as subject, p.state as ref, ".self::RESERVATIONS.'::text as value, null::text as unit, p.recorded_at::text as at, p.public_id::text as target');
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
