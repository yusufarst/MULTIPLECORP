<?php

namespace App\Modules\FUL\Services;

use App\Modules\FUL\Data\ShipmentFigures;
use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FUL's part of the fulfilment predicate for SERVICE lines (BR-PRJ-02; L-39; TASK-047), read by TASK-055: every confirmed
 * service line handed over or its remainder cancelled, the handover being the delivery equivalent — no delivery record
 * is asked of it. A provider owns its predicate's key, so TASK-045's goods lines extend this one class.
 */
final class HandoverPredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::FULFILMENT, Predicates::DELIVERY];
    }

    /**
     * For each project with a confirmed service line, in one set query (QB-01).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_lines as l')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->whereIn('l.project_id', $projectIds)->whereNotNull('l.current_pin_id')->groupBy('l.project_id')
            ->selectRaw("l.project_id, count(*) filter (where l.fulfillment_mode = 'SERVICE' and b.demand_qty_base > b.handed_over_qty_base + b.cancelled_qty_base) as open,
                count(*) filter (where l.fulfillment_mode <> 'SERVICE' and b.demand_qty_base > b.delivered_net_qty_base + b.cancelled_qty_base) as goods,
                (select count(*) from stock_movements m where m.project_id = l.project_id and m.movement_type = 'DISPATCH') as dispatches,
                (select count(*) from stock_movements m where m.project_id = l.project_id and m.movement_type = 'DISPATCH' and ".ShipmentFigures::AWAITING.') as awaiting')->get();
        $states = [];

        foreach ($rows as $row) {
            $open = (int) Row::text($row, 'open');
            $goods = (int) Row::text($row, 'goods');
            $awaiting = (int) Row::text($row, 'awaiting');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::FULFILMENT => new PredicateState(true, [
                ...($goods === 0 ? [] : [new JourneyItem('projects.item.goods_undelivered', ['jumlah' => (string) $goods], 'delivery')]),
                ...($open === 0 ? [] : [new JourneyItem('projects.item.handovers_open', ['jumlah' => (string) $open], 'service')]),
            ]),
                // Predicate 2: every shipment delivered or formally closed — one still awaiting on PG-031 blocks (TASK-045; TASK-055).
                Predicates::DELIVERY => (int) Row::text($row, 'dispatches') === 0 ? PredicateState::none() : new PredicateState(true,
                    $awaiting === 0 ? [] : [new JourneyItem('projects.item.shipments_awaiting', ['jumlah' => (string) $awaiting], 'delivery', true)]),
            ];
        }

        return $states;
    }
}
