<?php

namespace App\Modules\ADM\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;

/**
 * ADM's completion predicate (WF-PRJ-03 predicate 5; BR-PRJ-02; BR-PRJ-04; AC-21): every required item *Terpenuhi* or
 * validly *Tidak Berlaku* — a removed item no longer counts —, whatever the project's delivery or payment state. Its
 * unmet items are one blocker per kind of item with how many are open (AC-21), the default invoice item a money item a
 * viewer without `finance.view` never receives (PJ-13). An optional item never blocks. One statement for the set of
 * projects through {@see Readiness} (QB-01; PF-01).
 */
final class AdministrationPredicate implements CompletionPredicateProvider
{
    private const KEYS = [
        'CATALOG_DOCUMENT' => 'projects.item.requirements_catalog',
        'EXTERNAL_DOCUMENT' => 'projects.item.requirements_external',
        'FREE_TEXT' => 'projects.item.requirements_other',
    ];

    public function predicates(): array
    {
        return [Predicates::ADMINISTRATION];
    }

    public function states(array $projectIds, bool $money): array
    {
        $states = [];

        foreach (Readiness::of($projectIds) as $project => $items) {
            $required = array_values(array_filter($items, fn (array $item): bool => $item['required']));
            $open = [];
            $invoice = false;

            foreach ($required as $item) {
                if (in_array($item['status'], Readiness::MET, true)) {
                    continue;
                }

                if ($item['default_invoice']) {
                    $invoice = true;
                } else {
                    $open[$item['kind']] = ($open[$item['kind']] ?? 0) + 1;
                }
            }

            $blockers = [];

            foreach (self::KEYS as $kind => $key) {
                if (isset($open[$kind])) {
                    $blockers[] = new JourneyItem($key, ['jumlah' => (string) $open[$kind]], 'documents', true);
                }
            }

            if ($invoice) {
                $blockers[] = new JourneyItem('projects.item.requirements_invoice', [], 'documents', true, true);
            }

            $state = new PredicateState($required !== [], $blockers);
            $states[$project][Predicates::ADMINISTRATION] = $money ? $state : $state->withoutMoney();
        }

        return $states;
    }
}
