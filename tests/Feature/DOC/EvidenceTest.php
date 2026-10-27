<?php

use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceBalance;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Command\Outcome;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Evidence;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-030; ROUTE-038, ROUTE-228–ROUTE-231; criteria 1–3, 5, 8–10, 12): one upload route for every purpose under
// its capability and allowlist, MSG-31 at the field for a refused file and MSG-20 past WS-12's bounds; the registration
// with its first version current and its guard, an identity of the company offered as a new version, never a second
// record; a visible duplicate's warning and its override; pool evidence with MSG-32's tick; a version current at once
// or only through "Jadikan Versi Berlaku"; a link and its reasoned unlink; PG-038 one branch at a time.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->uploader = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
});

afterEach(fn () => @unlink($this->hotFile));

/** ROUTE-228's request for the evidence of the shared company. */
function evidenceUpload(object $test, string $bytes, string $name, array $fields = []): TestResponse
{
    $path = Files::path($bytes);

    return $test->actingAs($test->uploader)->post('/berkas', [
        'purpose' => 'bukti', 'company' => $test->company->public_id, ...$fields,
        'file' => new UploadedFile($path, $name, null, null, true),
    ], ['Accept' => 'application/json']);
}

it('stores an upload for its purpose and answers its id, the file the uploader\'s of its company (ROUTE-228; FL-01)', function () {
    $response = evidenceUpload($this, Evidence::pdf(), 'SP Klien.pdf')->assertCreated();
    $file = FileObject::query()->where('public_id', $response->json('id'))->sole();

    expect($response->json('name'))->toBe('SP Klien.pdf')
        ->and($file->purpose)->toBe('UPLOAD')
        ->and($file->company_id)->toBe($this->company->id)
        ->and($file->uploaded_by)->toBe($this->uploader->id)
        ->and(DB::table('command_log')->count())->toBe(0);
});

it('refuses at the field with MSG-31 an SVG, a disguised type, an active office file and a pixel bomb, storing nothing (criterion 1; FL-02–FL-05)', function (string $bytes, string $name) {
    $response = evidenceUpload($this, $bytes, $name)->assertStatus(422);

    expect($response->json('message'))->toBe(__('messages.msg_31', ['jenis' => 'PDF, JPG, PNG, WEBP, DOCX, XLSX', 'ukuran' => '20 MB']))
        ->and(FileObject::query()->count())->toBe(0)
        ->and(Storage::disk('files')->allFiles())->toBe([])
        ->and(DB::table('security_events')->where('event_kind', 'UPLOAD_REJECTED')->count())->toBe(1);
})->with([
    'svg' => fn () => [Files::svg(), 'gambar.svg'],
    'disguised' => fn () => [Files::png(), 'surat.pdf'],
    'active office' => fn () => [Files::docxWithDdeField(), 'surat.docx'],
    'pixel bomb' => fn () => [Files::pngHeader(60000, 60000), 'foto.png'],
]);

it('answers each purpose under its own capability, a company outside the scope as unknown (PJ-08; PJ-20; AZ-11)', function () {
    $other = Company::factory()->create();
    $plain = Authorization::admin([$this->company->id], ['projects.view']);
    $editor = Authorization::admin([$this->company->id], ['products.edit']);
    $preparer = Authorization::admin([$this->company->id], ['projects.view', 'import.prepare']);
    $send = fn ($user, array $fields, string $bytes, string $name) => $this->actingAs($user)->post('/berkas',
        [...$fields, 'file' => new UploadedFile(Files::path($bytes), $name, null, null, true)], ['Accept' => 'application/json']);

    $send($plain, ['purpose' => 'bukti', 'company' => $this->company->public_id], Evidence::pdf(), 'a.pdf')->assertForbidden();
    $send($this->uploader, ['purpose' => 'bukti', 'company' => $other->public_id], Evidence::pdf(), 'a.pdf')->assertNotFound();
    $send($this->uploader, ['purpose' => 'gambar-produk'], Files::png(), 'a.png')->assertForbidden();
    $send($editor, ['purpose' => 'gambar-produk'], Files::png(), 'a.png')->assertCreated();
    $send($this->uploader, ['purpose' => 'aset-identitas', 'company' => $this->company->public_id], Files::png(), 'logo.png')->assertForbidden();
    $send(Authorization::owner(), ['purpose' => 'aset-identitas', 'company' => $this->company->public_id], Files::png(), 'logo.png')->assertCreated();
    $send($preparer, ['purpose' => 'sumber-impor', 'companies' => [$this->company->public_id, $other->public_id]], "kode,nama\nA,B\n", 'impor.csv')->assertNotFound();
    $send($preparer, ['purpose' => 'sumber-impor', 'companies' => [$this->company->public_id]], "kode,nama\nA,B\n", 'impor.csv')->assertCreated();
    $send($preparer, ['purpose' => 'arsip', 'company' => $this->company->public_id], Evidence::pdf(), 'arsip.pdf')->assertCreated();

    expect(FileObject::query()->orderBy('id')->pluck('company_id', 'purpose')->all())
        ->toBe(['PRODUCT_IMAGE' => null, 'IDENTITY_ASSET' => $this->company->id, 'IMPORT_SOURCE' => null, 'ARCHIVE' => $this->company->id]);
});

