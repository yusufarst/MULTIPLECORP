<?php

use Carbon\CarbonImmutable;
use Tests\Support\Clock;
use Tests\Support\FixtureSets;

// FX-08 (CONCURRENCY_IDEMPOTENCY HO-19): the controlled clock freezes now() on WIB instants.

it('freezes now on the second before WIB midnight, which is still the last day of the month', function () {
    $now = Clock::freeze('before-wib-midnight');

    expect($now->toIso8601String())->toBe('2026-01-31T23:59:59+07:00')
        ->and(now()->equalTo($now))->toBeTrue()
        ->and(CarbonImmutable::now()->equalTo($now))->toBeTrue()
        ->and($now->utc()->toIso8601String())->toBe('2026-01-31T16:59:59+00:00')
        ->and($now->addSecond()->toDateString())->toBe('2026-02-01');
});

it('keeps the WIB date where the UTC date still lags a day', function () {
    $now = Clock::freeze('utc-previous-day');

    expect($now->toDateString())->toBe('2026-02-01')
        ->and($now->utc()->toDateString())->toBe('2026-01-31');
});

it('names a backdated issue and periods seeded and unseeded before, between and inside a coarser one', function () {
    $set = FixtureSets::load('FX-08');
    $backdated = $set['entries']['backdated'];
    $periods = collect($set['entries']['periods']);

    expect(CarbonImmutable::parse($backdated['recorded_at'])->toDateString())->toBeGreaterThan($backdated['business_date'])
        ->and($periods->where('seeded', true)->pluck('period')->all())->toBe(['2026-02', '2026-04', '2027'])
        ->and($periods->where('seeded', false)->pluck('period')->all())->toBe(['2026-01', '2026-03', '2027-03']);
});

it('refuses an instant FX-08 does not name', function () {
    expect(fn () => Clock::freeze('tomorrow'))->toThrow(InvalidArgumentException::class);
});
