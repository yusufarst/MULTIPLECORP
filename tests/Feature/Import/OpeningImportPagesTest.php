<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-07, TL-16 (TASK-057; ROUTE-058; ROUTE-272–ROUTE-276; INT-272–INT-276; WF-MIG-01; FL-06; C-47; DP-18; UXS-19; L-33; NM-03; NM-08; IMP-06; criteria 1, 2, 4, 5, 7, 8, 14,
// 15): PG-058 with its batches; "Siapkan Batch" from the preparer's own source, malformed and duplicate rows surfaced with their numbers and reasons; the same source refused;
// a collision outside scope answered with MSG-18 alone and recorded for the Owner; the joint sign-off, the Owner's commit behind the step-up, an Admin's denied; a missing seed
// named; a refused row stopping the batch until another Owner resumes; a kept legacy number at its seed refused; the abort of a batch with no committed row only.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
});

afterEach(fn () => @unlink($this->hotFile));

function importAnswer(object $test, object $actor, string $url, array $body, bool $stepUp = false): array
{
    $request = $stepUp ? $test->actingAs($actor)->withSession([RequireStepUp::CONFIRMED_AT => time()]) : $test->actingAs($actor);
    $answer = $request->followingRedirects()->post($url, ['command_id' => (string) Str::uuid(), ...$body])->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function importBody(ImportWorld $world, object $file, string $kind): array
{
    return ['jenis' => $kind, 'berkas' => $file->public_id, 'tanggal' => $world->cutoff, 'perusahaan' => [$world->company->public_id]];
}

it('prepares a batch from the preparer\'s own source, each malformed and repeated row surfaced with its number and reasons (WF-MIG-01; FL-06; criteria 1, 15)', function () {
    $world = ImportWorld::make();
    $file = $world->source([
        ['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1'],
        ['id' => 'L2', 'kode' => '', 'nama' => 'Tanpa kode'],
        ['id' => 'L1', 'kode' => 'RAK-9', 'nama' => 'Kembar'],
    ]);

    $this->actingAs($world->admin)->get('/data-master/impor')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('IMPORT/OpeningImport')->where('can.prepare', true)->where('can.commit', false));
    $answer = importAnswer($this, $world->admin, '/data-master/impor', importBody($world, $file, 'LOCATION'));
    $batch = DB::table('import_batches')->first();
    $rows = DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_kind', 'LOCATION')->orderBy('row_number')->get(['row_number', 'validation_state', 'errors']);

    expect($answer['kind'])->toBe('COMMITTED')->and($batch->state)->toBe('PREPARED')
        ->and($rows->pluck('validation_state')->all())->toBe(['VALID', 'INVALID', 'DUPLICATE'])
        ->and($rows->pluck('row_number')->all())->toBe([2, 3, 4])
        ->and(json_decode($rows[1]->errors, true))->toBe([['field' => 'kode', 'reason' => 'required']]);

    // Criterion 15: another account's upload answers as not found, the database never holding a source to its preparer otherwise.
    $other = Authorization::admin([$world->company->id], ['import.prepare', 'projects.view']);
    $foreign = ImportWorld::store("id,kode,nama\nX,RAK-X,Rak X\n", 'lain.csv', $world->admin->id);
    $this->actingAs($other)->post('/data-master/impor', ['command_id' => (string) Str::uuid(), ...importBody($world, $foreign, 'LOCATION')])->assertNotFound();

    $this->actingAs($world->admin)->get("/data-master/impor?batch={$batch->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('batch.id', $batch->public_id)->where('batch.problems.0.row', 3)->where('batch.problems.1.state', 'DUPLICATE'));
});

it('refuses the same source of the same kind, and answers MSG-18 alone for a collision outside the preparer\'s scope, recorded for the Owner (C-47; DP-18; UXS-19; criterion 2)', function () {
    $world = ImportWorld::make();
    $bytes = "id,kode,nama\nL1,RAK-1,Rak 1\n";
    $first = ImportWorld::store($bytes, 'a.csv', $world->admin->id);
    expect(importAnswer($this, $world->admin, '/data-master/impor', importBody($world, $first, 'LOCATION'))['kind'])->toBe('COMMITTED');

    $again = ImportWorld::store($bytes, 'b.csv', $world->admin->id);
    $same = importAnswer($this, $world->admin, '/data-master/impor', importBody($world, $again, 'LOCATION'));
    expect($same['kind'])->toBe('REJECTED')->and($same['text'])->toBe(__('import.refusal.same_source'));

    $elsewhere = ImportWorld::make(Company::factory()->create());
    $stranger = ImportWorld::store($bytes, 'c.csv', $elsewhere->admin->id);
    $hidden = importAnswer($this, $elsewhere->admin, '/data-master/impor', importBody($elsewhere, $stranger, 'LOCATION'));

    expect($hidden)->toMatchArray(['kind' => 'CONFLICT', 'cause' => 'import_outside_scope', 'text' => __('messages.msg_18'), 'code' => null])
        ->and(DB::table('security_events')->where('event_kind', 'IMPORT_COLLISION_UNDISCLOSED')->count())->toBe(1)
        ->and(DB::table('import_batches')->count())->toBe(1);
});

it('lists a row whose committed twin lies outside scope under Tidak Valid with MSG-18 alone, never under Duplikat (UXS-19; criterion 2)', function () {
    $world = ImportWorld::make();
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'MEJA-9', 'nama' => 'Meja', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-9', 'nama' => 'Rak 9']]);
    $world->import('OPENING_STOCK', [['id' => 'S1', 'perusahaan' => $world->company->code, 'produk' => 'MEJA-9', 'lokasi' => 'RAK-9', 'jumlah' => '1', 'biaya' => '100']]);

    $elsewhere = ImportWorld::make(Company::factory()->create());
    $batch = ImportWorld::batch($elsewhere->prepare('OPENING_STOCK', $elsewhere->source([['id' => 'S1', 'perusahaan' => $elsewhere->company->code, 'produk' => 'MEJA-9', 'lokasi' => 'RAK-9', 'jumlah' => '1', 'biaya' => '100']])));
    $row = DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_identity', 'S1')->first(['validation_state', 'errors']);

    expect($row->validation_state)->toBe('INVALID')->and(json_decode($row->errors, true))->toBe([['field' => '', 'reason' => 'undisclosed']]);
});

it('takes both sign-offs before the Owner commits behind the step-up; an Admin\'s commit is denied; a missing seed is named (L-33; OD-13; AU-15; NM-03; criteria 4, 5)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']], seeds: false)));
    $url = "/data-master/impor/{$batch->public_id}";

    expect(importAnswer($this, $world->admin, "{$url}/uji", [])['kind'])->toBe('COMMITTED')
        ->and(DB::table('import_batches')->value('state'))->toBe('DRY_RUN_PASSED');
    $world->signOff($batch, $world->admin->id);
    $this->actingAs($world->owner)->postJson("{$url}/terapkan", ['command_id' => (string) Str::uuid()])->assertStatus(423)->assertJsonPath('message', __('messages.msg_27'));
    expect(importAnswer($this, $world->owner, "{$url}/terapkan", [], stepUp: true)['text'])->toBe(__('import.refusal.unsigned'));
    $world->signOff($batch, $world->owner->id);

    $this->actingAs($world->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post("{$url}/terapkan", ['command_id' => (string) Str::uuid()])->assertNotFound();

    $missing = importAnswer($this, $world->owner, "{$url}/terapkan", [], stepUp: true);
    expect($missing['kind'])->toBe('REJECTED')->and($missing['text'])->toBe(__('import.refusal.seed', ['jenis' => 'PROJECT', 'periode' => $world->year]))
        ->and(DB::table('import_batches')->value('committed_at'))->toBeNull();
});

