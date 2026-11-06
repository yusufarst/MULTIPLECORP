<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;

/**
 * QS-04 (WORKFLOWS §10; IA §9.1): reservations cut, project demand flagged — the CUT events of the reservations of
 * QS-01's lines, read by `reservation_events (reservation_id)` —, the quantity cut, newest cut first; PG-011.
 */
final class QS04ReservationsCut extends LineSignal
{
    private const CUT = '(select abs(sum(e.qty_delta_base)) from reservations r join reservation_events e on e.reservation_id = r.id and e.kind = \'CUT\' where r.project_line_id = l.id)';

    private const LAST = '(select max(e.recorded_at) from reservations r join reservation_events e on e.reservation_id = r.id and e.kind = \'CUT\' where r.project_line_id = l.id)';

    public function code(): string
    {
        return 'QS-04';
    }

    protected function newestFirst(): bool
    {
        return true;
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::unreserved($company)->whereRaw('coalesce('.self::CUT.', 0) > 0')->orderByDesc('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(".self::CUT.')::text as value, u.code as unit, '.self::LAST.'::text as at, p.public_id::text as target');
    }

    protected function href(Context $context, object $row): ?string
    {
        return self::project($context, $row);
    }

    public function seeAll(Context $context): ?string
    {
        return $context->holding('projects.view') !== [] ? '/proyek' : null;
    }
}
