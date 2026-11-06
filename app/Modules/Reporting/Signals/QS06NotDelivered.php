<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-06 (WORKFLOWS §10; IA §9.1): dispatched or shipped, not delivered — the lines of the company's ACTIVE projects
 * whose guard shows a dispatched or confirmed quantity not yet delivered (the second arm of `ix14_open`) — RD-03;
 * the line's latest shipment on PG-031 for `delivery.record`, otherwise PG-030.
 */
final class QS06NotDelivered extends Signal
{
    private const UNDELIVERED = '(b.dispatched_net_qty_base + b.dropship_confirmed_net_qty_base - b.delivered_net_qty_base)';

    private const SHIPMENT = "(select m.public_id from dispatch_lines d join stock_movements m on m.id = d.stock_movement_id where d.project_line_id = l.id and m.movement_type = 'DISPATCH' order by m.id desc limit 1)";

    public function code(): string
    {
        return 'QS-06';
    }

    public function companies(Context $context): array
    {
        return $context->holding('projects.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::active($company)->whereRaw(self::UNDELIVERED.' > 0')->orderBy('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(".self::UNDELIVERED.')::text as value, u.code as unit, l.recorded_at::text as at, '.self::SHIPMENT.'::text as target');
    }

    protected function href(Context $context, object $row): string
    {
        $shipment = Row::nullable($row, 'target');

        return $shipment !== null && $context->viewer->allows('delivery.record', self::companyOf($row)) ? '/gudang/pengiriman/'.$shipment : '/gudang/pengiriman';
    }

    public function seeAll(Context $context): string
    {
        return '/gudang/pengiriman';
    }
}
