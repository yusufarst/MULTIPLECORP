<?php

use App\Modules\INV\Data\CountVariance;
use Brick\Math\BigDecimal;

// TL-03 (TASK-043; WF-INV-05; AX-06; annex 7–9; C-37; §5.8): the variance's split and the bounds, pure; no rule here chooses a company.

it('finds the pending cases first and exactly, then the identified losses, the rest one finding', function () {
    $order = CountVariance::surplus(BigDecimal::of('5'), [7 => BigDecimal::of('2'), 9 => BigDecimal::of('1')], BigDecimal::of('1'));

    expect(array_map('strval', $order['found']))->toBe([7 => '2', 9 => '1'])
        ->and((string) $order['restored'])->toBe('1')
        ->and((string) $order['finding'])->toBe('1');
});

it('stops at the surplus: a case found in part, nothing left for a finding', function () {
    $order = CountVariance::surplus(BigDecimal::of('1'), [7 => BigDecimal::of('2')], BigDecimal::zero());

    expect(array_map('strval', $order['found']))->toBe([7 => '1'])->and($order['finding']->isZero())->toBeTrue();
});

it('refuses more identified than the surplus leaves', function () {
    expect(CountVariance::surplus(BigDecimal::of('2'), [7 => BigDecimal::of('2')], BigDecimal::of('1')))->toBeNull();
});

it('takes a shortage from the finding the basis carried, what is left of it stays', function () {
    $small = CountVariance::shortage(BigDecimal::of('1'), BigDecimal::of('3'));
    $large = CountVariance::shortage(BigDecimal::of('5'), BigDecimal::of('3'));

    expect((string) $small['shortage'])->toBe('0')->and((string) $small['withdrawn'])->toBe('1')->and((string) $small['remaining'])->toBe('2')
        ->and((string) $large['shortage'])->toBe('2')->and((string) $large['withdrawn'])->toBe('3')->and((string) $large['remaining'])->toBe('0');
});

it('covers a shortage only exactly', function () {
    expect(CountVariance::covers(BigDecimal::of('3'), BigDecimal::of('1'), BigDecimal::of('1'), BigDecimal::of('1')))->toBeTrue()
        ->and(CountVariance::covers(BigDecimal::of('3'), BigDecimal::of('1'), BigDecimal::of('1'), BigDecimal::zero()))->toBeFalse()
        ->and(CountVariance::covers(BigDecimal::of('3'), BigDecimal::of('4'), BigDecimal::zero(), BigDecimal::zero()))->toBeFalse();
});

it('raises a group bound to cover a member over what is assigned, never lowers it (§5.8)', function () {
    expect((string) CountVariance::groupBound(BigDecimal::zero(), BigDecimal::of('5'), BigDecimal::zero()))->toBe('5')
        ->and((string) CountVariance::groupBound(BigDecimal::of('5'), BigDecimal::of('5'), BigDecimal::zero()))->toBe('5')
        ->and((string) CountVariance::groupBound(BigDecimal::of('5'), BigDecimal::of('3'), BigDecimal::of('4')))->toBe('7')
        ->and((string) CountVariance::groupBound(BigDecimal::of('9'), BigDecimal::of('3'), BigDecimal::of('4')))->toBe('9');
});

it('keeps an assignment within the case\'s exposure and the group\'s bound: A cannot absorb 7 of the 5 it had (C-37; §5.8\'s example)', function () {
    expect(CountVariance::withinBounds(BigDecimal::of('2'), BigDecimal::of('5'), BigDecimal::zero(), BigDecimal::of('5'), BigDecimal::of('3')))->toBeTrue()
        ->and(CountVariance::withinBounds(BigDecimal::of('2'), BigDecimal::of('5'), BigDecimal::zero(), BigDecimal::of('5'), BigDecimal::of('5')))->toBeFalse()
        ->and(CountVariance::withinBounds(BigDecimal::of('6'), BigDecimal::of('5'), BigDecimal::zero(), BigDecimal::of('9'), BigDecimal::zero()))->toBeFalse();
});

it('leaves the remaining bounds covering what is pending: the transportation argument on §5.8\'s example, so a resolution never deadlocks', function () {
    $claims = ['A' => 5, 'B' => 5];
    $cases = [2, 3, 2];
    $bound = ['A' => BigDecimal::zero(), 'B' => BigDecimal::zero()];
    $assigned = ['A' => BigDecimal::zero(), 'B' => BigDecimal::zero()];

    foreach ($cases as $index => $pending) {
        foreach ($claims as $company => $qty) {
            $bound[$company] = CountVariance::groupBound($bound[$company], BigDecimal::of($qty), $assigned[$company]);
        }

        // Whatever is assigned so far, what remains of the bounds covers what remains pending.
        $remaining = BigDecimal::of(array_sum(array_slice($cases, $index + 1)) + $pending);
        $room = $bound['A']->minus($assigned['A'])->plus($bound['B'])->minus($assigned['B']);
        expect($room->isGreaterThanOrEqualTo($remaining))->toBeTrue();
        // What is assigned closes its claims: the next member's snapshot sees them gone.
        $take = BigDecimal::min(BigDecimal::of($pending), $bound['A']->minus($assigned['A']));
        $assigned['A'] = $assigned['A']->plus($take);
        $claims['A'] -= (int) (string) $take;
    }

    expect((string) $assigned['A'])->toBe('5');
});
