<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-05 (WORKFLOWS §10; IA §9.1): open purchase remainders — the company's OPEN purchases on `purchases (company_id,
 * state)` (IX-14) with their lines whose guard shows a remainder (C-29) —, one row per purchase with its open lines,
 * oldest first; RD-05, or `stock.receive` through PJ-18 (its quantities only); PG-022 for receiving, PG-016 for
 * `cost.view`.
 */
final class QS05OpenPurchaseRemainders extends Signal
{
    private const OPEN = '(select count(*) from purchase_lines pl join purchase_line_balances g on g.purchase_line_id = pl.id where pl.purchase_id = pu.id and g.ordered_qty_base > g.normal_received_net_qty_base + g.closed_qty_base + g.dropship_confirmed_net_qty_base)';

    public function code(): string
    {
        return 'QS-05';
    }

    public function companies(Context $context): array
    {
        return $context->holding('cost.view', 'stock.receive');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('purchases as pu')->where('pu.company_id', $company)->where('pu.state', 'OPEN')
            ->whereRaw(self::OPEN.' > 0')->orderBy('pu.id')
            ->selectRaw("'p' || pu.id as key, pu.company_id, pu.purchase_number as subject, null::text as ref, ".self::OPEN.'::text as value, null::text as unit, pu.business_date::text as at, pu.public_id::text as target');
    }

    protected function href(Context $context, object $row): ?string
    {
        $company = self::companyOf($row);

        return match (true) {
            $context->viewer->allows('stock.receive', $company) => '/gudang/barang-masuk/'.Row::text($row, 'target'),
            $context->viewer->allows('cost.view', $company) => '/pembelian/'.Row::text($row, 'target'),
            default => null,
        };
    }

    public function seeAll(Context $context): string
    {
        return $context->holding('stock.receive') !== [] ? '/gudang/barang-masuk' : '/pembelian';
    }
}
