<?php

namespace App\Modules\INV\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * INV's part of the stock predicate (WF-PRJ-03 predicate 8; L-38; DIR-026; TASK-055): no pending unexplained-loss case whose causal project is this one —
 * a case with quantity still pending resolution. One set query (QB-01).
 */
final class LossCasePredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::STOCK];
    }

    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('unattributed_loss_cases')->whereIn('causal_project_id', $projectIds)->where('pending_qty_base', '>', 0)->groupBy('causal_project_id')
            ->selectRaw('causal_project_id, count(*) as pending')->get();
        $states = [];

        foreach ($rows as $row) {
            $states[(int) Row::text($row, 'causal_project_id')] = [Predicates::STOCK => new PredicateState(true,
                [new JourneyItem('projects.item.loss_cases_pending', ['jumlah' => Row::text($row, 'pending')], 'warehouse', true)])];
        }

        return $states;
    }
}
