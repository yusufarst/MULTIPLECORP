<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-18's findings and stale counts (WORKFLOWS §10; IA §9.1) — a pool signal read once with `stock.view` (RD-02):
 * the OPEN count findings on C-34's partial UNIQUE key and the STALE counts, a set that only grows (HO-16), in one
 * statement; oldest first; PG-026 for `stock.count` or `stock.count_apply`, otherwise a finding's product on PG-019.
 * The attribution of an unattributed surplus is the Owner's part of QS-18 (`QS18SurplusAttribution`).
 */
final class QS18CountFindings extends Signal
{
    public function code(): string
    {
        return 'QS-18';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('stock.view') ? [0] : [];
    }

    protected function branch(Context $context, int $company): Builder
    {
        $findings = DB::table('stock_count_findings as f')->join('products as pr', 'pr.id', '=', 'f.product_id')->join('units as u', 'u.id', '=', 'pr.base_unit_id')
            ->where('f.state', 'OPEN')
            ->selectRaw("'f' || f.id as key, null::bigint as company_id, pr.name as subject, 'finding' as ref, trim_scale(f.surplus_qty_base)::text as value, u.code as unit, f.recorded_at::text as at, pr.public_id::text as target");
        $stale = DB::table('stock_counts as c')->join('locations as l', 'l.id', '=', 'c.location_id')->where('c.state', 'STALE')
            ->selectRaw("'c' || c.id as key, null::bigint as company_id, l.name as subject, 'stale' as ref, null::text as value, null::text as unit, c.started_at::text as at, null::text as target");

        return DB::query()->fromSub($findings->unionAll($stale), 'x')->orderBy('x.at')->select('x.*');
    }

    protected function href(Context $context, object $row): ?string
    {
        if ($context->viewer->has('stock.count') || $context->viewer->has('stock.count_apply')) {
            return '/gudang/stok-opname';
        }

        $product = Row::nullable($row, 'target');

        return $product === null ? null : '/gudang/stok/'.$product;
    }

    public function seeAll(Context $context): ?string
    {
        return $context->viewer->has('stock.count') || $context->viewer->has('stock.count_apply') ? '/gudang/stok-opname' : null;
    }
}
