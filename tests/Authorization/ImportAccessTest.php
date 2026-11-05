<?php

use App\Modules\ORG\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-057; PERMISSIONS_MATRIX §4, §7.3; PJ-20; OD-13; DP-05; DP-18; SM-01; SM-04 W4-25; SM-09): PG-058 and its commands for an `import.prepare` holder with every
// company of the batch in scope and for the Owner; a batch reaching a company outside the preparer's scope unknown and never prepared; the commit and the abort the
// Owner's only; "Koreksi Data Awal" by `stock.reverse` and `finance.contra`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
});

afterEach(fn () => @unlink($this->hotFile));

it('opens PG-058 for an import.prepare holder and the Owner only, the navigation offering it to them alone (ROUTE-058; NAV-058)', function () {
    $world = ImportWorld::make();
    $viewer = Authorization::admin([$world->company->id], ['projects.view', 'stock.view']);

    $this->actingAs($viewer)->get('/data-master/impor')->assertForbidden();
    $this->actingAs($world->admin)->get('/data-master/impor')->assertOk();
    $this->actingAs($world->owner)->get('/data-master/impor')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can.commit', true));
});

it('keeps a batch of a company outside the preparer\'s scope unknown, and refuses a batch spanning one (PJ-20; DP-05; DP-18; W4-25)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])));
    $other = Company::factory()->create();
    $stranger = Authorization::admin([$other->id], ['import.prepare', 'projects.view']);

    $this->actingAs($stranger)->get("/data-master/impor?batch={$batch->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('batch', null)->where('batches', []));
    $this->actingAs($stranger)->post("/data-master/impor/{$batch->public_id}/uji", ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($stranger)->get("/status/impor/{$batch->public_id}")->assertNotFound();

    $source = ImportWorld::store("id,kode,nama\nL9,RAK-9,Rak 9\n", 's.csv', $stranger->id);
    $this->actingAs($stranger)->post('/data-master/impor', ['command_id' => (string) Str::uuid(), 'jenis' => 'LOCATION', 'berkas' => $source->public_id, 'tanggal' => $world->cutoff,
        'perusahaan' => [$other->public_id, $world->company->public_id]])->assertNotFound();
    expect(DB::table('import_batches')->count())->toBe(1);
});

it('lets only the Owner commit or abort, an Admin\'s attempt unknown (OD-13; AU-15)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])));

    foreach (['terapkan', 'batalkan'] as $step) {
        $this->actingAs($world->admin)->post("/data-master/impor/{$batch->public_id}/{$step}", ['command_id' => (string) Str::uuid(), 'alasan' => 'Coba'])->assertNotFound();
    }

    expect(fn () => $world->commit($batch, $world->admin->id))->toThrow(AuthorizationException::class)
        ->and($batch->refresh()->committed_at)->toBeNull();
});

it('lets Koreksi Data Awal run with stock.reverse and finance.contra only (ROUTE-213; ROUTE-214)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])));
    $viewer = Authorization::admin([$world->company->id], ['stock.view', 'finance.view']);

    $this->actingAs($viewer)->post('/gudang/riwayat-stok/'.Str::uuid().'/koreksi-data-awal', ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah'])->assertForbidden();
    $this->actingAs($viewer)->post("/keuangan/data-awal/{$batch->public_id}/koreksi", ['command_id' => (string) Str::uuid(), 'baris' => 2, 'alasan' => 'Salah'])->assertForbidden();
});
