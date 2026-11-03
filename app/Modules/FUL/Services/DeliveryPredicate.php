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
 * FUL's delivery predicate (WF-PRJ-03 predicate 2; TASK-045; TASK-055): every shipment of the project delivered or formally closed — a shipment still awaiting
 * on PG-031 blocks; an open discrepancy case is the correction-cases predicate's. One set query over the projects' dispatches (QB-01).
 */
final class DeliveryPredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::DELIVERY];
    }

    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('stock_movements as m')->whereIn('m.project_id', $projectIds)->where('m.movement_type', 'DISPATCH')->groupBy('m.project_id')
            ->selectRaw('m.project_id, count(*) filter (where '.ShipmentFigures::AWAITING.') as awaiting')->get();
        $states = [];

        foreach ($rows as $row) {
            $awaiting = (int) Row::text($row, 'awaiting');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::DELIVERY => new PredicateState(true,
                $awaiting === 0 ? [] : [new JourneyItem('projects.item.shipments_awaiting', ['jumlah' => (string) $awaiting], 'delivery', true)])];
        }

        return $states;
    }
}
