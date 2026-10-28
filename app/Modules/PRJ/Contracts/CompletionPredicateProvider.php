<?php

namespace App\Modules\PRJ\Contracts;

use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;

/**
 * PRJ's inversion contract for the completion predicates a higher tier owns (ARCHITECTURE §3; WF-PRJ-03): PUR, INV,
 * FUL, FIN and ADM each implement it in their own Task and register it under {@see self::TAG}. The journey summary of
 * PG-010, PG-011's predicates group and "Yang tersisa sebelum selesai" read every predicate through it, and AX-25 reads
 * it inside the completion's transaction under the project's LK-01 lock (CONCURRENCY_IDEMPOTENCY §7).
 *
 * Each predicate is one set query over the given projects, never one query per project (QB-01; PF-01), so a page's
 * statements do not grow with its rows (PFM-02).
 */
interface CompletionPredicateProvider
{
    public const TAG = 'prj.completion-predicates';

    /**
     * The predicates of {@see Predicates} this provider evaluates.
     *
     * @return list<string>
     */
    public function predicates(): array;

    /**
     * Each predicate's state for each project; a project or predicate left out has no facts to judge.
     *
     * @param  list<int>  $projectIds
     * @param  bool  $money  whether the reader holds `finance.view` — without it no money item is produced (PJ-13)
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array;
}
