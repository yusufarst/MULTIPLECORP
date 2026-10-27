<?php

use App\Modules\ORG\Data\TreatmentPrecedence;

// TL-03 (TASK-025; DATABASE §12; DIR-024 D-1; BR-FIN-17): among the rules in force of one context and tax type, the
// company's own overrides the global one; another company's never applies; with neither, nothing is assumed (FS-13).

it('chooses the company\'s rule over the global one, never another company\'s', function () {
    $global = ['company_id' => null, 'treatment' => 'SETTLEMENT_NO_EXPENSE', 'evidence_required' => true];
    $own = ['company_id' => 7, 'treatment' => 'SETTLEMENT_TO_EXPENSE', 'evidence_required' => false];
    $other = ['company_id' => 8, 'treatment' => 'REMITTANCE_TO_EXPENSE', 'evidence_required' => false];

    expect(TreatmentPrecedence::choose([$global, $own], 7))->toBe($own)
        ->and(TreatmentPrecedence::choose([$own, $global], 7))->toBe($own)
        ->and(TreatmentPrecedence::choose([$global, $other], 7))->toBe($global)
        ->and(TreatmentPrecedence::choose([$other], 7))->toBeNull()
        ->and(TreatmentPrecedence::choose([], 7))->toBeNull();
});
