<?php

use App\Http\Middleware\RequireStepUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-057; INT-272–INT-276; IG-05; CI-05; AU-15; criterion 13): each NX-13 command replayed with its `command_id` returns its recorded outcome and
// writes nothing again; INT-275's step-up expires and asks again.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
});

afterEach(fn () => @unlink($this->hotFile));

it('replays "Siapkan Batch", the dry run, a sign-off and the commit by their command_id, one batch and one commit request (INT-272–INT-275; CI-05)', function () {
    $world = ImportWorld::make();
    $file = $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']]);
    $prepare = (string) Str::uuid();
    $first = $world->prepare('LOCATION', $file, commandId: $prepare);
    $again = $world->prepare('LOCATION', $file, commandId: $prepare);
    expect($again->kind)->toBe('COMMITTED')->and($again->resultId)->toBe($first->resultId)->and(DB::table('import_batches')->count())->toBe(1);

    $batch = ImportWorld::batch($first);
    $world->dryRun($batch);
    $world->signOff($batch, $world->admin->id);
    $world->signOff($batch, $world->owner->id);
    $commit = (string) Str::uuid();
    expect($world->commit($batch, commandId: $commit)->kind)->toBe('COMMITTED')
        ->and($world->commit($batch, commandId: $commit)->kind)->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'IMPORT.CommitRequested')->count())->toBe(1)
        ->and(DB::table('locations')->where('code', 'RAK-1')->count())->toBe(1);
});

it('asks the step-up again once it has expired before "Jalankan Impor" and "Batalkan Batch" (INT-275; INT-276; AU-15; MSG-27)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])));
    $expired = time() - (int) config('auth.password_timeout', 900) - 60;

    foreach (['terapkan', 'batalkan'] as $step) {
        $this->actingAs($world->owner)->withSession([RequireStepUp::CONFIRMED_AT => $expired])
            ->postJson("/data-master/impor/{$batch->public_id}/{$step}", ['command_id' => (string) Str::uuid(), 'alasan' => 'Sumber keliru'])
            ->assertStatus(423)->assertJsonPath('message', __('messages.msg_27'));
    }

    expect($batch->refresh()->state)->toBe('PREPARED');
});