it('stops the batch at a refused row, keeps what committed, and commits it once its cause is removed and another Owner resumes (CI-08; JB-07; PX-05; M-33; criteria 6, 7)', function () {
    $world = ImportWorld::make();
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'LEMARI-1', 'nama' => 'Lemari', 'satuan' => 'UNIT']]);
    $world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1'], ['id' => 'L2', 'kode' => 'RAK-2', 'nama' => 'Rak 2']]);
    $code = $world->company->code;
    $batch = ImportWorld::batch($world->prepare('OPENING_STOCK', $world->source([
        ['id' => 'S1', 'perusahaan' => $code, 'produk' => 'LEMARI-1', 'lokasi' => 'RAK-1', 'jumlah' => '2', 'biaya' => '200'],
        ['id' => 'S2', 'perusahaan' => $code, 'produk' => 'LEMARI-1', 'lokasi' => 'RAK-2', 'jumlah' => '3', 'biaya' => '300'],
    ])));
    $world->dryRun($batch);
    $world->signOff($batch, $world->admin->id);
    $world->signOff($batch, $world->owner->id);
    // The second row's rack is deactivated after the dry run: its commit is refused.
    DB::table('locations')->where('code', 'RAK-2')->update(['is_active' => false]);

    expect($world->commit($batch)->kind)->toBe('COMMITTED');
    $rows = DB::table('import_rows')->where('import_batch_id', $batch->id)->whereIn('import_identity', ['S1', 'S2'])->orderBy('row_number')->get(['validation_state', 'errors']);
    expect($batch->refresh()->state)->toBe('SIGNED_OFF')->and($rows->pluck('validation_state')->all())->toBe(['COMMITTED', 'VALID'])
        ->and(json_decode($rows[1]->errors, true)[0]['reason'])->toBe('reference')
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->count())->toBe(1);

    DB::table('locations')->where('code', 'RAK-2')->update(['is_active' => true]);
    $other = Authorization::owner();
    expect($world->commit($batch, $other->id)->kind)->toBe('COMMITTED')
        ->and($batch->refresh()->state)->toBe('COMMITTED')->and($batch->committed_by)->toBe($other->id)
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->count())->toBe(2);

    // Rerunning the whole batch's commit finds every row done (BD-11).
    \App\Modules\ImportOrchestration\Jobs\CommitImportRows::dispatchSync($batch->id);
    expect(DB::table('inventory_lots')->where('acquisition_kind', 'OPENING')->count())->toBe(2);
});

