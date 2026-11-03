<?php

use App\Modules\FIN\Data\CreditAttribution;

// TL-03 (TASK-053; WF-FIN-06; DIR-104; APPR-028; FX-01 3): each rupiah of a source's rest counts for exactly one project, replayed from the source's rows in recorded order, pure.

function creditCommand(array $creates = [], array $ends = [], string $contra = '0.00'): array
{
    return ['contra' => $contra, 'ends' => $ends, 'creates' => $creates];
}

function creditCreate(int $id, string $amount, ?int $project = null, string $target = 'INVOICE', bool $successor = false): array
{
    return ['id' => $id, 'target' => $target, 'project' => $project, 'amount' => $amount, 'successor' => $successor];
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
    $moved = creditCommand([creditCreate(2, '100.00', 9, 'INVOICE', true)], [creditEnd(1, 'REALLOCATED', '300.00', 7, '100.00')]);

    expect(CreditAttribution::replay('1000.00', [$paid, $moved])['projects'])->toBe([7 => ['arisen' => '900.00', 'left' => '900.00']])
        ->and(CreditAttribution::replay('1000.00', [$paid, creditCommand([], [creditEnd(1, 'SOURCE_CONTRA', '300.00', 7)], '1000.00')])['projects'])->toBe([7 => ['arisen' => '700.00', 'left' => '0.00']]);
});

it('spends a later application from the oldest credit, lowering what is left and never what has arisen (DIR-104)', function () {
    $replayed = CreditAttribution::replay('1000.00', [creditCommand([creditCreate(1, '400.00', 7)]), creditCommand([creditCreate(2, '250.00', 8)])]);

    expect($replayed['projects'])->toBe([7 => ['arisen' => '600.00', 'left' => '350.00']])->and($replayed['unattributed'])->toBe('0.00');
});