it('stops uploads at 60 an hour and at the daily byte budget with MSG-20, counted from the stored files (criterion 10; WS-12; H6-07)', function () {
    FileObjectFactory::new()->count(60)->create(['company_id' => $this->company->id, 'uploaded_by' => $this->uploader->id, 'recorded_at' => now()->subMinutes(30)]);

    $limited = evidenceUpload($this, Evidence::pdf(), 'a.pdf')->assertStatus(429);
    expect($limited->json('message'))->toBe(trans_choice('messages.msg_20', 30, ['count' => 30]));

    // Files are append-only: a fresh account's day of large files, older than an hour, meets the byte budget.
    $this->uploader = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    FileObjectFactory::new()->count(3)->create(['company_id' => $this->company->id, 'uploaded_by' => $this->uploader->id,
        'recorded_at' => now()->subHours(5), 'size_bytes' => 100 * 1024 * 1024]);
    config(['files.uploads.daily_bytes' => 300 * 1024 * 1024]);
    evidenceUpload($this, Evidence::pdf(), 'a.pdf')->assertStatus(429);

    expect(FileObject::query()->count())->toBe(63);
});

it('registers the evidence with its first version current and its guard, opening PG-038 with it (criterion 2; ROUTE-229; NAV-038)', function () {
    $file = Evidence::upload($this->uploader, $this->company->id);
    $body = Evidence::registration($file, $this->company->public_id, ['reference' => 'SP / 0012', 'issuer' => 'SMA  Negeri 1, Contoh']);

    $this->actingAs($this->uploader)->post('/bukti', $body)
        ->assertRedirect("/dokumen/bukti?cabang={$this->company->public_id}&bukti=".EvidenceDocument::query()->sole()->public_id);
    $evidence = EvidenceDocument::query()->sole();
    $version = EvidenceDocumentVersion::query()->sole();

    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($evidence->identity_key)->toBe('sma negeri 1 contoh|CLIENT_ORDER|sp/0012|2026-10-01')
        ->and($evidence->current_version_id)->toBe($version->id)
        ->and($version->file_object_id)->toBe($file->id)
        ->and($version->version_no)->toBe(1)
        ->and(EvidenceBalance::query()->sole()->evidence_document_id)->toBe($evidence->id)
        ->and(DB::table('audit_events')->where('action', 'DOC.EvidenceRegistered')->value('entity_public_id'))->toBe($evidence->public_id);
});

it('offers an identity that exists in the company as a new version, never a second record (criterion 2; L-45; BD-14)', function () {
    $first = Evidence::upload($this->uploader, $this->company->id);
    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($first, $this->company->public_id, ['reference' => 'SP/77']));
    $existing = EvidenceDocument::query()->sole();
    $second = Evidence::upload($this->uploader, $this->company->id);

    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($second, $this->company->public_id, ['reference' => 'sp / 77', 'issuer' => 'sma negeri 1 contoh']));
    expect(Evidence::answer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'L-45', 'state' => ['evidence' => $existing->public_id]])
        ->and(EvidenceDocument::query()->count())->toBe(1);

    $this->actingAs($this->uploader)->post("/bukti/{$existing->public_id}/versi", ['command_id' => (string) Str::uuid(), 'file' => $second->public_id, 'note' => 'Pindaian lebih jelas']);
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($existing->refresh()->current_version_id)->toBe(EvidenceDocumentVersion::query()->where('version_no', 2)->value('id'));
});

