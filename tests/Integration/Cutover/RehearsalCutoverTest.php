<?php

use App\Modules\ImportOrchestration\Services\RehearsalBatches;
use App\Modules\ORG\Services\GoLiveRecording;
use App\Modules\ORG\Services\NumberingGoLive;
use App\Modules\ORG\Services\NumberingState;
use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-077; IMP-13; HO-32; HO-36; CUT-05–CUT-07; TEST_STRATEGY §15's numbering at the cutover), on real PostgreSQL
// with TASK-057's import: the rehearsal generator's batches, made from counts alone, pass the import's own validation and
// commit in IMP-03's order with their seed rows, each commit's duration measurable; go-live stays unrecorded while they
// commit and is recorded by the operator's command after them, which the number allocator then reads; and the
// post-import refresh reports every registered hot query's plan on the imported data without a value of the data.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->goLiveFile = sys_get_temp_dir().'/cutover-'.bin2hex(random_bytes(6)).'/numbering-go-live';
});

afterEach(function () {
    File::deleteDirectory(dirname($this->goLiveFile));
});

it('commits the generated batches, records go-live after them and refreshes the plans on the imported data (criteria 2–5)', function () {
    $world = ImportWorld::make();
    // Go-live is the operator's, after the commit: while the batches commit, its store has no file.
    config(['numbering.go_live_file' => $this->goLiveFile]);
    app()->forgetInstance(NumberingGoLive::class);
    expect(app(NumberingState::class)->goLive())->toBeNull();

    $files = RehearsalBatches::generate(
        ['LOCATION' => 2, 'PRODUCT' => 6, 'CLIENT' => 3, 'SUPPLIER' => 2, 'OPENING_STOCK' => 8, 'OPEN_PROJECT' => 2, 'OPEN_PURCHASE' => 2, 'OPENING_RECEIVABLE' => 3],
        [$world->company->code], $world->cutoff, ['PROJECT' => 20, 'PURCHASE' => 20],
    );
    $durations = [];

    foreach ($files as $name => $csv) {
        $kind = (string) preg_replace('/^\d\d-(.+)-\d\d\.csv$/', '$1', $name);
        $batch = ImportWorld::batch($world->prepare($kind, ImportWorld::store($csv, $name, $world->admin->id)));
        $world->dryRun($batch);
        expect($batch->refresh()->state)->toBe('DRY_RUN_PASSED', "{$name}: ".json_encode(DB::table('import_rows')->where('import_batch_id', $batch->id)->whereIn('validation_state', ['INVALID', 'DUPLICATE'])->get(['row_number', 'import_identity', 'validation_state', 'errors'])->all()));
        $world->signOff($batch, $world->admin->id);
        $world->signOff($batch, $world->owner->id);
        $started = hrtime(true);
        $world->commit($batch);
        $durations[$kind] = intdiv(hrtime(true) - $started, 1_000_000);

        expect($batch->refresh()->state)->toBe('COMMITTED', $name)
            ->and(ImportWorld::counts($batch))->not->toHaveKey('INVALID');
    }

    expect(array_keys($durations))->toBe(RehearsalBatches::ORDER)
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->where('source_company_id', $world->company->id)->count())->toBe(8)
        ->and(DB::table('projects')->where('origin', 'OPENING')->count())->toBe(2)
        ->and(DB::table('purchases')->where('origin', 'OPENING')->count())->toBe(2)
        ->and(app(NumberingState::class)->goLive())->toBeNull();

    // CUT-07: every batch committed, so the operator's command records go-live, which the allocator now reads.
    $goLive = ((int) $world->year + 1).'-01-01';
    $this->artisan('numbering:go-live', ['day' => $goLive, '--operator' => 'Yusuf Operator'])->assertExitCode(0);

    expect(app(NumberingState::class)->goLive())->toBe($goLive)
        ->and(DB::table('audit_events')->where('action', GoLiveRecording::ACTION)->count())->toBe(1);

    // CUT-06's refresh, under the owner's login given to the run: every registered plan reported, no data value printed.
    config(['backup.database.username' => config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION.'.username'),
        'backup.database.password' => config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION.'.password')]);
    $this->artisan('ops:post-import-refresh')
        ->expectsOutputToContain('plans reported')
        ->doesntExpectOutputToContain('Barang Latihan');
});