it('refuses a kept legacy purchase number at or above its period\'s committed seed, surfaced by the dry run (NM-08; DIR-079 CB-01; criterion 8)', function () {
    $world = ImportWorld::make();
    $world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'RAK-BUKU', 'nama' => 'Rak buku', 'satuan' => 'UNIT']]);
    $world->import('SUPPLIER', [['id' => 'V1', 'nama' => 'CV Uji Pemasok']]);
    $batch = ImportWorld::batch($world->prepare('OPEN_PURCHASE', $world->source([
        ['id' => 'B1', 'perusahaan' => $world->company->code, 'pemasok' => 'V1', 'tanggal' => "{$world->year}-12-10", 'nomor' => "PO/{$world->year}/0021", 'produk' => 'RAK-BUKU', 'jumlah' => '1', 'harga' => '100000'],
    ])));
    $world->dryRun($batch);
    $row = DB::table('import_rows')->where('import_batch_id', $batch->id)->where('import_identity', 'B1')->first(['validation_state', 'errors']);

    expect($batch->refresh()->state)->toBe('PREPARED')->and($row->validation_state)->toBe('INVALID')
        ->and(json_decode($row->errors, true)[0])->toMatchArray(['reason' => 'refused', 'code' => 'NM-08']);
});

it('aborts with its reason a batch that committed no row, none of its rows committing after, and refuses the abort of one that did (IMP-06; PRQ-06; criterion 14)', function () {
    $world = ImportWorld::make();
    $batch = ImportWorld::batch($world->prepare('LOCATION', $world->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])));
    $world->dryRun($batch);
    $world->signOff($batch, $world->admin->id);
    $world->signOff($batch, $world->owner->id);

    $aborted = importAnswer($this, $world->owner, "/data-master/impor/{$batch->public_id}/batalkan", ['alasan' => 'Sumber keliru'], stepUp: true);
    expect($aborted['kind'])->toBe('COMMITTED')->and($batch->refresh()->state)->toBe('ABORTED')
        ->and(DB::table('audit_events')->where('action', 'IMPORT.BatchAborted')->value('reason'))->toBe('Sumber keliru')
        ->and($world->commit($batch)->code)->toBe('ST-02')
        ->and(DB::table('locations')->where('code', 'RAK-1')->exists())->toBeFalse();

    $committed = $world->import('LOCATION', [['id' => 'L2', 'kode' => 'RAK-2', 'nama' => 'Rak 2']]);
    expect($world->abort($committed)->code)->toBe('ST-02');
});
