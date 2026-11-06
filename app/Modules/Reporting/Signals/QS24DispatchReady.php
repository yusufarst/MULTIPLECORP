<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-24 (`DIR-077` CA-07; IA §9.1): dispatch-ready lines — the WAREHOUSE lines of the company's ACTIVE projects whose
 * guard shows a remaining reservation, CALC-02's reserved remainder (`project_line_balances.reserved_qty_base`; L-04),
 * read from IX-14's open root and the guard by key —; PG-024 "Keluarkan Barang" for `stock.dispatch`, otherwise the
 * line on PG-011; "Lihat Semua" → PG-023.
 */
final class QS24DispatchReady extends LineSignal
{
    public function code(): string
    {
        return 'QS-24';
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::active($company)->where('l.fulfillment_mode', 'WAREHOUSE')->where('b.reserved_qty_base', '>', 0)->orderBy('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(b.reserved_qty_base)::text as value, u.code as unit, l.recorded_at::text as at, p.public_id::text as target");
    }

    protected function href(Context $context, object $row): ?string
    {
        return $context->viewer->allows('stock.dispatch', self::companyOf($row)) ? '/gudang/barang-keluar/'.Row::text($row, 'target') : self::project($context, $row);
    }

    public function seeAll(Context $context): string
    {
        return '/gudang/barang-keluar';
    }
}
