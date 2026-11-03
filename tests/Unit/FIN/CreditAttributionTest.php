<?php

use App\Modules\FIN\Data\CreditAttribution;

// TL-03 (TASK-053; WF-FIN-06; DIR-104; APPR-028; FX-01 3): each rupiah of a source's rest counts for exactly one project, replayed from the source's rows in recorded order, pure.

function creditCommand(array $creates = [], array $ends = [], string $contra = '0.00'): array
{
    return ['contra' => $contra, 'ends' => $ends, 'creates' => $creates];
}

function creditCreate(int $id, string $amount, ?int $project = null, string $target = 'INVOICE', ?int $supersedes = null): array
{
    return ['id' => $id, 'target' => $target, 'project' => $project, 'amount' => $amount, 'supersedes' => $supersedes];
}

function creditEnd(int $id, string $reason, string $amount, ?int $project = null, string $reapplied = '0.00'): array
{
    return ['id' => $id, 'reason' => $reason, 'project' => $project, 'amount' => $amount, 'reapplied' => $reapplied];
}

it('FX-01 3: counts a 1,200,000 payment\'s 200,000 rest against a 1,000,000 invoice for that invoice\'s project (annex 3; BR-FIN-05; criterion 1)', function () {
    $replayed = CreditAttribution::replay('1200000.00', [creditCommand([creditCreate(1, '1000000.00', 7)])]);

    expect($replayed['projects'])->toBe([7 => ['arisen' => '200000.00', 'left' => '200000.00']])->and($replayed['unattributed'])->toBe('0.00');
});

it('counts a source with no application for no project (DIR-104)', function () {
    expect(CreditAttribution::replay('500000.00', []))->toBe(['projects' => [], 'unattributed' => '500000.00']);
});

it('spends a refund from the oldest credit, its contra giving it back to the projects it took it from as arising again (CM-38; DIR-104)', function () {
    $refunded = [creditCommand([creditCreate(1, '1000000.00', 7)]), creditCommand([creditCreate(2, '200000.00', null, 'REFUND')])];

    expect(CreditAttribution::replay('1200000.00', $refunded)['projects'])->toBe([7 => ['arisen' => '200000.00', 'left' => '0.00']])
        ->and(CreditAttribution::replay('1200000.00', [...$refunded, creditCommand([], [creditEnd(2, 'REFUND_CONTRA', '200000.00')])])['projects'])->toBe([7 => ['arisen' => '400000.00', 'left' => '200000.00']]);
});

it('blocks only the project a payment\'s rest counts for when it pays two, and makes a released application its own project\'s credit (criterion 8)', function () {
    $across = creditCommand([creditCreate(1, '300.00', 7), creditCreate(2, '200.00', 8)]);

    expect(CreditAttribution::replay('1000.00', [$across])['projects'])->toBe([7 => ['arisen' => '500.00', 'left' => '500.00']])
        ->and(CreditAttribution::replay('1000.00', [$across, creditCommand([], [creditEnd(2, 'INVOICE_CORRECTED', '200.00', 8)])])['projects'])
        ->toBe([7 => ['arisen' => '500.00', 'left' => '500.00'], 8 => ['arisen' => '200.00', 'left' => '200.00']]);
});

it('counts what a reallocation does not re-apply as released, and an application ended by its source\'s contra as none, the unapplied part of the contra taking the oldest credit (CM-19; CM-21)', function () {
    $paid = creditCommand([creditCreate(1, '300.00', 7)]);
    $moved = creditCommand([creditCreate(2, '100.00', 9, 'INVOICE', 1)], [creditEnd(1, 'REALLOCATED', '300.00', 7, '100.00')]);

    expect(CreditAttribution::replay('1000.00', [$paid, $moved])['projects'])->toBe([7 => ['arisen' => '900.00', 'left' => '900.00']])
        ->and(CreditAttribution::replay('1000.00', [$paid, creditCommand([], [creditEnd(1, 'SOURCE_CONTRA', '300.00', 7)], '1000.00')])['projects'])->toBe([7 => ['arisen' => '700.00', 'left' => '0.00']]);
});

