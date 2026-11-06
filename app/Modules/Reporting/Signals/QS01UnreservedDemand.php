<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;

/**
 * QS-01 (WORKFLOWS §10; IA §9.1): confirmed WAREHOUSE demand not yet reserved, oldest line first; the line on PG-025
 * "Reservasi" for `stock.reserve`, otherwise the line on PG-011.
 */
final class QS01UnreservedDemand extends LineSignal
{
    public function code(): string
    {
        return 'QS-01';
    }

    protected function branch(Context $context, int $company): Builder
    {
        return ProjectLines::unreserved($company)->orderBy('l.id')
            ->selectRaw("'l' || l.id as key, p.company_id, pr.name as subject, p.project_number as ref, trim_scale(".ProjectLines::UNRESERVED.')::text as value, u.code as unit, l.recorded_at::text as at, p.public_id::text as target');
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
