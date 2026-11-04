<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\FIN\Actions\ContraOpeningFact;
use App\Modules\INV\Actions\ReverseOpeningMovement;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-16 (TASK-057; WF-MIG-01; AX-28; IMP-01; IMP-03; IMP-04; DATABASE §24; FX-09): masters, then opening stock, each batch
// prepared from a synthetic source, dry-run on the rolled-back copy, signed off by the Admin and the Owner and committed row by row
// through the modules' ordinary actions, its seeds first.

uses(RefreshDatabase::class);

it('imports products and locations, then opening stock with its cost and serials, the control totals per company beside it (IMP-01; IMP-04; BR-INV-12; criteria 3, 9, 10)', function () {
    $world = ImportWorld::make();

    $products = $world->import('PRODUCT', [
        ['id' => 'P1', 'jenis' => 'barang', 'sku' => 'LAPTOP-1', 'nama' => 'Laptop', 'satuan' => 'UNIT', 'serial' => 'ya', 'harga_beli' => '5000000'],
        ['id' => 'P2', 'jenis' => 'barang', 'sku' => 'KERTAS-A4', 'nama' => 'Kertas A4', 'satuan' => 'RIM', 'satuan_lain' => 'DUS', 'rasio' => '5'],
    ]);
    expect($products->state)->toBe('COMMITTED')->and(ImportWorld::counts($products))->toMatchArray(['COMMITTED' => 4]);

    $locations = $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-A1', 'nama' => 'Rak A1']]);
    expect($locations->state)->toBe('COMMITTED');

    $stock = $world->import('OPENING_STOCK', [
        ['id' => 'S1', 'perusahaan' => $world->company->code, 'produk' => 'LAPTOP-1', 'lokasi' => 'RAK-A1', 'jumlah' => '2', 'biaya' => '9000000', 'serial' => 'SN-1;SN-2'],
        ['id' => 'S2', 'perusahaan' => $world->company->code, 'produk' => 'KERTAS-A4', 'lokasi' => 'RAK-A1', 'jumlah' => '3', 'satuan' => 'DUS', 'biaya' => '750000'],
    ]);

    expect($stock->state)->toBe('COMMITTED')
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->where('source_company_id', $world->company->id)->sum('acquired_qty_base'))->toEqual(17)
        ->and(DB::table('serial_units')->where('state', 'IN_STOCK')->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('movement_type', 'OPENING')->count())->toBe(2)
        ->and($stock->control_totals[$world->company->id]['stock_qty'] ?? null)->toEqual(17)->and($stock->control_totals[$world->company->id]['stock_cost'] ?? null)->toEqual(9750000)
        ->and(DB::table('stock_product_balances')->sum('reserved_qty'))->toEqual(0);
});

