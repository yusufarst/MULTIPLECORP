<?php

use App\Modules\PRJ\Actions\CancelProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-15 (TASK-056; PFM-20; QB-14; AX-27, retry class B): "Batalkan Proyek" writes in statements proportional to the reservations it releases — each further one adding the same —,
// never a restart (SQ-20).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
    $this->world->stock('60');
    $this->actor = Authorization::admin([$this->world->company->id], ['projects.view', 'projects.cancel'])->id;
});

/** The statements of one cancellation of a project with `$reserved` reserved warehouse lines. */
function cancellationStatements(object $test, int $reserved): int
{
    $project = $test->world->project(array_fill(0, $reserved, 'WAREHOUSE'), '5');

    foreach (range(1, $reserved) as $number) {
        expect(ReservationWorld::reserve($test->world->actorId, ReservationWorld::line($project, $number), '1'))->toBe('COMMITTED');
    }

    $project->refresh();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $outcome = app(CancelProject::class)->run((string) Str::uuid(), $test->actor, $project->id, $project->company_id, $project->public_id, $project->lock_version, 'Klien membatalkan kontrak');
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($outcome->kind)->toBe('COMMITTED');

    return $count;
}

it('cancels in statements proportional to the reservations it releases (PFM-20; QB-14)', function () {
    // The first command of the process warms what later ones only read.
    cancellationStatements($this, 1);
    $one = cancellationStatements($this, 1);
    $two = cancellationStatements($this, 2);
    $three = cancellationStatements($this, 3);

    expect($three - $two)->toBe($two - $one)->and($two - $one)->toBeGreaterThan(0);
});
