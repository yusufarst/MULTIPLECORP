<?php

use App\Support\Backup\DiskPressure;
use App\Support\Backup\RetentionRule;
use Carbon\CarbonImmutable;

// TL-03 (TASK-074; BK-05, BK-06, BK-11, BK-12; BKR-04; DIR-079 CB-11; BACKUP_RECOVERY §3): the retention keeps the newest
// verified generation of each of the last 7 days, 4 weeks and 3 months, the newest verified one always, and the kept
// pre-deployment and pre-import generations; it never lets an unverified generation expire. The disk-pressure rule
// starts a run below the critical level when it fits, and at the critical level only when the space is there.

/** @return array{name: string, at: CarbonImmutable, kind: string, verified: bool} */
function retained(string $at, string $kind = 'daily', bool $verified = true): array
{
    $time = CarbonImmutable::parse($at, 'UTC');

    return ['name' => $time->format('Ymd\THis\Z').'-abc1234', 'at' => $time, 'kind' => $kind, 'verified' => $verified];
}

it('keeps 7 daily, 4 weekly and 3 monthly generations and lets the rest expire (CB-11; BK-05)', function () {
    $generations = [];

    // A daily generation at 20:00 UTC (03:00 WIB) for 120 days up to 2026-11-20.
    for ($day = 0; $day < 120; $day++) {
        $generations[] = retained(CarbonImmutable::parse('2026-11-19 20:00', 'UTC')->subDays($day)->toDateTimeString());
    }

    ['keep' => $keep, 'expire' => $expire] = (new RetentionRule(7, 4, 3, 'Asia/Jakarta'))->apply($generations);
    $kept = collect($keep)->sort()->values()->all();

    expect(count($keep) + count($expire))->toBe(120)
        // the last 7 business days
        ->and(array_slice(array_reverse($kept), 0, 7))->toBe(collect(range(0, 6))->map(fn (int $day): string => CarbonImmutable::parse('2026-11-19 20:00', 'UTC')->subDays($day)->format('Ymd\THis\Z').'-abc1234')->all())
        // the newest of each of the last 4 ISO weeks and 3 months add at most 7 more, and the oldest kept is in September
        ->and(count($keep))->toBeLessThanOrEqual(14)->toBeGreaterThan(7)
        ->and($kept[0])->toStartWith('202609');
});

it('always keeps the newest verified generation and never lets an unverified one expire (BK-12; BKR-04)', function () {
    $generations = [retained('2026-11-20 20:00', verified: false), retained('2026-01-01 20:00'), retained('2025-12-01 20:00', verified: false)];

    ['keep' => $keep, 'expire' => $expire] = (new RetentionRule(0, 0, 0, 'Asia/Jakarta'))->apply($generations);

    expect($keep)->toBe([retained('2026-01-01 20:00')['name']])->and($expire)->toBe([]);
});

it('keeps the latest pre-deployment generation until the next and a pre-import one until a newer pre-deployment one (BK-06)', function () {
    $generations = [
        retained('2026-11-20 20:00'),
        retained('2026-11-10 08:00', 'predeploy'),
        retained('2026-10-10 08:00', 'predeploy'),
        retained('2026-11-15 08:00', 'preimport'),
        retained('2026-09-01 08:00', 'preimport'),
    ];

    ['keep' => $keep, 'expire' => $expire] = (new RetentionRule(1, 0, 0, 'Asia/Jakarta'))->apply($generations);

    expect($keep)->toContain(retained('2026-11-10 08:00', 'predeploy')['name'], retained('2026-11-15 08:00', 'preimport')['name'])
        ->and($expire)->toContain(retained('2026-10-10 08:00', 'predeploy')['name'], retained('2026-09-01 08:00', 'preimport')['name'])
        ->not->toContain(retained('2026-11-20 20:00')['name']);
});

it('starts a run below the critical level when it fits, and at the critical level only with the space for it (BACKUP_RECOVERY §3)', function () {
    $gb = 1024 ** 3;

    $ok = DiskPressure::assess(200 * $gb, 100 * $gb, 80, 90, 5 * $gb);
    $warning = DiskPressure::assess(200 * $gb, 30 * $gb, 80, 90, 5 * $gb);
    $criticalFits = DiskPressure::assess(200 * $gb, 10 * $gb, 80, 90, 5 * $gb);
    $criticalShort = DiskPressure::assess(200 * $gb, 10 * $gb, 80, 90, 12 * $gb);
    $criticalUnknown = DiskPressure::assess(200 * $gb, 10 * $gb, 80, 90, 0);
    $full = DiskPressure::assess(200 * $gb, 2 * $gb, 99, 100, 5 * $gb);
    $lowered = DiskPressure::assess(200 * $gb, 150 * $gb, 1, 2, 5 * $gb);

    expect([$ok->level, $ok->mayStart, $ok->usedPct])->toBe([DiskPressure::OK, true, 50])
        ->and([$warning->level, $warning->mayStart])->toBe([DiskPressure::WARNING, true])
        ->and([$criticalFits->level, $criticalFits->mayStart])->toBe([DiskPressure::CRITICAL, true])
        ->and([$criticalShort->level, $criticalShort->mayStart])->toBe([DiskPressure::CRITICAL, false])
        ->and($criticalUnknown->mayStart)->toBeFalse()
        ->and([$full->level, $full->mayStart])->toBe([DiskPressure::WARNING, false])
        ->and($lowered->level)->toBe(DiskPressure::CRITICAL);
});