it('imports clients and suppliers, an open project with its MIGRATION confirmation, open purchases keeping a fitting legacy number, receivables, credit and archive items (IMP-01; IMP-07; BR-FIN-14; L-50; criteria 8, 9)', function () {
    $world = ImportWorld::make();
    $code = $world->company->code;
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'MEJA-1', 'nama' => 'Meja', 'satuan' => 'UNIT', 'harga_jual' => '1500000']]);
    $world->import('CLIENT', [['id' => 'K1', 'nama' => 'SMAN 1 Contoh', 'unit' => 'Tata Usaha', 'alamat' => 'Jl. Contoh 1', 'kota' => 'Yogyakarta', 'pic_nama' => 'Bu Rina']]);
    $world->import('SUPPLIER', [['id' => 'V1', 'nama' => 'CV Pemasok Contoh', 'kota' => 'Sleman']]);

    $projects = $world->import('OPEN_PROJECT', [
        ['id' => 'J1', 'perusahaan' => $code, 'klien' => 'K1', 'judul' => 'Pengadaan meja', 'produk' => 'MEJA-1', 'jumlah' => '10', 'harga' => '1500000'],
        ['id' => 'J1', 'perusahaan' => $code, 'produk' => 'MEJA-1', 'jumlah' => '2', 'harga' => '1400000', 'keterangan' => 'Meja guru'],
    ]);
    $project = DB::table('projects')->where('import_row_id', DB::table('import_rows')->where('import_batch_id', $projects->id)->where('import_kind', 'OPEN_PROJECT')->value('id'))->first();
    expect($projects->state)->toBe('COMMITTED')->and($project->origin)->toBe('OPENING')->and($project->state)->toBe('ACTIVE')
        ->and(DB::table('project_lines')->where('project_id', $project->id)->count())->toBe(2)
        ->and(DB::table('commercial_confirmations')->where('project_id', $project->id)->value('kind'))->toBe('MIGRATION')
        ->and(DB::table('reservations')->count())->toBe(0);

    $purchases = $world->import('OPEN_PURCHASE', [
        ['id' => 'B1', 'perusahaan' => $code, 'pemasok' => 'V1', 'proyek' => 'J1', 'tanggal' => "{$world->year}-11-02", 'nomor' => "PO/{$world->year}/0007", 'produk' => 'MEJA-1', 'jumlah' => '4', 'harga' => '900000', 'baris_proyek' => '1'],
        ['id' => 'B2', 'perusahaan' => $code, 'pemasok' => 'V1', 'tanggal' => "{$world->year}-12-01", 'nomor' => 'LAMA-15', 'produk' => 'MEJA-1', 'jumlah' => '3', 'harga' => '900000'],
    ]);
    $numbers = DB::table('purchases')->where('origin', 'OPENING')->orderBy('id')->pluck('purchase_number')->all();
    expect($purchases->state)->toBe('COMMITTED')->and($numbers[0])->toBe("PO/{$world->year}/0007")->and($numbers[1])->toBe("PO/{$world->year}/0021");

    $evidence = EvidenceDocument::factory()->create(['company_id' => $world->company->id, 'evidence_scope' => 'COMPANY']);
    $receivables = $world->import('OPENING_RECEIVABLE', [
        ['id' => 'F1', 'perusahaan' => $code, 'klien' => 'K1', 'proyek' => 'J1', 'nomor_faktur' => 'INV-LAMA-1', 'tanggal_tagih' => "{$world->year}-10-01", 'jatuh_tempo' => "{$world->year}-10-31", 'nilai' => '2000000', 'ppn' => '220000'],
        ['id' => 'F2', 'perusahaan' => $code, 'klien' => 'K1', 'nomor_faktur' => 'INV-LAMA-2', 'tanggal_tagih' => "{$world->year}-06-01", 'nilai' => '500000'],
    ]);
    $credits = $world->import('OPENING_CREDIT', [['id' => 'C1', 'perusahaan' => $code, 'klien' => 'K1', 'nilai' => '300000', 'bukti' => $evidence->public_id]]);
    $documents = $world->import('LEGACY_DOCUMENT', [['id' => 'D1', 'perusahaan' => $code, 'jenis' => 'Faktur', 'nomor' => 'INV-LAMA-0', 'keterangan' => 'Faktur tahun lalu']]);

    expect(DB::table('invoices')->where('origin', 'OPENING')->orderBy('id')->pluck('aging_basis')->all())->toBe(['DUE_DATE', 'MIGRASI'])
        ->and(DB::table('invoice_balances')->sum('gross_value'))->toEqual(2720000)
        ->and(DB::table('project_commercial_balances')->where('project_id', $project->id)->value('invoiced_sales_value'))->toEqual(0)
        ->and(DB::table('application_source_balances')->whereNotNull('opening_customer_credit_id')->value('gross_amount'))->toEqual(300000)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and($credits->control_totals[$world->company->id]['credits'] ?? null)->toBe('300000.00')
        ->and($documents->state)->toBe('COMMITTED')->and(DB::table('legacy_archive_items')->where('legacy_reference', 'INV-LAMA-0')->exists())->toBeTrue();
});

it('corrects a wrong opening fact on its record only — stock within what is unconsumed, a receivable and a credit once their applications are disposed — never by importing again (CM-33; criterion 11)', function () {
    $world = ImportWorld::make();
    $code = $world->company->code;
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'KURSI-1', 'nama' => 'Kursi', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-B1', 'nama' => 'Rak B1']]);
    $world->import('CLIENT', [['id' => 'K1', 'nama' => 'SMP 2 Contoh', 'unit' => 'TU', 'pic_nama' => 'Pak Budi']]);
    $world->import('OPENING_STOCK', [['id' => 'S1', 'perusahaan' => $code, 'produk' => 'KURSI-1', 'lokasi' => 'RAK-B1', 'jumlah' => '6', 'biaya' => '600000']]);
    $world->import('OPENING_RECEIVABLE', [['id' => 'F1', 'perusahaan' => $code, 'klien' => 'K1', 'nomor_faktur' => 'LAMA-9', 'tanggal_tagih' => "{$world->year}-09-01", 'nilai' => '1000000']]);
    $evidence = EvidenceDocument::factory()->create(['company_id' => $world->company->id, 'evidence_scope' => 'COMPANY']);
    $world->import('OPENING_CREDIT', [['id' => 'C1', 'perusahaan' => $code, 'klien' => 'K1', 'nilai' => '250000', 'bukti' => $evidence->public_id]]);
    $actor = Authorization::admin([$world->company->id], ['stock.view', 'stock.reverse', 'finance.view', 'finance.contra']);
    $today = BusinessDate::today();

    $movement = (int) DB::table('stock_movements')->where('movement_type', 'OPENING')->value('id');
    $reversed = app(ReverseOpeningMovement::class)->run((string) Str::uuid(), $actor->id, $world->company->id, $movement, [], 'Jumlah salah', $today, false);
    expect($reversed->kind)->toBe('COMMITTED')
        ->and(DB::table('stock_product_balances')->sum('on_hand_qty'))->toEqual(0)
        ->and(DB::table('lot_cost_balances')->sum('remaining_cost'))->toEqual(0);

    $invoice = (int) DB::table('invoices')->where('origin', 'OPENING')->value('id');
    $corrected = app(ContraOpeningFact::class)->run((string) Str::uuid(), $actor->id, $world->company->id, 'piutang', $invoice, ['sales' => '400000', 'tax' => '0', 'amount' => null], 'Sisa salah', $today, false);
    $credit = (int) DB::table('opening_customer_credits')->value('id');
    $contra = app(ContraOpeningFact::class)->run((string) Str::uuid(), $actor->id, $world->company->id, 'kredit', $credit, ['sales' => null, 'tax' => null, 'amount' => null], 'Kredit tidak ada', $today, false);

    expect($corrected->kind)->toBe('COMMITTED')->and(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('gross_value'))->toEqual(400000)
        ->and($contra->kind)->toBe('COMMITTED')->and(DB::table('application_source_balances')->where('opening_customer_credit_id', $credit)->value('net_amount'))->toEqual(0);

    // Never by importing again: the same identities are duplicates of their committed twins.
    $again = ImportWorld::batch($world->prepare('OPENING_STOCK', $world->source([['id' => 'S1', 'perusahaan' => $code, 'produk' => 'KURSI-1', 'lokasi' => 'RAK-B1', 'jumlah' => '5', 'biaya' => '500000']])));
    expect(DB::table('import_rows')->where('import_batch_id', $again->id)->where('import_identity', 'S1')->value('validation_state'))->toBe('DUPLICATE');
});

