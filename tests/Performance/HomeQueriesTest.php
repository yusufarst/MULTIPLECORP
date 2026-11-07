<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\Reporting\Queries\Beranda;
use App\Modules\Reporting\Queries\HomeGroups;
use App\Modules\Reporting\Queries\WorkQueue;
use App\Modules\Reporting\Signals\Context;
use App\Modules\Reporting\Signals\QS20RoutedRefusals;
use App\Modules\Reporting\Signals\QS21AcknowledgedDuplicates;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-15 (TASK-058; QB-10; PF-24; PF-43; PERFORMANCE §12; DIR-088): each home group within QB-10's statements — twelve
// per signal group, the ten completion predicates of QS-15 a group of their own, seven for G5 with the receivables
// position —, and the review signals reading IX-13's partial indexes.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
});

function homeStatements(Closure $read): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    $read();

    return $count;
}

function homeContextOf(int $userId): Context
{
    return app(HomeGroups::class)->context(app(EffectiveCapabilities::class)->for($userId), null);
}

it('reads each group of Beranda and Pekerjaan within QB-10 on two companies with open work (QB-10; PF-43)', function () {
    $first = ReservationWorld::make();
    $second = ReservationWorld::make($first);

    foreach ([$first, $second] as $world) {
        $world->project(['WAREHOUSE'], '5');
        $world->project(['WAREHOUSE'], '2');
    }

    $owner = homeContextOf(Authorization::owner()->id);
    $admin = homeContextOf($first->actorId);
    $beranda = app(Beranda::class);
    $work = app(WorkQueue::class);

    expect(homeStatements(fn () => $beranda->attention($owner)))->toBeLessThanOrEqual(10)
        ->and(homeStatements(fn () => $beranda->operations($owner)))->toBeLessThanOrEqual(12)
        ->and(homeStatements(fn () => $beranda->billing($owner)))->toBeLessThanOrEqual(8)
        ->and(homeStatements(fn () => $beranda->completion($owner)))->toBeLessThanOrEqual(12)
        ->and(homeStatements(fn () => $beranda->figures($owner)))->toBeLessThanOrEqual(7)
        ->and(homeStatements(fn () => $work->goods($admin)))->toBeLessThanOrEqual(12)
        ->and(homeStatements(fn () => $work->documents($admin)))->toBeLessThanOrEqual(6)
        ->and(homeStatements(fn () => $work->billing($admin)))->toBeLessThanOrEqual(8)
        ->and(homeStatements(fn () => $work->completion($admin)))->toBeLessThanOrEqual(12);
});

it('plans QS-20 and QS-21 on IX-13\'s partial indexes over the routed and the acknowledged codes (DIR-088; PERFORMANCE §12)', function () {
    $world = ReservationWorld::make();
    $owner = homeContextOf(Authorization::owner()->id);
    DB::statement('analyze audit_events');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'audit_events')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    app(QS20RoutedRefusals::class)->read($owner);
    app(QS21AcknowledgedDuplicates::class)->read($owner);
    expect($queries)->toHaveCount(2);
    DB::statement('set local enable_seqscan = off');
    $plans = array_map(fn (array $query): string => implode("\n", array_map(fn (object $row): string => (string) array_values((array) $row)[0], DB::select('explain '.$query[0], $query[1]))), $queries);

    expect($plans[0])->toContain('audit_events_ix13_routed')->and($plans[1])->toContain('audit_events_ix13_acknowledged');
    expect($world->company->id)->toBeInt();
});
