<?php

use App\Modules\PUR\Data\ReturnSettlement;
use App\Support\Money;

// TL-03 (TASK-044; WF-PUR-03; NX-16; C-24; C-46; annex 12, annex 14): the closure equation and the unrecovered share's split, pure.

it('closes only when returned cost = refunded + replaced + unrecovered: annex 12\'s four returnable units at annex 14\'s 102,500 a piece', function () {
    $returned = Money::of('410000.00');

    expect(ReturnSettlement::open($returned, Money::of('200000.00'), Money::of('102500.00'), Money::zero())->toString())->toBe('107500.00')
        ->and(ReturnSettlement::open($returned, Money::of('200000.00'), Money::of('102500.00'), Money::of('107500.00'))->isZero())->toBeTrue()
        ->and(ReturnSettlement::open($returned, Money::of('200000.00'), Money::of('102500.00'), Money::of('107500.01'))->isNegative())->toBeTrue();
});

it('spreads the unrecovered share over the consumptions in order, each never above what it carries, the ones left untouched out', function () {
    $carried = [11 => Money::of('102500.00'), 12 => Money::of('102500.00'), 13 => Money::of('102500.00'), 14 => Money::of('102500.00')];
    $parts = ReturnSettlement::split(Money::of('107500.00'), $carried);

    expect(array_map(fn (Money $part): string => $part->toString(), $parts ?? []))->toBe([11 => '102500.00', 12 => '5000.00'])
        ->and(array_map(fn (Money $part): string => $part->toString(), ReturnSettlement::split(Money::of('410000.00'), $carried) ?? []))->toBe([11 => '102500.00', 12 => '102500.00', 13 => '102500.00', 14 => '102500.00']);
});

it('skips a consumption that carries nothing and refuses an amount above what all of them carry (C-24)', function () {
    $carried = [21 => Money::zero(), 22 => Money::of('2500.00')];

    expect(array_map(fn (Money $part): string => $part->toString(), ReturnSettlement::split(Money::of('2500.00'), $carried) ?? []))->toBe([22 => '2500.00'])
        ->and(ReturnSettlement::split(Money::of('2500.01'), $carried))->toBeNull();
});
