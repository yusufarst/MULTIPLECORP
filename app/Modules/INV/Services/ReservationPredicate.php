<?php

namespace App\Modules\INV\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * INV's part of the stock predicate (WF-PRJ-03 predicate 8; TASK-038; TASK-042): no active reservation of the project — one
 * blocker naming how many remain to be released otherwise, a residual one listed to be released by command (QS-16; PX-02) —
 * and no unresolved unusable units whose causal project it is (L-38): those of an open damaged-return case, and those damaged
 * at the receipt of its line, neither lost nor repaired, while they stay unusable on their lot. Disposing of them is a
 * residual command (L-11).
 */
final class ReservationPredicate implements CompletionPredicateProvider
{
    private const UNUSABLE = <<<'SQL'
        (select count(*) from (
            select k.source_lot_id as lot_id from correction_cases k
            where k.state = 'OPEN' and k.case_kind in ('SALES_RETURN', 'DAMAGED_UNIT') and k.causal_project_id = l.project_id and k.source_lot_id is not null
            union
            select i.id from project_lines pr
            join purchase_lines pl on pl.project_line_id = pr.id
            join receiving_lines g on g.purchase_line_id = pl.id and g.damaged_qty_base > 0
            join inventory_lots i on i.receiving_line_id = g.id
            where pr.project_id = l.project_id
                and not exists (select 1 from stock_movement_entries d where d.lot_id = i.id and d.movement_type = 'RECEIPT' and d.serial_unit_id is not null)
                and g.damaged_qty_base > coalesce((select sum(b.qty_base - b.restored_qty_base) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.lot_id = i.id and c.kind = 'LOSS' and c.bearer_project_id = pr.project_id and c.correction_case_id is null), 0)
                    + coalesce((select sum(e.qty_delta_base) from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id where e.lot_id = i.id and e.condition = 'USABLE' and ((m.movement_type = 'CONDITION_CHANGE' and e.qty_delta_base > 0) or (m.movement_type = 'MOVEMENT_REVERSAL' and m.reverses_movement_type = 'CONDITION_CHANGE' and e.qty_delta_base < 0))), 0)
            union
            select i.id from project_lines pr
            join purchase_lines pl on pl.project_line_id = pr.id
            join receiving_lines g on g.purchase_line_id = pl.id and g.damaged_qty_base > 0
            join inventory_lots i on i.receiving_line_id = g.id
            join stock_movement_entries d on d.lot_id = i.id and d.movement_type = 'RECEIPT' and d.condition = 'UNUSABLE' and d.serial_unit_id is not null
            join serial_units s on s.id = d.serial_unit_id and s.state = 'IN_STOCK' and s.current_condition = 'UNUSABLE' and s.current_lot_id = i.id
            where pr.project_id = l.project_id
                and coalesce((select sum(e.qty_delta_base) from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id where e.serial_unit_id = s.id and e.condition = 'USABLE' and ((m.movement_type = 'CONDITION_CHANGE' and e.qty_delta_base > 0) or (m.movement_type = 'MOVEMENT_REVERSAL' and m.reverses_movement_type = 'CONDITION_CHANGE' and e.qty_delta_base < 0))), 0) <= 0
        ) causal where exists (select 1 from stock_lot_balances u where u.lot_id = causal.lot_id and u.condition = 'UNUSABLE' and u.qty > 0)) as unusable
        SQL;

    /** @return list<string> */
    public function predicates(): array
    {
        return [Predicates::STOCK];
    }

    /**
     * Predicate 8's reservation part for each project with a warehouse line, in one set query (QB-01).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_lines as l')->leftJoin('reservations as r', fn (JoinClause $join) => $join->on('r.project_line_id', '=', 'l.id')->where('r.state', 'ACTIVE'))
            ->whereIn('l.project_id', $projectIds)->where('l.fulfillment_mode', 'WAREHOUSE')->groupBy('l.project_id')
            ->selectRaw('l.project_id, count(r.id) as active, (select count(*) from unattributed_loss_cases u where u.causal_project_id = l.project_id and u.pending_qty_base > 0) as losses, '.self::UNUSABLE)->get();
        $states = [];

        foreach ($rows as $row) {
            $active = (int) Row::text($row, 'active');
            $unusable = (int) Row::text($row, 'unusable');
            $losses = (int) Row::text($row, 'losses');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::STOCK => new PredicateState(true, [
                ...($active === 0 ? [] : [new JourneyItem('projects.item.reservations_active', ['jumlah' => (string) $active], 'warehouse', true)]),
                ...($unusable === 0 ? [] : [new JourneyItem('projects.item.unusable_units', ['jumlah' => (string) $unusable], 'warehouse', true)]),
                // L-38; DIR-026: an unexplained loss whose causal project is this one, still pending its resolution (TASK-055).
                ...($losses === 0 ? [] : [new JourneyItem('projects.item.loss_cases_pending', ['jumlah' => (string) $losses], 'warehouse', true)]),
            ])];
        }

        return $states;
    }
}
