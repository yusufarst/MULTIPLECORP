<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-22 (WORKFLOWS §10; IA §9.1; `DIR-027`): pending unexplained-loss attribution cases — the cases with a pending
 * quantity, on the partial index `unattributed_loss_cases_ix10_pending` — a pool signal for the Owner alone (RD-09:
 * `review.view` or `stock.resolve_unattributed`), blocking no consumption; oldest first; PG-029.
 */
final class QS22PendingLoss extends Signal
{
    public function code(): string
    {
        return 'QS-22';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('review.view') || $context->viewer->has('stock.resolve_unattributed') ? [0] : [];
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('unattributed_loss_cases as k')->join('products as pr', 'pr.id', '=', 'k.product_id')->join('units as u', 'u.id', '=', 'pr.base_unit_id')
            ->where('k.pending_qty_base', '>', 0)->orderBy('k.id')
            ->selectRaw("'k' || k.id as key, null::bigint as company_id, pr.name as subject, null::text as ref, trim_scale(k.pending_qty_base)::text as value, u.code as unit, k.recorded_at::text as at, null::text as target");
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