it('warns of a reference-less likely duplicate, recorded only with warnings.override naming the matches and its reason (criterion 3; IP-05; BD-14)', function () {
    $body = fn ($file, array $more = []) => Evidence::registration($file, $this->company->public_id, ['reference' => null, ...$more]);
    $this->actingAs($this->uploader)->post('/bukti', $body(Evidence::upload($this->uploader, $this->company->id)));
    $match = EvidenceDocument::query()->sole()->public_id;
    $file = Evidence::upload($this->uploader, $this->company->id);

    $this->actingAs($this->uploader)->post('/bukti', $body($file));
    expect(Evidence::answer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'FL-10'])
        ->and(Evidence::answer()['state'])->toMatchArray(['matches' => [$match], 'override' => false])
        ->and(Evidence::answer()['state']['labels'][0])->toMatchArray(['id' => $match, 'kind' => 'evidence', 'type' => 'CLIENT_ORDER', 'reference' => null, 'branch' => $this->company->public_id]);

    Authorization::grant($this->uploader, 'warnings.override');
    $this->actingAs($this->uploader)->post('/bukti', $body($file, ['acknowledged' => [$match]]));
    expect(Evidence::answer()['code'])->toBe('FL-10');

    $this->actingAs($this->uploader)->post('/bukti', $body($file, ['acknowledged' => [$match], 'reason' => 'Surat pesanan kedua di hari yang sama']));
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(EvidenceDocument::query()->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'DOC.EvidenceDuplicateOverridden')->sole())
        ->authority_class->toBe('ADM_PLUS')
        ->reason->toBe('Surat pesanan kedua di hari yang sama');
});

it('shows nothing of a match in another company, which the Owner finds as EVIDENCE_DUPLICATE_UNDISCLOSED (criterion 4; DP-13; UXS-18)', function () {
    $other = Company::factory()->create();
    $elsewhere = EvidenceDocument::factory()->create(['company_id' => $other->id, 'issuer_reference' => 'SP/9', 'declared_issuer' => 'SMA Negeri 1 Contoh',
        'identity_key' => 'sma negeri 1 contoh|CLIENT_ORDER|sp/9|2026-10-01']);
    $file = Evidence::upload($this->uploader, $this->company->id);

    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($file, $this->company->public_id, ['reference' => 'SP/9']));
    $registered = EvidenceDocument::query()->where('company_id', $this->company->id)->sole();
    $event = DB::table('security_events')->where('event_kind', 'EVIDENCE_DUPLICATE_UNDISCLOSED')->sole();

    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($event->target_public_id)->toBe($registered->public_id)
        ->and($event->related_public_id)->toBe($elsewhere->public_id)
        ->and($event->reason_code)->toBe('CROSS_TYPE')
        ->and($event->related_company_id)->toBe($other->id);
});

it('records an upload equal to a rendition as a reproduction, flagged only to a viewer of that rendition (criterion 5; FL-05; FS-12)', function () {
    // The rendition is another company's document, so its match shows the uploader nothing (GAP-034).
    $bytes = Evidence::pdf('rendisi-terbit');
    $version = DocumentVersion::factory()->issued()->create();
    $rendered = FileObjectFactory::new()->create(['purpose' => 'RENDITION', 'company_id' => $version->company_id, 'sha256' => hash('sha256', $bytes)]);
    DocumentRendition::factory()->create(['document_version_id' => $version->id, 'state' => 'READY', 'file_object_id' => $rendered->id]);
    $file = Evidence::upload($this->uploader, $this->company->id, $bytes);

    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($file, $this->company->public_id));
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(json_decode((string) DB::table('audit_events')->where('action', 'DOC.EvidenceRegistered')->value('metadata'), true)['reproduction'])->toBeTrue()
        ->and(DB::table('security_events')->where('event_kind', 'EVIDENCE_DUPLICATE_UNDISCLOSED')->value('reason_code'))->toBe('RENDITION');

    $page = fn ($viewer) => $this->actingAs($viewer)->get("/dokumen/bukti?cabang={$this->company->public_id}");
    $page($this->uploader)->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.reproduction', false));
    $owner = Authorization::owner();
    $page($owner)->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.reproduction', true));
});

