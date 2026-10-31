<?php

namespace App\Modules\PUR\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PUR's part of `CompletionPredicateProvider` (ARCHITECTURE §3; WF-PRJ-03 predicate 8; BR-PUR-04): no open project-linked purchase remainder without closure. The predicate itself is TASK-055's.
 */
final class PurchasePredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::STOCK];
    }

    /**
     * For each project with a live purchase, in one set query (QB-01).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('purchases as p')->join('purchase_lines as l', 'l.purchase_id', '=', 'p.id')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')
            ->whereIn('p.project_id', $projectIds)->where('p.state', '<>', 'CANCELLED')->groupBy('p.project_id')
            ->selectRaw("p.project_id, count(*) filter (where l.kind = 'GOODS' and b.ordered_qty_base > b.normal_received_net_qty_base + b.dropship_confirmed_net_qty_base + b.closed_qty_base) as open")->get();
        $states = [];

        foreach ($rows as $row) {
            $open = (int) Row::text($row, 'open');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::STOCK => new PredicateState(true,
                $open === 0 ? [] : [new JourneyItem('projects.item.purchase_remainders', ['jumlah' => (string) $open], 'warehouse', true)])];
        }

        return $states;
    }
}