it('spends a later application from the oldest credit, lowering what is left and never what has arisen (DIR-104)', function () {
    $replayed = CreditAttribution::replay('1000.00', [creditCommand([creditCreate(1, '400.00', 7)]), creditCommand([creditCreate(2, '250.00', 8)])]);

    expect($replayed['projects'])->toBe([7 => ['arisen' => '600.00', 'left' => '350.00']])->and($replayed['unattributed'])->toBe('0.00');
});

it('takes only a partial contra\'s disposed part as applied, the unapplied rest of the contra taking the oldest credit (CM-19; ER-06)', function () {
    $paid = creditCommand([creditCreate(1, '60.00', 7)]);
    // A contra of 60 moving the whole 60 to a re-recorded payment leaves the 40 of credit standing.
    $moved = creditCommand([], [creditEnd(1, 'RE_RECORDED', '60.00', 7)], '60.00');
    // A contra of 50 releasing 10 of the 60, its 50 successor staying on the source, takes the 40 of credit.
    $released = creditCommand([creditCreate(2, '50.00', 7, 'INVOICE', 1)], [creditEnd(1, 'SOURCE_CONTRA', '60.00', 7, '50.00')], '50.00');

    expect(CreditAttribution::replay('100.00', [$paid, $moved])['projects'])->toBe([7 => ['arisen' => '40.00', 'left' => '40.00']])
        ->and(CreditAttribution::replay('100.00', [$paid, $released])['projects'])->toBe([7 => ['arisen' => '40.00', 'left' => '0.00']]);
});

it('counts a re-recorded payment\'s moved application as its own first application (CM-19; ER-06)', function () {
    $replayed = CreditAttribution::replay('100.00', [creditCommand([creditCreate(5, '60.00', 7)])]);

    expect($replayed['projects'])->toBe([7 => ['arisen' => '40.00', 'left' => '40.00']]);
});

it('gives back a partial refund contra\'s part only, the rest staying with the remaining backing (CM-38; ER-06)', function () {
    $refunded = [creditCommand([creditCreate(1, '1000.00', 7)]), creditCommand([creditCreate(2, '100.00', null, 'REFUND')])];
    $partial = creditCommand([creditCreate(3, '70.00', null, 'REFUND', 2)], [creditEnd(2, 'REFUND_CONTRA', '100.00', null, '70.00')]);
    $rest = creditCommand([], [creditEnd(3, 'REFUND_CONTRA', '70.00')]);

    expect(CreditAttribution::replay('1100.00', [...$refunded, $partial])['projects'])->toBe([7 => ['arisen' => '130.00', 'left' => '30.00']])
        ->and(CreditAttribution::replay('1100.00', [...$refunded, $partial, $rest])['projects'])->toBe([7 => ['arisen' => '200.00', 'left' => '100.00']]);
});

it('keeps a refund\'s trail through a partial source contra, its contra giving the rest back to the project it came from (CM-19; CM-38; Codex)', function () {
    $commands = [
        creditCommand([creditCreate(1, '100.00', 7)]),
        creditCommand([], [creditEnd(1, 'INVOICE_CORRECTED', '100.00', 7)]),
        creditCommand([creditCreate(2, '100.00', null, 'REFUND')]),
        // The payment's contra of 30 leaves the refund 30 short: its backing of 70 stays with the successor.
        creditCommand([creditCreate(3, '70.00', null, 'REFUND', 2)], [creditEnd(2, 'SOURCE_CONTRA', '100.00', null, '70.00')], '30.00'),
        creditCommand([], [creditEnd(3, 'REFUND_CONTRA', '70.00')]),
    ];

    expect(CreditAttribution::replay('100.00', $commands)['projects'])->toBe([7 => ['arisen' => '170.00', 'left' => '70.00']]);
});

it('spends money a refund\'s contra gave back before the first invoice application, and attributes its rest then (Codex)', function () {
    $commands = [
        creditCommand([creditCreate(1, '100.00', null, 'REFUND')]),
        creditCommand([], [creditEnd(1, 'REFUND_CONTRA', '100.00')]),
        creditCommand([creditCreate(2, '60.00', 7)]),
    ];

    expect(CreditAttribution::replay('100.00', $commands))->toBe(['projects' => [7 => ['arisen' => '40.00', 'left' => '40.00']], 'unattributed' => '0.00']);
});