it('registers pool evidence only of the pool types with MSG-32\'s tick, seen with stock.view (criterion 8; PJ-08; H7-11)', function () {
    $keeper = Authorization::admin([$this->company->id], ['stock.view', 'evidence.view', 'evidence.upload']);
    $file = Evidence::upload($keeper, null);
    $body = Evidence::registration($file, null, ['company' => null, 'pool' => true, 'type' => 'WAREHOUSE_RECORD', 'reference' => null, 'issuer' => 'Gudang Bersama']);

    $this->actingAs($keeper)->post('/bukti', $body)->assertSessionHasErrors('pool_confirmed');
    $this->actingAs($keeper)->post('/bukti', [...$body, 'type' => 'CLIENT_ORDER', 'pool_confirmed' => true])->assertSessionHasErrors('pool_confirmed');
    $this->actingAs($keeper)->post('/bukti', [...$body, 'pool_confirmed' => true])->assertRedirect('/dokumen/bukti?cabang=bersama&bukti='.EvidenceDocument::query()->sole()->public_id);
    $pool = EvidenceDocument::query()->sole();

    expect($pool->evidence_scope)->toBe('POOL')->and($pool->company_id)->toBeNull()
        ->and(json_decode((string) DB::table('audit_events')->where('action', 'DOC.EvidenceRegistered')->value('metadata'), true)['pool'])->toBeTrue();
    $this->actingAs($keeper)->get('/dokumen/bukti?cabang=bersama')->assertInertia(fn (AssertableInertia $page) => $page->where('branch', 'bersama')->where('rows.0.id', $pool->public_id));
    $this->actingAs($this->uploader)->get('/dokumen/bukti?cabang=bersama')->assertInertia(fn (AssertableInertia $page) => $page->where('branch', $this->company->public_id)->has('rows', 0));
});

it('makes a version of linked evidence current only through "Jadikan Versi Berlaku" (NX-07; NX-14; L-41)', function () {
    $project = Project::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration(Evidence::upload($this->uploader, $this->company->id), $this->company->public_id));
    $evidence = EvidenceDocument::query()->sole();
    $first = $evidence->current_version_id;

    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'link', 'project' => $project->public_id, 'role' => 'CLIENT_ORIGINAL']);
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('evidence_links')->where('project_id', $project->id)->value('role'))->toBe('CLIENT_ORIGINAL');

    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/versi", ['command_id' => (string) Str::uuid(), 'file' => Evidence::upload($this->uploader, $this->company->id)->public_id]);
    expect($evidence->refresh()->current_version_id)->toBe($first);

    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'replace', 'version' => 2]);
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($evidence->refresh()->current_version_id)->toBe(EvidenceDocumentVersion::query()->where('version_no', 2)->value('id'))
        ->and(DB::table('audit_events')->where('action', 'DOC.EvidenceReplaced')->exists())->toBeTrue();

    $handle = $this->actingAs($this->uploader)->get("/dokumen/bukti?bukti={$evidence->public_id}")->viewData('page')['props']['evidence']['links'][0]['handle'];
    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'unlink', 'link' => $handle])
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'unlink', 'link' => $handle, 'reason' => 'Salah proyek']);
    expect(Evidence::answer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('evidence_links')->value('unlink_reason'))->toBe('Salah proyek');
});

it('references only an upload its own actor made for the evidence\'s company, another\'s answering as not found (criterion 12; DIR-089 (5))', function () {
    $colleague = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    $theirs = Evidence::upload($colleague, $this->company->id);

    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($theirs, $this->company->public_id))->assertNotFound();
    $this->actingAs($colleague)->post('/bukti', Evidence::registration($theirs, $this->company->public_id));
    $evidence = EvidenceDocument::query()->sole();
    $this->actingAs($this->uploader)->post("/bukti/{$evidence->public_id}/versi", ['command_id' => (string) Str::uuid(), 'file' => $theirs->public_id])->assertNotFound();

    expect(EvidenceDocumentVersion::query()->count())->toBe(1);
});

it('lists one branch at a time, its families only, a page of 25 with its cursor (ROUTE-038; PJ-08; PF-13)', function () {
    EvidenceDocument::factory()->count(26)->create(['company_id' => $this->company->id]);
    EvidenceDocument::factory()->supplierBill()->create(['company_id' => $this->company->id]);

    $first = $this->actingAs($this->uploader)->get('/dokumen/bukti')->assertOk();
    $first->assertInertia(fn (AssertableInertia $page) => $page->component('DOC/Evidence', false)->where('branch', $this->company->public_id)->has('rows', 25)
        ->where('branches.0.types', ['CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'THIRD_PARTY_HPS', 'NPWP', 'NIB', 'DELIVERY_PROOF', 'OTHER']));
    $more = $first->viewData('page')['props']['more'];

    $this->actingAs($this->uploader)->get("/dokumen/bukti?setelah={$more}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('more', null));
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get('/dokumen/bukti')->assertForbidden();
});
