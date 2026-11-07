<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The stock report (RD-02; CALC-01; CALC-02; BR-INV-13; PJ-07; PJ-22; UXS-39): the pool's position per product — ON
 * HAND, RESERVED, UNUSABLE and AVAILABLE —; a pending unattributed quantity as its own line, outside every company's
 * figures (MSG-41); each company's remaining reservations, its project's number only with `projects.view`; and with
 * `cost.view` each company's stock value and its own exposure to what waits on the Owner — never a case's pending
 * quantity or another candidate. A position, read as of now: the period does not bound it. Five statements.
 */
final class StockReport implements Report
{
    public function code(): string
    {
        return 'stok';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::PooledPhysical->admits($viewer) ? ['stock.view'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return array_values(array_filter(['cost.view', 'projects.view'], $viewer->has(...)));
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $companies = $filters->companyIds();
        $tables = [$this->position(), $this->pending(), $this->reservations($viewer, $companies)];

        if ($viewer->has('cost.view')) {
            $tables[] = $this->values($companies);
            $tables[] = self::exposures('paparan', $companies);
        }

        return $tables;
    }

    private function position(): Table
    {
        $query = DB::table('products as p')->leftJoin('stock_product_balances as b', 'b.product_id', '=', 'p.id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.kind', 'GOODS')
            ->where(fn (Builder $live) => $live->where('p.is_active', true)->orWhere('b.on_hand_qty', '<>', 0))
            ->select(['p.id', 'p.sku', 'p.name', 'u.code as unit', DB::raw('lower(p.name) as sort_name'),
                DB::raw('coalesce(b.on_hand_qty, 0) as on_hand'), DB::raw('coalesce(b.reserved_qty, 0) as reserved'), DB::raw('coalesce(b.unusable_qty, 0) as unusable'),
                DB::raw('coalesce(b.on_hand_qty, 0) - coalesce(b.reserved_qty, 0) - coalesce(b.unusable_qty, 0) as available')]);

        return new Table('posisi', [new Column('sku'), new Column('product'), new Column('unit'), new Column('on_hand', 'qty'), new Column('reserved', 'qty'), new Column('unusable', 'qty'), new Column('available', 'qty')], null,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['lower(p.name)' => 'sort_name', 'p.id' => 'id']) as $row) {
                    yield [null, [Row::nullable($row, 'sku'), Row::text($row, 'name'), Row::text($row, 'unit'), Row::text($row, 'on_hand'), Row::text($row, 'reserved'), Row::text($row, 'unusable'), Row::text($row, 'available')]];
                }
            },
            fn (): int => (clone $query)->count());
    }

    /** BR-INV-13: the pool's pending quantity per product, on its own line (MSG-41; PJ-07) — the pool views show it whole. */
    private function pending(): Table
    {
        $query = DB::table('unattributed_loss_cases as c')->join('products as p', 'p.id', '=', 'c.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('c.case_kind', 'LOSS')->where('c.pending_qty_base', '>', 0)
            ->groupBy('p.id', 'p.name', 'u.code')->select(['p.id', 'p.name', 'u.code as unit', DB::raw('sum(c.pending_qty_base) as pending')]);

        return new Table('selisih', [new Column('product'), new Column('unit'), new Column('pending', 'qty')], null,
            function () use ($query): iterable {
                foreach ((clone $query)->orderByRaw('lower(p.name), p.id')->get() as $row) {
                    yield [null, [Row::text($row, 'name'), Row::text($row, 'unit'), Row::text($row, 'pending')]];
                }
            },
            fn (): int => DB::query()->fromSub($query, 'g')->count());
    }

    /**
     * CALC-02 per company: each ACTIVE reservation's remainder, its project named only with `projects.view` (RD-02; RD-03).
     *
     * @param  list<int>  $companies
     */
    private function reservations(Authority $viewer, array $companies): Table
    {
        $named = $viewer->has('projects.view');
        $query = DB::table('reservations as r')->whereIn('r.company_id', $companies)->where('r.state', 'ACTIVE')->where('r.remaining_qty_base', '>', 0)
            ->join('products as p', 'p.id', '=', 'r.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as j', 'j.id', '=', 'l.project_id')
            ->select(['r.id', 'r.company_id', 'p.name', 'u.code as unit', 'r.remaining_qty_base', 'j.project_number']);

        return new Table('reservasi', [new Column('project'), new Column('product'), new Column('unit'), new Column('remaining', 'qty')], $companies,
            function () use ($query, $named): iterable {
                foreach (Keyed::rows($query, ['r.company_id' => 'company_id', 'r.id' => 'id']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [$named ? Row::text($row, 'project_number') : null, Row::text($row, 'name'), Row::text($row, 'unit'), Row::text($row, 'remaining_qty_base')]];
                }
            },
            fn (): int => (clone $query)->count());
    }

    /**
     * A company's stock value: its own lots' remaining cost (PJ-22: `cost.view` of that company), never a total across companies.
     *
     * @param  list<int>  $companies
     */
    private function values(array $companies): Table
    {
        return new Table('nilai', [new Column('value', 'money', true)], $companies,
            function () use ($companies): iterable {
                $values = DB::table('inventory_lots as l')->join('lot_cost_balances as b', 'b.lot_id', '=', 'l.id')->whereIn('l.source_company_id', $companies)
                    ->groupBy('l.source_company_id')->selectRaw('l.source_company_id, coalesce(sum(b.remaining_cost), 0.00)::text as total')->pluck('total', 'l.source_company_id')->all();

                foreach ($companies as $company) {
                    $value = $values[$company] ?? '0.00';
                    yield [$company, [is_string($value) ? $value : '0.00']];
                }
            },
            fn (): int => count($companies));
    }

    /**
     * Each company's own exposure to what still waits on the Owner (UXS-39; PJ-07; PJ-22), per product: an overlap group
     * counts once, by its bound less what it has assigned (DATABASE §5.8); a case alone by its own row. Shared with the
     * profit report, where a company's exposure appears the same way.
     *
     * @param  list<int>  $companies
     */
    public static function exposures(string $key, array $companies): Table
    {
        $alone = DB::table('unattributed_loss_exposures as x')->join('unattributed_loss_cases as k', 'k.id', '=', 'x.unattributed_loss_case_id')->whereIn('x.company_id', $companies)->where('k.pending_qty_base', '>', 0)->whereNull('k.exposure_group_case_id')
            ->whereNotExists(fn (Builder $member) => $member->from('unattributed_loss_cases as m')->whereColumn('m.exposure_group_case_id', 'k.id'))
            ->selectRaw('x.company_id, k.product_id, x.exposure_qty_base - x.assigned_net_qty_base as qty');
        $grouped = DB::table('unattributed_loss_group_exposures as g')->join('unattributed_loss_cases as k', 'k.id', '=', 'g.exposure_group_case_id')->whereIn('g.company_id', $companies)
            ->whereExists(fn (Builder $member) => $member->from('unattributed_loss_cases as m')->whereRaw('coalesce(m.exposure_group_case_id, m.id) = g.exposure_group_case_id')->where('m.pending_qty_base', '>', 0))
            ->selectRaw('g.company_id, k.product_id, g.bound_qty_base - g.assigned_net_qty_base as qty');
        $query = DB::query()->fromSub($alone->unionAll($grouped), 'e')->join('products as p', 'p.id', '=', 'e.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('e.qty', '>', 0)
            ->groupBy('e.company_id', 'p.id', 'p.name', 'u.code')->select(['e.company_id', 'p.name', 'u.code as unit', DB::raw('sum(e.qty) as qty')]);

        return new Table($key, [new Column('product'), new Column('unit'), new Column('exposure', 'qty')], $companies,
            function () use ($query): iterable {
                foreach ((clone $query)->orderBy('e.company_id')->orderByRaw('lower(p.name), p.id')->get() as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'name'), Row::text($row, 'unit'), Row::text($row, 'qty')]];
                }
            },
            fn (): int => DB::query()->fromSub($query, 'x')->count());
    }
}
