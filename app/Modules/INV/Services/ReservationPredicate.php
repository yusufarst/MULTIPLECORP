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
 * INV's part of the stock predicate (WF-PRJ-03 predicate 8; TASK-038): no active reservation of the project — one blocker
 * naming how many remain to be released otherwise, a residual one listed to be released by command (QS-16; PX-02).
 */
final class ReservationPredicate implements CompletionPredicateProvider
{
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
            ->selectRaw('l.project_id, count(r.id) as active')->get();
        $states = [];

        foreach ($rows as $row) {
            $active = (int) Row::text($row, 'active');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::STOCK => new PredicateState(true,
                $active === 0 ? [] : [new JourneyItem('projects.item.reservations_active', ['jumlah' => (string) $active], 'warehouse', true)])];
        }

        return $states;
    }
}
