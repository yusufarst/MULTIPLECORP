<?php

use App\Modules\ImportOrchestration\Sweeps\ImportSweep;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\ImportWorld;

// TL-05, TL-16 (TASK-057; AX-28; M-33; M-34; JB-07; JB-09; HO-18; BD-11; C-47; IMP-11; GAP-010; criteria 6, 10) on real PostgreSQL sessions: two commits of one batch at once —
// a rerun chunk, a duplicate dispatch — and a lost chunk re-dispatched by the sweep, each creating one fact per legacy row; the reconciliation suite runs after every
// scenario (tests/Pest.php), so the guards match the committed opening facts.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('lets two commits of one batch race and still create one lot per legacy row, the batch COMMITTED (AX-28; BD-11; C-47; M-33; criterion 6)', function () {
    $world = ImportWorld::make();
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'MEJA-R', 'nama' => 'Meja', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-R', 'nama' => 'Rak R']]);
    $code = $world->company->code;
    $rows = array_map(fn (int $n): array => ['id' => "S{$n}", 'perusahaan' => $code, 'produk' => 'MEJA-R', 'lokasi' => 'RAK-R', 'jumlah' => '1', 'biaya' => '100'], range(1, 6));
    $batch = ImportWorld::batch($world->prepare('OPENING_STOCK', $world->source($rows)));
    $world->dryRun($batch);
    $world->signOff($batch, $world->admin->id);
    $world->signOff($batch, $world->owner->id);
    // The commit request recorded without its dispatch: the two sessions are the racing chunks.
    DB::table('import_batches')->where('id', $batch->id)->update(['committed_by' => $world->owner->id, 'committed_at' => now()]);
    $id = $batch->id;

    $results = Sessions::run([
        'first' => static fn (): string => ImportWorld::chunk($id),
        'second' => static fn (): string => ImportWorld::chunk($id),
    ]);

    expect($results)->toBe(['first' => 'done', 'second' => 'done'])
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->count())->toBe(6)
        ->and(DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_kind', 'OPENING_STOCK')->where('validation_state', 'COMMITTED')->count())->toBe(6);

    ImportWorld::chunk($id);
    expect($batch->refresh()->state)->toBe('COMMITTED');
});

it('re-dispatches a lost chunk from the batch\'s commit request, the facts reconciling to the control totals (JB-09; M-34; IMP-11; GAP-010; criterion 10)', function () {
    $world = ImportWorld::make();
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'KURSI-R', 'nama' => 'Kursi', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-S', 'nama' => 'Rak S']]);
    $code = $world->company->code;
    $batch = ImportWorld::batch($world->prepare('OPENING_STOCK', $world->source([
        ['id' => 'S1', 'perusahaan' => $code, 'produk' => 'KURSI-R', 'lokasi' => 'RAK-S', 'jumlah' => '4', 'biaya' => '400'],
        ['id' => 'S2', 'perusahaan' => $code, 'produk' => 'KURSI-R', 'lokasi' => 'RAK-S', 'jumlah' => '6', 'biaya' => '660'],
    ])));
    $world->dryRun($batch);
    $world->signOff($batch, $world->admin->id);
    $world->signOff($batch, $world->owner->id);
    // The commit request committed but its job was lost before it ran (M-34).
    DB::table('import_batches')->where('id', $batch->id)->update(['committed_by' => $world->owner->id, 'committed_at' => now()->subMinutes(ImportSweep::STALE_MINUTES + 1)]);
    DB::table('import_rows')->where('import_batch_id', $batch->id)->update(['updated_at' => now()->subMinutes(ImportSweep::STALE_MINUTES + 2)]);

    expect(app(ImportSweep::class)->sweep())->toBe(1)->and($batch->refresh()->state)->toBe('COMMITTED');
    $totals = $batch->control_totals[$world->company->id];
    expect(DB::table('inventory_lots')->where('source_company_id', $world->company->id)->where('acquisition_kind', 'OPENING')->sum('acquired_qty_base'))->toEqual($totals['stock_qty'])
        ->and(DB::table('lot_cost_entries')->where('entry_kind', 'OPENING_COST')->sum('amount'))->toEqual($totals['stock_cost'])
        ->and(app(ImportSweep::class)->sweep())->toBe(0);
});
