<?php

use App\Support\Costing\ChargeAllocator;
use App\Support\Costing\CostSplitter;
use App\Support\Costing\ProportionalContra;
use App\Support\Money;

// TL-03 (TASK-036; FX-01; ARCHITECTURE §6; BR-FIN-13; GAP-004; GAP-025): CostSplitter, ChargeAllocator and
// ProportionalContra against the numeric annex 10, 12, 14, 15 and 16 and DATABASE §6.2's and §6.3's fixtures, value
// by value — exact decimals, the Rp1 increment half away from zero, the zeroing part taking what is left.

function costingMoney(Money $amount): string
{
    return $amount->toString();
}

it('rounds a share once to the Rp1 increment, half away from zero, as PostgreSQL rounds (GAP-004)', function () {
    expect(costingMoney(Money::of('100000.00')->share('5', '12')))->toBe('41667.00')
        ->and(costingMoney(Money::of('1.00')->share('1', '2')))->toBe('1.00')
        ->and(costingMoney(Money::of('-1.00')->share('1', '2')))->toBe('-1.00')
        ->and(costingMoney(Money::of('8333.00')->share('2', '5')))->toBe('3333.00')
        ->and(costingMoney(Money::of('41667.00')->share('2', '5')))->toBe('16667.00')
        ->and(fn () => Money::of('1.005'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::of('1.00')->share('1', '0'))->toThrow(InvalidArgumentException::class);
});

it('draws a box of 12 for 100,000 as 41,667 then 58,333, the zeroing draw taking the rest (annex 10; DATABASE §6.2)', function () {
    $lot = Money::of('100000.00');
    $first = CostSplitter::draw($lot, '5.000', '12.000');
    $second = CostSplitter::draw($lot->minus($first), '7.000', '7.000');

    expect([costingMoney($first), costingMoney($second), costingMoney($first->plus($second))])->toBe(['41667.00', '58333.00', '100000.00'])
        ->and(fn () => CostSplitter::draw($lot, '13', '12'))->toThrow(InvalidArgumentException::class);
});

it('posts a box\'s partial receipts by cumulative target, the completing receipt taking the remainder (annex 10; DATABASE §6.1)', function () {
    $value = Money::of('100000.00');
    $first = CostSplitter::target($value, '5', '12');
    $second = CostSplitter::target($value, '12', '12')->minus($first);

    expect([costingMoney($first), costingMoney($second)])->toBe(['41667.00', '58333.00']);
});

it('contras X\'s partial returns over its open quantity, the lot\'s cost conserved (DATABASE §6.2, P3 scenario 18; L-31)', function () {
    $x = Money::of('41667.00');
    $firstReturn = ProportionalContra::of($x, '2', '5');
    $lot = $firstReturn;
    $z = CostSplitter::draw($lot, '2', '2');
    $lastReturn = ProportionalContra::of($x->minus($firstReturn), '3', '3');
    $y = Money::of('58333.00');

    expect([costingMoney($firstReturn), costingMoney($z), costingMoney($lastReturn)])->toBe(['16667.00', '16667.00', '25000.00'])
        ->and(costingMoney(Money::sum($y, $z, $lastReturn)))->toBe('100000.00');
});

it('pins 6 of A\'s lot of 10 at 12,000 as 72,000 of HPP for B\'s project (annex 12)', function () {
    expect(costingMoney(CostSplitter::draw(Money::of('120000.00'), '6', '10')))->toBe('72000.00');
});

it('splits a freight of 100,000 on line value as 25,000 and 75,000, both lots 102,500 a piece (annex 14; DATABASE §6.1)', function () {
    $shares = ChargeAllocator::shares(Money::of('100000.00'), ['A' => '1000000.00', 'B' => '3000000.00']);

    expect(array_map(costingMoney(...), $shares))->toBe(['A' => '25000.00', 'B' => '75000.00'])
        ->and(costingMoney(Money::of('1000000.00')->plus($shares['A'])->share('1', '10')))->toBe('102500.00')
        ->and(costingMoney(Money::of('3000000.00')->plus($shares['B'])->share('1', '30')))->toBe('102500.00');
});

it('leaves a split\'s residual to the last weight, the parts summing to the whole (BR-FIN-13)', function () {
    $parts = CostSplitter::split(Money::of('100.00'), ['a' => '1', 'b' => '1', 'c' => '1']);

    expect(array_map(costingMoney(...), $parts))->toBe(['a' => '33.00', 'b' => '33.00', 'c' => '34.00'])
        ->and(costingMoney(Money::sum(...array_values($parts))))->toBe('100.00')
        ->and(fn () => CostSplitter::split(Money::of('1.00'), ['a' => '0']))->toThrow(InvalidArgumentException::class);
});

it('spreads an amount over quantities by cumulative rounding, a zero quantity taking nothing (DATABASE §6.3 step 1)', function () {
    $parts = CostSplitter::spread(Money::of('100.00'), ['a' => '1', 'gone' => '0', 'b' => '1', 'c' => '1']);

    expect(array_map(costingMoney(...), $parts))->toBe(['a' => '33.00', 'gone' => '0.00', 'b' => '34.00', 'c' => '33.00'])
        ->and(costingMoney(Money::sum(...array_values($parts))))->toBe('100.00')
        ->and(array_map(costingMoney(...), CostSplitter::spread(Money::of('-8333.00'), ['lot' => '5'])))->toBe(['lot' => '-8333.00']);
});

it('reclassifies a unit lost in transit and contras the delivered one over its open quantity only (annex 15; DATABASE §6.2)', function () {
    $hpp = Money::of('100000.00');
    $reclassified = ProportionalContra::of($hpp, '1', '2');
    $restored = ProportionalContra::of($hpp->minus($reclassified), '1', '1');

    expect([costingMoney($reclassified), costingMoney($restored)])->toBe(['50000.00', '50000.00'])
        // Project cost stays 100,000 after the pair; the return takes the open unit's HPP and leaves the LOSS.
        ->and(costingMoney($hpp->minus($reclassified)->plus($reclassified)))->toBe('100000.00');
});

it('contras a damaged return of 50,000 into its lot and draws it once as the loss (annex 16)', function () {
    $contra = ProportionalContra::of(Money::of('50000.00'), '1', '1');
    $loss = CostSplitter::draw($contra, '1', '1');

    expect([costingMoney($contra), costingMoney($loss)])->toBe(['50000.00', '50000.00']);
});

it('corrects a box to 120,000 between receipts: +8,333 on the lot, +3,333 on the dispatch, 70,000 on the second receipt (DATABASE §6.3)', function () {
    $posted = CostSplitter::target(Money::of('100000.00'), '5', '12');
    $correction = CostSplitter::target(Money::of('120000.00'), '5', '12')->minus($posted);
    $dispatch = $correction->share('2', '5');
    $second = CostSplitter::target(Money::of('120000.00'), '12', '12')->minus($posted->plus($correction));

    expect([costingMoney($correction), costingMoney($dispatch), costingMoney($second)])->toBe(['8333.00', '3333.00', '70000.00'])
        ->and(costingMoney(Money::of('16667.00')->plus($dispatch)))->toBe('20000.00')
        ->and(costingMoney(Money::of('25000.00')->plus($correction)->minus($dispatch)))->toBe('30000.00');
});

it('allocates a share over the ordered quantity, never above what is pending (SF-CHARGE; L-47; AX-29)', function () {
    $share = Money::of('25000.00');

    expect(costingMoney(ChargeAllocator::posting($share, '6', '10', Money::zero(), Money::zero(), Money::zero())))->toBe('15000.00')
        ->and(costingMoney(ChargeAllocator::pending($share, Money::of('15000.00'), Money::zero(), Money::zero())))->toBe('10000.00')
        ->and(costingMoney(ChargeAllocator::posting($share, '10', '10', Money::of('15000.00'), Money::zero(), Money::zero())))->toBe('10000.00')
        // Re-received units after a reversal whose share was re-spread post nothing of it again.
        ->and(costingMoney(ChargeAllocator::posting($share, '10', '10', Money::of('20000.00'), Money::of('5000.00'), Money::zero())))->toBe('0.00')
        ->and(costingMoney(ChargeAllocator::posting($share, '10', '10', Money::of('15000.00'), Money::zero(), Money::of('10000.00'))))->toBe('0.00');
});
