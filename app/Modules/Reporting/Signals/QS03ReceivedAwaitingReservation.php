<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;

/**
 * QS-03 (WORKFLOWS §10; IA §9.1): received goods for a project awaiting reservation — QS-01's lines whose linked
 * purchase lines (`purchase_lines.project_line_id`) show a received quantity on their guard —, the quantity received
 * and not yet reserved; PG-025 "Reservasi" for `stock.reserve`, otherwise the line on PG-011.
 */
final class QS03ReceivedAwaitingReservation extends LineSignal
{
    private const RECEIVED = '(select sum(g.normal_received_net_qty_base) from purchase_lines pl join purchase_line_balances g on g.purchase_line_id = pl.id where pl.project_line_id = l.id)';

    public function code(): string
    {
        return 'QS-03';
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::unreserved($company)->whereRaw('coalesce('.self::RECEIVED.', 0) > 0')->orderBy('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(least(".self::RECEIVED.', '.ProjectLines::UNRESERVED.'))::text as value, u.code as unit, l.recorded_at::text as at, p.public_id::text as target');
    }

    protected function href(Context $context, object $row): ?string
    {
        return $context->viewer->allows('stock.reserve', self::companyOf($row)) ? '/gudang/reservasi' : self::project($context, $row);
    }

    public function seeAll(Context $context): ?string
    {
        return $context->holding('stock.reserve') !== [] ? '/gudang/reservasi' : null;
    }
}
