<?php

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Tests\Support\Clock;

// TL-03 (TASK-005): DATABASE §17's business date is the WIB calendar day of an instant the application
// records in UTC (BR-DT-05, BR-DT-06).

it('gives the WIB day of an instant, which runs seven hours ahead of UTC', function (string $instant, string $day) {
    expect(BusinessDate::of(CarbonImmutable::parse($instant)))->toBe($day);
})->with([
    'late evening in UTC is already tomorrow in WIB' => ['2026-10-20T17:30:00Z', '2026-10-21'],
    'the last second of a WIB day' => ['2026-10-20T16:59:59Z', '2026-10-20'],
    'the first second of a WIB day' => ['2026-10-20T17:00:00Z', '2026-10-21'],
    'an instant given in WIB' => ['2026-10-21T00:15:00+07:00', '2026-10-21'],
]);

it('takes today from the application clock, in WIB while UTC is still on the previous day (FX-08)', function () {
    Clock::freeze('utc-previous-day');

    expect(BusinessDate::today())->toBe('2026-02-01')
        ->and(CarbonImmutable::now()->utc()->toDateString())->toBe('2026-01-31')
        ->and(config('app.timezone'))->toBe('UTC');
});