it('recovers on a dry run run again a row the first one refused once its cause is removed, and asks both sign-offs again (IMP-04; L-33; ER-06)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('OPENING_STOCK', $world->source([['id' => 'S1', 'perusahaan' => $world->company->code, 'produk' => 'BANGKU-1', 'lokasi' => 'RAK-Z', 'jumlah' => '2', 'biaya' => '200']])));
    $world->dryRun($batch);
    expect($batch->refresh()->state)->toBe('PREPARED')->and(DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_identity', 'S1')->value('validation_state'))->toBe('INVALID');

    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'BANGKU-1', 'nama' => 'Bangku', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-Z', 'nama' => 'Rak Z']]);
    $world->dryRun($batch);
    expect($batch->refresh()->state)->toBe('DRY_RUN_PASSED')->and(DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_identity', 'S1')->value('validation_state'))->toBe('VALID')
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->count())->toBe(0);

    $world->signOff($batch, $world->admin->id);
    $world->dryRun($batch);
    expect($batch->refresh()->admin_signoff_by)->toBeNull()->and($batch->state)->toBe('DRY_RUN_PASSED');
});

it('records the product a row created when its barcode is its last step, and takes back only the serials still in stock (Codex P1, P2; CM-33)', function () {
    $world = ImportWorld::make();
    $code = $world->company->code;
    $products = $world->import('PRODUCT', [['id' => 'P9', 'jenis' => 'barang', 'nama' => 'Proyektor', 'satuan' => 'UNIT', 'serial' => 'ya', 'barcode' => '8991234567890']]);
    $row = DB::table('import_rows')->where('import_batch_id', $products->id)->where('import_identity', 'P9')->first(['result_entity_type', 'result_entity_id']);
    expect($row->result_entity_type)->toBe('product')->and(DB::table('products')->where('id', $row->result_entity_id)->value('name'))->toBe('Proyektor');

    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-P', 'nama' => 'Rak P']]);
    $world->import('OPENING_STOCK', [['id' => 'S1', 'perusahaan' => $code, 'produk' => 'P9', 'lokasi' => 'RAK-P', 'jumlah' => '2', 'biaya' => '2000', 'serial' => 'SN-A;SN-B']]);
    // One serial has left since the import: what is left at the rack is the other one.
    $lot = (int) DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->value('id');
    DB::table('serial_units')->where('serial_number', 'SN-A')->update(['state' => 'DISPATCHED', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null]);
    DB::table('stock_lot_balances')->where('lot_id', $lot)->update(['qty' => '1']);

    $actor = Authorization::admin([$world->company->id], ['stock.view', 'stock.reverse']);
    $movement = (int) DB::table('stock_movements')->where('movement_type', 'OPENING')->value('id');
    $outcome = app(ReverseOpeningMovement::class)->run((string) Str::uuid(), $actor->id, $world->company->id, $movement, [], 'Serial keliru', BusinessDate::today(), false);

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('serial_number', 'SN-B')->value('state'))->toBe('NEVER_RECEIVED')
        ->and(DB::table('serial_units')->where('serial_number', 'SN-A')->value('state'))->toBe('DISPATCHED');
});

it('opens an older batch by its address beyond the recent list, which continues by its cursor (Codex P1; IP-18)', function () {
    $world = ImportWorld::make();
    $oldest = ImportBatch::factory()->create(['company_scope' => [$world->company->id]]);
    ImportBatch::factory()->count(51)->create(['company_scope' => [$world->company->id]]);
    $hot = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    $this->actingAs($world->admin)->get("/data-master/impor?batch={$oldest->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('batch.id', $oldest->public_id)->has('batches', 50)->whereNot('more', null));
    @unlink($hot);
});
