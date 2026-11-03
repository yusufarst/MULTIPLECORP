<?php

use App\Modules\INV\Services\ReceivingRecords;
use App\Support\Command\Refused;
use App\Support\Costing\CostSplitter;
use App\Support\Money;

// TL-03 (TASK-048; L-31; BR-INV-03; BR-FIN-13; FX-01 10): a client's return and its reversal take their cost by the consumption's or the restoration's standing share, the restoration that zeroes it the residual; an alternate-unit entry converts at the cited snapshot, pure.

it('contras a third of a 1,000 consumption as 333 and the two thirds that zero it as the residual 667, the parts summing to the whole (L-31; criterion 2)', function () {
    $first = CostSplitter::draw(Money::of('1000.00'), '1', '3');
    $rest = CostSplitter::draw(Money::of('1000.00')->minus($first), '2', '2');

    expect($first->toString())->toBe('333.00')
        ->and($rest->toString())->toBe('667.00')
        ->and($first->plus($rest)->toString())->toBe('1000.00');
});

it('takes back a reversal\'s share of a restoration\'s standing contra, the one that zeroes it exactly what is left (CM-37; C-55; criterion 7)', function () {
    $contra = Money::of('667.00');
    $part = CostSplitter::draw($contra, '1', '2');

    expect($part->toString())->toBe('334.00')
        ->and(CostSplitter::draw($contra->minus($part), '1', '1')->toString())->toBe('333.00');
});

it('refuses a reversal above what the restoration still stands at', function () {
    CostSplitter::draw(Money::of('100.00'), '3', '2');
})->throws(InvalidArgumentException::class);

it('converts 2 boxes of 12 at the cited snapshot to 24 in the base unit, and refuses a fraction the base unit cannot hold (BR-INV-03; C-09)', function () {
    expect((string) ReceivingRecords::base('2', 12, 0))->toBe('24')
        ->and((string) ReceivingRecords::base('1.5', 12, 0))->toBe('18.0');

    ReceivingRecords::base('0.5', 3, 0);
})->throws(Refused::class);
