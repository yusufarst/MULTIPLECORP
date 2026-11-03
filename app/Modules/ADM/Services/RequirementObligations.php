<?php

namespace App\Modules\ADM\Services;

use App\Modules\PRJ\Contracts\ResidualObligationProvider;
use App\Support\RowHandle;

/**
 * ADM's residual obligations (WF-PRJ-03; BR-PRJ-04): each required item neither satisfied nor validly waived — the missing documents a Force Complete
 * leaves listed —, named by its description. One statement through {@see Readiness} (QB-01).
 */
final class RequirementObligations implements ResidualObligationProvider
{
    public function obligations(array $projectIds): array
    {
        $obligations = [];

        foreach (Readiness::of($projectIds) as $project => $items) {
            foreach ($items as $item) {
                if ($item['required'] && ! in_array($item['status'], Readiness::MET, true)) {
                    $obligations[$project][] = ['kind' => 'document', 'ref' => RowHandle::of('admin_requirements', $item['id']), 'label' => $item['description'], 'amount' => null, 'quantity' => null];
                }
            }
        }

        return $obligations;
    }
}
