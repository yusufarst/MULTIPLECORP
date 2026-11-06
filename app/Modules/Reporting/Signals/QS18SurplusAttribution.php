<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-18's attribution (WORKFLOWS §10; IA §9.1): the unattributed surplus awaiting the Owner's attribution — the OPEN
 * count findings with a surplus, on C-34's partial UNIQUE key — a pool signal for the Owner alone (RD-09:
 * `review.view` or `stock.attribute_surplus`; PJ-13); oldest first; PG-029.
 */
final class QS18SurplusAttribution extends Signal
{
    public function code(): string
    {
        return 'QS-18';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('review.view') || $context->viewer->has('stock.attribute_surplus') ? [0] : [];
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('stock_count_findings as f')->join('products as pr', 'pr.id', '=', 'f.product_id')->join('units as u', 'u.id', '=', 'pr.base_unit_id')
            ->where('f.state', 'OPEN')->where('f.surplus_qty_base', '>', 0)->orderBy('f.id')
            ->selectRaw("'f' || f.id as key, null::bigint as company_id, pr.name as subject, null::text as ref, trim_scale(f.surplus_qty_base)::text as value, u.code as unit, f.recorded_at::text as at, pr.public_id::text as target");
    }

    protected function href(Context $context, object $row): string
    {
        return '/gudang/selisih-temuan';
    }

    public function seeAll(Context $context): string
    {
        return '/gudang/selisih-temuan';
    }
}
