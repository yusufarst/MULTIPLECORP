<?php

use App\Modules\PRJ\Data\CompletionSnapshot;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Materiality;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Modules\PRJ\Services\ResidualCommands;

// TL-03 (TASK-055; ST-04; SF-REVAL; L-11; C-39): the completion snapshot's canonical form and its hash — the same facts the same hash, an amount or a blocker changed another —, the unmet
// predicates in WF-PRJ-03's order, the materiality test, and what each command meets on each project state.

function completionObligation(string $amount): array
{
    return ['kind' => 'receivable', 'ref' => '019a0000-0000-7000-8000-000000000001', 'label' => 'INV/2026/0001', 'amount' => $amount, 'quantity' => null];
}

it('gives the same facts the same hash, whatever the order the states arrive in (ST-04)', function () {
    $states = [Predicates::RECEIVABLE => new PredicateState(true, [new JourneyItem('projects.item.receivables_open', ['jumlah' => '1'])]), Predicates::BILLING => new PredicateState(true)];
    $reversed = array_reverse($states, true);

    expect(CompletionSnapshot::hash(CompletionSnapshot::of($states, [completionObligation('250000.00')])))->toBe(CompletionSnapshot::hash(CompletionSnapshot::of($reversed, [completionObligation('250000.00')])))
        ->and(CompletionSnapshot::hash(CompletionSnapshot::of($states, [])))->toMatch('/^[0-9a-f]{64}$/');
});

it('changes the hash when an amount or a blocker changes (ST-04; M-27)', function () {
    $states = [Predicates::RECEIVABLE => new PredicateState(true, [new JourneyItem('projects.item.receivables_open', ['jumlah' => '1'])])];
    $hash = CompletionSnapshot::hash(CompletionSnapshot::of($states, [completionObligation('250000.00')]));

    expect(CompletionSnapshot::hash(CompletionSnapshot::of($states, [completionObligation('250000.01')])))->not->toBe($hash)
        ->and(CompletionSnapshot::hash(CompletionSnapshot::of([Predicates::RECEIVABLE => new PredicateState(true, [new JourneyItem('projects.item.receivables_open', ['jumlah' => '2'])])], [completionObligation('250000.00')])))->not->toBe($hash);
});

it('carries a format version, every condition and no timestamp (ST-04)', function () {
    $snapshot = CompletionSnapshot::of([], []);

    expect($snapshot['format'])->toBe(CompletionSnapshot::FORMAT)->and(array_keys($snapshot['conditions']))->toBe(Predicates::ALL)
        ->and(json_encode($snapshot))->not->toMatch('/\d{4}-\d{2}-\d{2}/')
        ->and(collect($snapshot['conditions'])->every(fn (array $condition): bool => $condition['met'] && ! $condition['applies']))->toBeTrue();
});

it('names the unmet predicates in WF-PRJ-03\'s order (C-39)', function () {
    $blocked = new PredicateState(true, [new JourneyItem('x')]);

    expect(CompletionSnapshot::unmet([Predicates::AUDIT => $blocked, Predicates::FULFILMENT => $blocked, Predicates::BILLING => new PredicateState(true)]))->toBe([Predicates::FULFILMENT, Predicates::AUDIT])
        ->and(CompletionSnapshot::unmet([]))->toBe([]);
});

it('finds a correction material only when it changes a predicate input or a CALC value (SF-REVAL; CM-32)', function () {
    $before = ['predicates' => ['receivable' => ['outstanding' => '0.00']], 'calc' => ['direct_cost' => '100000.00']];

    expect(Materiality::material($before, $before))->toBeFalse()
        ->and(Materiality::material($before, ['predicates' => ['receivable' => ['outstanding' => '1.00']], 'calc' => ['direct_cost' => '100000.00']]))->toBeTrue()
        ->and(Materiality::material($before, ['predicates' => ['receivable' => ['outstanding' => '0.00']], 'calc' => ['direct_cost' => '100000.01']]))->toBeTrue();
});

it('accepts anything on an open project, only residual commands on a closed one, and runs a correction as a revalidation — never on a CANCELLED project (L-11; UXI-40)', function (string $state, string $command, bool $correction, string $decision) {
    expect(ResidualCommands::decision($state, $command, $correction))->toBe($decision);
})->with([
    'active, anything' => ['ACTIVE', 'PRJ.UpdateProject', false, ResidualCommands::ACCEPT],
    'draft, anything' => ['DRAFT', 'INV.Reserve', false, ResidualCommands::ACCEPT],
    'completed, a payment' => ['COMPLETED_NORMAL', 'FIN.RecordPayment', false, ResidualCommands::ACCEPT],
    'forced, a reservation released' => ['COMPLETED_FORCED', 'INV.ReleaseReservation', false, ResidualCommands::ACCEPT],
    'cancelled, a write-off' => ['CANCELLED', 'FIN.WriteOffReceivable', false, ResidualCommands::ACCEPT],
    'completed, a residual never revalidates' => ['COMPLETED_NORMAL', 'FIN.ApplyPayment', true, ResidualCommands::ACCEPT],
    'completed, a correction' => ['COMPLETED_NORMAL', 'FIN.CorrectBilling', true, ResidualCommands::REVALIDATE],
    'forced, a correction' => ['COMPLETED_FORCED', 'INV.ReverseDispatch', true, ResidualCommands::REVALIDATE],
    'cancelled, a correction' => ['CANCELLED', 'FIN.CorrectBilling', true, ResidualCommands::ACCEPT],
    'completed, anything else' => ['COMPLETED_NORMAL', 'INV.Reserve', false, ResidualCommands::REJECT],
    'cancelled, anything else' => ['CANCELLED', 'FIN.IssueInvoice', false, ResidualCommands::REJECT],
]);
