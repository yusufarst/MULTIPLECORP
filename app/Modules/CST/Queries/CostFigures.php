<?php

namespace App\Modules\CST\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * CST's figures for the higher tiers (DATABASE §6.4 (8); BR-FIN-11; AC-11): a lot's remaining cost; a project's direct
 * cost (CALC-09) — its HPP, the losses it bears and its expenses, each row counted once —; a company's cost part of
 * CALC-11 — its company-level losses and unattributed expenses, non-attributable charges among them —; and the
 * inter-company allocation rows (DATABASE §6.5). Every read holds the cost family (`cost.view`; PJ-22) and applies the
 * viewer's company scope inside its query before anything else (AZ-04; CS-07), a company out of scope answering as
 * unknown (AZ-11); figures stay per company, never a total across them (CS-08). An allocation shows each side what it
 * may see: the source side the draw with the bearer masked, the bearer side the cost in full with the source masked,
 * unless the other company is in scope too (PJ-05; PJ-06).
 */
final class CostFigures
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return ?array{remaining_qty: string, remaining_cost: string} the quantity only beside its cost (PJ-22) */
    public function lot(int $viewerId, int $lotId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return null;
        }

        $row = $viewer->scope->apply(DB::table('inventory_lots as l'), 'l.source_company_id')->join('lot_cost_balances as b', 'b.lot_id', '=', 'l.id')
            ->where('l.id', $lotId)->first(['b.remaining_qty_base', 'b.remaining_cost']);

        return is_object($row) ? ['remaining_qty' => Row::text($row, 'remaining_qty_base'), 'remaining_cost' => Row::text($row, 'remaining_cost')] : null;
    }

    /**
     * The loss each movement recorded, only with `cost.view` of its bearer, with no source side (J-INV-06; PJ-06).
     *
     * @param  list<int>  $movementIds
     * @return array<int, string> movement => amount
     */
    public function lossesOf(int $viewerId, array $movementIds): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $figures = [];

        if ($movementIds === [] || ! $viewer->has('cost.view')) {
            return $figures;
        }

        foreach ($viewer->scope->apply(DB::table('cost_attributions as a'), 'a.bearer_company_id')->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->whereIn('c.stock_movement_id', $movementIds)->where('a.cost_class', 'LOSS')->groupBy('c.stock_movement_id', 'a.bearer_company_id')
            ->selectRaw('c.stock_movement_id, a.bearer_company_id, sum(a.amount) as amount')->get() as $row) {
            if ($viewer->allows('cost.view', (int) Row::text($row, 'bearer_company_id'))) {
                $movement = (int) Row::text($row, 'stock_movement_id');
                $figures[$movement] = Money::of($figures[$movement] ?? '0')->plus(Money::of(Row::text($row, 'amount')))->toString();
            }
        }

        return $figures;
    }

    /**
     * PG-019's lots' cost and flagged basis, only those of a source company whose `cost.view` the viewer holds, in scope (PJ-02; rows 73, 74).
     *
     * @return array<string, array{cost: string, flagged: ?string}>
     */
    public function lotsOfProduct(int $viewerId, int $productId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $figures = [];

        if (! $viewer->has('cost.view')) {
            return $figures;
        }

        foreach ($viewer->scope->apply(DB::table('inventory_lots as l'), 'l.source_company_id')->join('lot_cost_balances as b', 'b.lot_id', '=', 'l.id')->where('l.product_id', $productId)
            ->get(['l.id', 'l.source_company_id', 'b.remaining_cost', DB::raw("(select sum(e.amount) from lot_cost_entries e where e.lot_id = l.id and e.entry_kind = 'SURPLUS_FLAGGED_BASIS') as flagged")]) as $row) {
            if ($viewer->allows('cost.view', (int) Row::text($row, 'source_company_id'))) {
                $figures[RowHandle::of('inventory_lots', (int) Row::text($row, 'id'))] = ['cost' => Row::text($row, 'remaining_cost'), 'flagged' => Row::nullable($row, 'flagged')];
            }
        }

        return $figures;
    }

    /**
     * PG-018's stock value: each company in scope whose `cost.view` the viewer holds, its own lots only, labelled per company and never a total across them; with its own exposure on a pending case, never the case's pending quantity or another candidate (PJ-22; CS-08; UXS-39).
     *
     * @return list<array{company: string, name: string, value: string, exposures: list<array{qty: string, unit: string}>}>
     */
    public function stockValues(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return [];
        }

        $companies = array_values(array_filter($viewer->scope->apply(DB::table('companies as c'), 'c.id')->orderBy('c.code')->get(['c.id', 'c.code', 'c.legal_name'])->all(), fn (object $company): bool => $viewer->allows('cost.view', (int) Row::text($company, 'id'))));
        $ids = array_map(fn (object $company): int => (int) Row::text($company, 'id'), $companies);
        $totals = $ids === [] ? [] : DB::table('inventory_lots as l')->join('lot_cost_balances as b', 'b.lot_id', '=', 'l.id')->whereIn('l.source_company_id', $ids)->groupBy('l.source_company_id')->selectRaw('l.source_company_id, coalesce(sum(b.remaining_cost), 0.00)::text as total')->pluck('total', 'l.source_company_id')->all();
        $exposures = $ids === [] ? [] : self::exposures($ids);

        return array_map(fn (object $company): array => ['company' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'value' => is_string($totals[(int) Row::text($company, 'id')] ?? null) ? $totals[(int) Row::text($company, 'id')] : '0.00', 'exposures' => $exposures[(int) Row::text($company, 'id')] ?? []], $companies);
    }

    /**
     * Each company's own exposure to what still waits on the Owner (UXS-39; PJ-07; PJ-22), per product and never a case's pending quantity or another company's: an overlap group's members hold one snapshot of the same claims, so a group counts once, by its bound less what it has assigned (DATABASE §5.8); a case alone by its own row.
     *
     * @param  list<int>  $companies
     * @return array<int, list<array{product: string, qty: string, unit: string}>>
     */
    private static function exposures(array $companies): array
    {
        $rows = [];
        $alone = DB::table('unattributed_loss_exposures as x')->join('unattributed_loss_cases as k', 'k.id', '=', 'x.unattributed_loss_case_id')->whereIn('x.company_id', $companies)->where('k.pending_qty_base', '>', 0)->whereNull('k.exposure_group_case_id')
            ->whereNotExists(fn (Builder $member) => $member->from('unattributed_loss_cases as m')->whereColumn('m.exposure_group_case_id', 'k.id'))
            ->selectRaw('x.company_id, k.product_id, x.exposure_qty_base - x.assigned_net_qty_base as qty');
        $grouped = DB::table('unattributed_loss_group_exposures as g')->join('unattributed_loss_cases as k', 'k.id', '=', 'g.exposure_group_case_id')->whereIn('g.company_id', $companies)
            ->whereExists(fn (Builder $member) => $member->from('unattributed_loss_cases as m')->whereRaw('coalesce(m.exposure_group_case_id, m.id) = g.exposure_group_case_id')->where('m.pending_qty_base', '>', 0))
            ->selectRaw('g.company_id, k.product_id, g.bound_qty_base - g.assigned_net_qty_base as qty');

        foreach (DB::query()->fromSub($alone->unionAll($grouped), 'e')->join('products as p', 'p.id', '=', 'e.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('e.qty', '>', 0)->groupBy('e.company_id', 'p.name', 'u.code')->orderBy('p.name')->selectRaw('e.company_id, p.name, u.code, sum(e.qty) as qty')->get() as $row) {
            $rows[(int) Row::text($row, 'company_id')][] = ['product' => Row::text($row, 'name'), 'qty' => Row::text($row, 'qty'), 'unit' => Row::text($row, 'code')];
        }

        return $rows;
    }

    /**
     * CALC-09: the project's HPP, the losses it bears — an unrecovered supplier-return share among them — and its
     * expenses, each `cost_attributions` and `expenses` row once; a supplier return's own class is no project cost.
     *
     * @return ?array{hpp: string, loss: string, expenses: string, direct_cost: string}
     */
    public function project(int $viewerId, int $projectId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return null;
        }

        $row = $viewer->scope->apply(DB::table('projects as p'), 'p.company_id')->where('p.id', $projectId)->selectRaw(<<<'SQL'
            coalesce((select sum(a.amount) from cost_attributions a where a.bearer_project_id = p.id and a.cost_class = 'HPP'), 0.00) as hpp,
            coalesce((select sum(a.amount) from cost_attributions a where a.bearer_project_id = p.id and a.cost_class = 'LOSS'), 0.00) as loss,
            coalesce((select sum(x.amount - coalesce((select sum(c.amount) from financial_contras c where c.expense_id = x.id), 0)) from expenses x where x.project_id = p.id), 0.00) as expenses
            SQL)->first();

        if (! is_object($row)) {
            return null;
        }

        [$hpp, $loss, $expenses] = [Row::text($row, 'hpp'), Row::text($row, 'loss'), Row::text($row, 'expenses')];

        return ['hpp' => $hpp, 'loss' => $loss, 'expenses' => $expenses, 'direct_cost' => Money::sum(Money::of($hpp), Money::of($loss), Money::of($expenses))->toString()];
    }

    /**
     * CALC-11's cost part for each company in scope: its company-level losses and its expenses borne by no project —
     * the non-attributable charges of company purchases among them —, per company, never summed across them (CS-08).
     *
     * @return list<array{company_id: int, losses: string, expenses: string}>
     */
    public function companies(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return [];
        }

        $rows = $viewer->scope->apply(DB::table('companies as c'), 'c.id')->orderBy('c.id')->selectRaw(<<<'SQL'
            c.id,
            coalesce((select sum(a.amount) from cost_attributions a where a.bearer_company_id = c.id and a.bearer_project_id is null and a.cost_class = 'LOSS'), 0.00) as losses,
            coalesce((select sum(x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)) from expenses x where x.company_id = c.id and x.project_id is null), 0.00) as expenses
            SQL)->get();
        $figures = [];

        foreach ($rows as $row) {
            $figures[] = ['company_id' => (int) Row::text($row, 'id'), 'losses' => Row::text($row, 'losses'), 'expenses' => Row::text($row, 'expenses')];
        }

        return $figures;
    }

    /**
     * The inter-company allocation rows a viewer may see (DATABASE §6.5; PJ-05; PJ-06): every cost row drawn from one
     * company's lot for another company, of a side in the viewer's scope, with what that side sees — the source side
     * the draw with the bearer, its reason and actor masked, the bearer side the cost in full with the source masked;
     * the lot code to holders of `stock.view` only.
     *
     * @return list<array{id: int, class: string, cause: string, amount: string, date: string, product: string, quantity: string, allocated: true,
     *     bearer_company_id: ?int, bearer_project_id: ?int, reason: ?string, authorized_by: ?string, source_company_id: ?int, lot_code: ?string}>
     */
    public function allocations(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return [];
        }

        $rows = DB::table('cost_attributions as a')
            ->where(fn (Builder $sides) => $sides
                ->where(fn (Builder $source) => $viewer->scope->apply($source, 'a.source_company_id'))
                ->orWhere(fn (Builder $bearer) => $viewer->scope->apply($bearer, 'a.bearer_company_id')))
            ->whereColumn('a.source_company_id', '<>', 'a.bearer_company_id')
            ->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->join('inventory_lots as l', 'l.id', '=', 'a.lot_id')
            ->join('products as p', 'p.id', '=', 'c.product_id')->leftJoin('users as u', 'u.id', '=', 'c.allocation_authorized_by')
            ->orderBy('a.id')
            ->get(['a.id', 'a.cost_class', 'a.cause', 'a.amount', 'a.business_date', 'a.source_company_id', 'a.bearer_company_id', 'a.bearer_project_id', 'a.allocation_reason',
                'c.qty_base', 'p.name as product', 'l.lot_code', 'u.name as authorized_by']);
        $stock = $viewer->has('stock.view');
        $allocations = [];

        foreach ($rows as $row) {
            $source = (int) Row::text($row, 'source_company_id');
            $bearer = (int) Row::text($row, 'bearer_company_id');
            $project = Row::nullable($row, 'bearer_project_id');
            $seesBearer = $viewer->scope->includes($bearer);
            $allocations[] = [
                'id' => (int) Row::text($row, 'id'),
                'class' => Row::text($row, 'cost_class'),
                'cause' => Row::text($row, 'cause'),
                'amount' => Row::text($row, 'amount'),
                'date' => Row::text($row, 'business_date'),
                'product' => Row::text($row, 'product'),
                'quantity' => Row::text($row, 'qty_base'),
                'allocated' => true,
                // PJ-05: the bearer, its reason and its actor only when the bearer company is in scope too.
                'bearer_company_id' => $seesBearer ? $bearer : null,
                'bearer_project_id' => $seesBearer && $project !== null ? (int) $project : null,
                'reason' => $seesBearer ? Row::nullable($row, 'allocation_reason') : null,
                'authorized_by' => $seesBearer ? Row::nullable($row, 'authorized_by') : null,
                // PJ-06: the source company only when it is in scope too; the lot code to holders of stock.view.
                'source_company_id' => $viewer->scope->includes($source) ? $source : null,
                'lot_code' => $stock ? Row::text($row, 'lot_code') : null,
            ];
        }

        return $allocations;
    }
}
