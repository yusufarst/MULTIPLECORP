<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-02 (WORKFLOWS §10; IA §9.1): shortage → purchase needed — QS-01's open demand against its product guard, the
 * pool's usable availability (on hand less reserved and unusable; CALC-01) — the shortfall per line; PG-017 prefilled
 * with the project for `purchase.manage`, otherwise the line on PG-011.
 */
final class QS02Shortage extends LineSignal
{
    private const AVAILABLE = 'greatest(coalesce(s.on_hand_qty - s.reserved_qty - s.unusable_qty, 0), 0)';

    public function code(): string
    {
        return 'QS-02';
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::unreserved($company)
            ->leftJoin('stock_product_balances as s', 's.product_id', '=', 'l.product_id')
            ->whereRaw(ProjectLines::UNRESERVED.' > '.self::AVAILABLE)->orderBy('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(".ProjectLines::UNRESERVED.' - '.self::AVAILABLE.')::text as value, u.code as unit, l.recorded_at::text as at, p.public_id::text as target');
    }

    protected function href(Context $context, object $row): ?string
    {
        return $context->viewer->allows('purchase.manage', self::companyOf($row)) ? '/pembelian/baru?proyek='.Row::text($row, 'target') : self::project($context, $row);
    }

    public function seeAll(Context $context): ?string
    {
        return $context->holding('stock.reserve') !== [] ? '/gudang/reservasi' : null;
    }
}
