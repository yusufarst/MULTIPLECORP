<?php

namespace App\Modules\Reporting\Signals;

use App\Modules\INV\Queries\StockViews;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-17 (WORKFLOWS §10; IA §9.1; BR-INV-10): low stock and restock advice — a pool signal: the products carrying a
 * minimum, on IX-14's partial index `products_ix14_minimum`, joined to their product guard, whose usable availability
 * (on hand less reserved and unusable) is under it — read once with `stock.view` (RD-02). One set query whose cost
 * follows the number of such products (HO-16). Advice only, never a purchase: the product's stock on PG-019, "Lihat
 * Semua" → PG-018 filtered *di bawah minimum*.
 */
final class QS17LowStock extends Signal
{
    private const AVAILABLE = 'coalesce(s.on_hand_qty - s.reserved_qty - s.unusable_qty, 0)';

    public function code(): string
    {
        return 'QS-17';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('stock.view') ? [0] : [];
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('products as pr')->join('units as u', 'u.id', '=', 'pr.base_unit_id')->leftJoin('stock_product_balances as s', 's.product_id', '=', 'pr.id')
            ->whereNotNull('pr.min_stock_qty_base')->where('pr.is_active', true)->whereRaw(self::AVAILABLE.' < pr.min_stock_qty_base')->orderBy('pr.name')->orderBy('pr.id')
            ->selectRaw("'pr' || pr.id as key, null::bigint as company_id, pr.name as subject, trim_scale(pr.min_stock_qty_base)::text as ref, trim_scale(".self::AVAILABLE.')::text as value, u.code as unit, pr.name as at, pr.public_id::text as target');
    }

    protected function href(Context $context, object $row): string
    {
        return '/gudang/stok/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/gudang/stok?saring='.StockViews::BELOW_MINIMUM;
    }
}
