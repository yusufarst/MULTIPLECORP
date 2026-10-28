<?php

namespace App\Modules\OPS\Queries;

use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The correction cases a project names, for its returns and corrections predicate (BR-PRJ-02 predicate 3; BR-CR-04),
 * OPS's query class PRJ reads directly (ARCHITECTURE §3): per project whether it has any case, and its open cases by
 * kind, oldest kind first — one statement over the projects asked (QB-01; PF-01). An open case is a blocker of the
 * journey (the P7 gate record's journey model).
 */
final class ProjectCases
{
    /**
     * @param  list<int>  $projectIds
     * @return array<int, array{cases: int, open: list<array{kind: string, count: int}>}>
     */
    public function of(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('correction_cases')
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id', 'case_kind')
            ->select(['project_id', 'case_kind'])
            ->selectRaw('count(*) as cases')
            ->selectRaw("count(*) filter (where state = 'OPEN') as open")
            ->selectRaw('min(id) as first')
            ->orderBy('project_id')->orderBy('first')
            ->get();
        $cases = [];

        foreach ($rows as $row) {
            $project = (int) Row::text($row, 'project_id');
            $cases[$project] ??= ['cases' => 0, 'open' => []];
            $cases[$project]['cases'] += (int) Row::text($row, 'cases');
            $open = (int) Row::text($row, 'open');

            if ($open > 0) {
                $cases[$project]['open'][] = ['kind' => Row::text($row, 'case_kind'), 'count' => $open];
            }
        }

        return $cases;
    }
}
