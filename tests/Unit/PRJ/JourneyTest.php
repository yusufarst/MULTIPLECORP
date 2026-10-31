<?php

use App\Modules\PRJ\Data\JourneyFacts;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Modules\PRJ\Services\Journey;

// TL-03 (TASK-032; the P7 gate record's journey model PL-1–PL-3; L-01): a project's stage, blocker marker and next step
// derived from its facts, decisions and predicates each time — the applicable stages in their fixed order, the ones that
// do not apply only named, the current one the first not complete, a blocker overlaying the stage it touches, the next
// step by one precedence, QS-15's money items left out without `finance.view`, a closed project its outcome only.

function facts(string $state = 'ACTIVE', bool $confirmed = false, ?string $quotation = null, bool $missing = false, array $modes = [], ?bool $invoiced = null): JourneyFacts
{
    return new JourneyFacts($state, $confirmed, $quotation, $missing, $modes, $invoiced);
}

function keys(array $journey): array
{
    return array_map(fn (array $stage): string => $stage['key'].':'.$stage['state'], $journey['stages']);
}

it('derives only the applicable stages in their fixed order, naming the others as not needed (PL-1; PL-2)', function () {
    $journey = Journey::derive(facts(modes: ['SERVICE' => ['done' => false, 'delivered' => true], 'WAREHOUSE' => ['done' => false, 'delivered' => false]]), [], true);

    expect(keys($journey))->toBe(['agreement:current', 'warehouse:upcoming', 'delivery:upcoming', 'service:upcoming', 'completion:upcoming'])
        ->and($journey['not_needed'])->toBe(['direct', 'documents', 'invoice', 'billing', 'payment'])
        ->and($journey['current'])->toBe('agreement')
        ->and($journey['position'])->toBe(1);
});

it('marks the first stage not complete as current, a confirmed project past its agreement (PL-1)', function () {
    $journey = Journey::derive(facts(confirmed: true, modes: ['DROPSHIP' => ['done' => true, 'delivered' => false]], invoiced: false), [], true);

    expect(keys($journey))->toBe(['agreement:complete', 'direct:complete', 'delivery:current', 'invoice:upcoming', 'completion:upcoming'])
        ->and($journey['position'])->toBe(3)
        ->and($journey['next']['key'])->toBe('projects.next.delivery');
});

it('takes the next step from the agreement\'s quotation state, and a DRAFT\'s missing details first (PL-3)', function () {
    expect(Journey::derive(facts(), [], true)['next']['key'])->toBe('projects.next.quotation')
        ->and(Journey::derive(facts(quotation: 'SENT'), [], true)['next']['key'])->toBe('projects.next.answer')
        ->and(Journey::derive(facts(quotation: 'REJECTED'), [], true)['next']['key'])->toBe('projects.next.revise')
        ->and(Journey::derive(facts('DRAFT', missing: true), [], true, '/proyek/x/ubah')['next'])->toMatchArray(['key' => 'projects.next.details', 'href' => '/proyek/x/ubah']);
});

it('overlays a blocker on the stage it names, or on the current one, and lists every unmet predicate (P7 gate record §a)', function () {
    $predicates = [
        Predicates::RETURNS => new PredicateState(true, [new JourneyItem('projects.item.cases_open', ['jumlah' => '1'], null, true)]),
        Predicates::DOCUMENTS => new PredicateState(true, [new JourneyItem('projects.item.rendition_failed', ['jumlah' => '2'], 'documents', true)]),
    ];
    $journey = Journey::derive(facts(confirmed: true, modes: ['WAREHOUSE' => ['done' => false, 'delivered' => false]], invoiced: false), $predicates, true);

    expect(keys($journey))->toBe(['agreement:complete', 'warehouse:attention', 'delivery:upcoming', 'documents:attention', 'invoice:upcoming', 'completion:upcoming'])
        ->and($journey['blocked'])->toBeTrue()
        ->and(array_column($journey['blockers'], 'stage'))->toBe(['warehouse', 'documents'])
        ->and(array_column($journey['remaining'], 'predicate'))->toBe(['returns', 'documents']);
});

it('omits QS-15\'s money items without finance.view and derives the invoice stage from the rest (PJ-13)', function () {
    $billing = [Predicates::BILLING => new PredicateState(true, [new JourneyItem('x.money', [], 'billing', false, true), new JourneyItem('x.plain', [], 'billing')])];
    $with = Journey::derive(facts(confirmed: true, invoiced: true), $billing, true);
    $without = Journey::derive(facts(confirmed: true, invoiced: true), $billing, false);

    expect(array_column($with['remaining'][0]['items'], 'key'))->toBe(['x.money', 'x.plain'])
        ->and(array_column($without['remaining'][0]['items'], 'key'))->toBe(['x.plain'])
        ->and(keys($with))->toContain('invoice:complete')
        ->and(keys($without))->toContain('invoice:complete')
        ->and(Journey::derive(facts(confirmed: true, invoiced: false), [], true)['next']['key'])->toBe('projects.next.invoice')
        ->and(Journey::derive(facts(confirmed: true, invoiced: false), [], false)['next'])->toBeNull();
});

it('shows a closed project\'s outcome and no open stage or step (L-11)', function () {
    foreach (['CANCELLED', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'] as $state) {
        $journey = Journey::derive(facts($state, confirmed: true), [], true);

        expect($journey)->toMatchArray(['outcome' => $state, 'stages' => [], 'current' => null, 'next' => null, 'blocked' => false]);
    }
});

it('treats a predicate without a provider as having no facts to judge (CompletionPredicateProvider)', function () {
    $journey = Journey::derive(facts(confirmed: true, invoiced: true), [], true);

    expect($journey['remaining'])->toBe([])
        ->and($journey['current'])->toBe('completion')
        ->and($journey['next']['key'])->toBe('projects.next.complete');
});

it('leads the *Pembayaran* stage\'s next step to PG-046 for an account that may record the payment (NAV-046; IA §8.7; TASK-051)', function () {
    $billed = [Predicates::BILLING => new PredicateState(false), Predicates::RECEIVABLE => new PredicateState(true, [new JourneyItem('projects.next.payment', [], 'payment')]), Predicates::PAYMENT => new PredicateState(false)];
    $facts = facts(confirmed: true, invoiced: true);
    $next = Journey::derive($facts, $billed, true, null, null, null, null, '/keuangan/pembayaran/baru?proyek=x')['next'];

    expect($next['stage'])->toBe('payment')->and($next['href'])->toBe('/keuangan/pembayaran/baru?proyek=x')
        ->and(Journey::derive($facts, $billed, true)['next']['href'])->toBeNull();
});
