<?php

use App\Modules\FUL\Actions\RecordHandover;
use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\ProjectLine;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\HandoverWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-047; QB-02; QB-13; PFM-01; PFM-02; PFM-20): PG-033 reads a page in the same number of statements for one handover
// and a full page of them, and for one line and ten; "Catat Serah Terima" of ten lines stays within the reference command's forty,
// growing by one statement per locked row only (QB-13: LR-05's multi-row lock form awaits HO-13) and never per row read or written.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = HandoverWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
});

afterEach(fn () => @unlink($this->hotFile));

function handoverStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** A confirmed project of `lines` service lines. */
function handoverProject(object $test, int $lines): object
{
    return $test->world->project(array_fill(0, $lines, 'SERVICE'), '1000');
}

/** @return list<array{baris: string, jumlah: string, lingkup: string}> */
function handoverBody(object $project, string $quantity): array
{
    return array_values(array_map(fn (int $line): array => ['baris' => RowHandle::of('project_lines', $line), 'jumlah' => $quantity, 'lingkup' => 'Pekerjaan selesai'],
        ProjectLine::query()->where('project_id', $project->id)->orderBy('line_no')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));
}

it('reads PG-033 in the same number of statements for one handover and a full page, one line and ten (QB-02; PFM-01; PFM-02)', function () {
    $few = handoverProject($this, 1);
    $many = handoverProject($this, 10);
    $this->actingAs($this->actor)->post("/proyek/{$few->public_id}/serah-terima", ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'baris' => handoverBody($few, '1')]);

    foreach (range(1, 25) as $round) {
        $this->actingAs($this->actor)->post("/proyek/{$many->public_id}/serah-terima", ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'baris' => handoverBody($many, (string) $round)]);
    }

    expect(DB::table('service_handovers')->where('project_id', $many->id)->count())->toBe(25);
    $one = handoverStatements(fn () => $this->actingAs($this->actor)->get("/proyek/{$few->public_id}/serah-terima")->assertOk());
    $page = handoverStatements(fn () => $this->actingAs($this->actor)->get("/proyek/{$many->public_id}/serah-terima")->assertOk());

    expect($page)->toBe($one);
});

it('writes a handover of ten lines within forty statements, one more per locked row only (QB-13; PFM-20)', function () {
    $few = handoverProject($this, 1);
    $many = handoverProject($this, 10);
    $record = app(RecordHandover::class);
    $lines = fn (object $project): array => ProjectLine::query()->where('project_id', $project->id)->orderBy('line_no')->pluck('id')
        ->map(fn (mixed $id): array => ['line' => (int) $id, 'quantity' => '1', 'scope' => 'Pekerjaan selesai'])->all();
    $run = fn (object $project): int => handoverStatements(fn () => expect($record->run((string) Str::uuid(), $this->world->actorId, new ProjectRef($project->id, $project->company_id, $project->public_id), BusinessDate::today(), false, 'Bu Sari', $lines($project), null, [], null, ['project' => $project->public_id]))->toHaveProperty('kind', 'COMMITTED'));

    // Reading the lines' ids is the test's own statement, once each.
    $one = $run($few);
    $ten = $run($many);

    expect($ten - $one)->toBe(9)->and($ten)->toBeLessThanOrEqual(40);
});
