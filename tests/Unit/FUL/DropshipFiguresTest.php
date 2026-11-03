<?php

use App\Support\Costing\CostSplitter;
use App\Support\Costing\ProportionalContra;
use App\Support\Money;

// TL-03 (TASK-046; annex 14; BR-FIN-13; L-18; CM-14; C-08): a drop-ship line's cost by cumulative rounding over partial
// confirmations, and a return lot's basis — the HPP contra at the confirmation's current net cost —, pure.

it('shares a partial confirmation by cumulative rounding to Rp1, the confirmation completing the line taking what is left (annex 14; BR-FIN-13)', function () {
    $value = Money::of('100000.00');
    $first = CostSplitter::target($value, '1', '3');
    $second = CostSplitter::target($value, '2', '3')->minus($first);
    $third = CostSplitter::target($value, '3', '3')->minus(CostSplitter::target($value, '2', '3'));

    expect($first->toString())->toBe('33333.00')->and($second->toString())->toBe('33334.00')->and($third->toString())->toBe('33333.00')
        ->and($first->plus($second)->plus($third)->toString())->toBe('100000.00');
});

it('keeps a line\'s cents on the confirmation that completes it (C-57)', function () {
    $value = Money::of('300000.03');

    expect(CostSplitter::target($value, '1', '3')->toString())->toBe('100000.00')
        ->and($value->minus(CostSplitter::target($value, '1', '3'))->toString())->toBe('200000.03');
});

it('bases a return lot on the proportional contra of the line\'s net cost, the return zeroing the line taking exactly what is left (CM-14; C-08; SF-RESTORE)', function () {
    $net = Money::of('400000.04');
    $first = ProportionalContra::of($net, '3', '4');
    $rest = ProportionalContra::of($net->minus($first), '1', '1');

    expect($first->toString())->toBe('300000.00')->and($rest->toString())->toBe('100000.04')
        ->and($first->plus($rest)->toString())->toBe('400000.04');
});
