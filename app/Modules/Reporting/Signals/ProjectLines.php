<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The open root of the line signals (PERFORMANCE §7, QS-01–QS-04, QS-06, QS-24): a company's ACTIVE projects on
 * `projects (company_id, state, id)` (IX-11; IX-14), then their lines and line guards by key, each line with its
 * product and base unit for its row. A project's number is its RD-03 part: shown where the viewer holds
 * `projects.view` in that company (PJ-13).
 */
final class ProjectLines
{
    /** The quantity a WAREHOUSE line still needs reserved: its demand less reserved, dispatched and cancelled (L-04; CALC-02). */
    public const UNRESERVED = '(b.demand_qty_base - b.reserved_qty_base - b.dispatched_net_qty_base - b.cancelled_qty_base)';

    public static function active(int $company): Builder
    {
        return DB::table('projects as p')
            ->join('project_lines as l', 'l.project_id', '=', 'p.id')
            ->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->join('products as pr', 'pr.id', '=', 'l.product_id')
            ->join('units as u', 'u.id', '=', 'pr.base_unit_id')
            ->where('p.company_id', $company)->where('p.state', 'ACTIVE');
    }

    /** The confirmed WAREHOUSE lines whose demand is not yet reserved (QS-01's lines). */
    public static function unreserved(int $company): Builder
    {
        return self::active($company)->where('l.fulfillment_mode', 'WAREHOUSE')->whereNotNull('l.current_pin_id')
            ->whereRaw(self::UNRESERVED.' > 0');
    }

    /** The row's project part for this viewer: the number with `projects.view` in the company (RD-03), else nothing. */
    public static function project(Context $context, object $row, ?string $number): ?string
    {
        return $context->viewer->allows('projects.view', (int) Row::text($row, 'company_id')) ? $number : null;
    }
}
