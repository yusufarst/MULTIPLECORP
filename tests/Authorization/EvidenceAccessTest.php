<?php

use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Evidence;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-030; PJ-08; TM-55; DP-05; TM-20; criteria 8–10): an account sees, chooses, uploads, versions and links
// only the evidence of the families it may view — another family's evidence answering as unknown, its type refused at
// the upload and the registration —; pool evidence by `stock.view` with `evidence.view`, never by `evidence.view` alone;
// a file served only through its evidence to its viewers, another company's answering as not found.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->projects = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    $this->finance = Authorization::admin([$this->company->id], ['finance.view', 'evidence.view', 'evidence.upload']);
    $this->statement = EvidenceDocument::factory()->bankStatement()->create(['company_id' => $this->company->id]);
    $file = Evidence::upload($this->finance, $this->company->id);
    $version = EvidenceDocumentVersion::factory()->create(['evidence_document_id' => $this->statement->id, 'file_object_id' => $file->id, 'uploaded_by' => $this->finance->id]);
    $this->statement->forceFill(['current_version_id' => $version->id])->save();
    $this->statementFile = $file;
});

afterEach(fn () => @unlink($this->hotFile));

it('shows and offers each account only its families\' evidence and types (PJ-08; TM-55)', function () {
    $page = fn ($viewer) => $this->actingAs($viewer)->get('/dokumen/bukti');

    $page($this->projects)->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('branches.0.types', fn ($types) => ! collect($types)->contains('BANK_STATEMENT')));
    $page($this->finance)->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.id', $this->statement->public_id)->where('branches.0.types', fn ($types) => collect($types)->contains('BANK_STATEMENT')));
    $this->actingAs($this->projects)->get("/dokumen/bukti?bukti={$this->statement->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('evidence', null));
});

it('refuses another family\'s type at the upload and the registration, and answers its evidence as unknown to a version or a link (PJ-08; TM-55)', function () {
    $this->actingAs($this->projects)->post('/berkas', ['purpose' => 'bukti', 'company' => $this->company->public_id, 'type' => 'BANK_STATEMENT',
        'file' => new UploadedFile(Files::path(Evidence::pdf()), 'rk.pdf', null, null, true)], ['Accept' => 'application/json'])->assertForbidden();
    $file = Evidence::upload($this->projects, $this->company->id);

    $this->actingAs($this->projects)->post('/bukti', Evidence::registration($file, $this->company->public_id, ['type' => 'BANK_STATEMENT', 'issuer' => 'Bank Contoh']))->assertForbidden();
    $this->actingAs($this->projects)->post("/bukti/{$this->statement->public_id}/versi", ['command_id' => (string) Str::uuid(), 'file' => $file->public_id])->assertNotFound();
    $this->actingAs($this->projects)->post("/bukti/{$this->statement->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'replace', 'version' => 1])->assertNotFound();

    expect(EvidenceDocumentVersion::query()->count())->toBe(1);
});

it('serves an evidence file only to its family\'s viewers in scope, another company\'s as not found (FL-07; DP-05; TM-20; criterion 9)', function () {
    $outsider = Authorization::admin([Company::factory()->create()->id], ['finance.view', 'evidence.view']);

    $this->actingAs($this->finance)->get("/berkas/{$this->statementFile->public_id}/unduh")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition');
    $this->actingAs($this->projects)->get("/berkas/{$this->statementFile->public_id}/unduh")->assertNotFound();
    $this->actingAs($outsider)->get("/berkas/{$this->statementFile->public_id}/unduh")->assertNotFound();
});

it('shows, counts and unlinks only the links whose record the viewer may see (PJ-08 row 95)', function () {
    $project = Project::factory()->create(['company_id' => $this->company->id]);
    $link = DB::table('evidence_links')->insertGetId(['evidence_document_id' => $this->statement->id, 'evidence_scope' => 'COMPANY',
        'company_id' => $this->company->id, 'role' => 'SUPPORTING', 'project_id' => $project->id, 'recorded_at' => now()]);
    $both = Authorization::admin([$this->company->id], ['finance.view', 'projects.view', 'evidence.view', 'evidence.upload']);
    $sheet = fn ($viewer) => $this->actingAs($viewer)->get("/dokumen/bukti?bukti={$this->statement->public_id}");

    $sheet($this->finance)->assertInertia(fn (AssertableInertia $page) => $page->has('evidence.links', 0)->where('rows.0.links', 0));
    $sheet($both)->assertInertia(fn (AssertableInertia $page) => $page->has('evidence.links', 1)->where('rows.0.links', 1));
    $this->actingAs($this->finance)->post("/bukti/{$this->statement->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'unlink',
        'link' => LinkHandle::of($link), 'reason' => 'Bukan urusan saya'])->assertNotFound();

    expect(DB::table('evidence_links')->whereNull('unlinked_at')->count())->toBe(1);
});

it('opens pool evidence to `stock.view` with `evidence.view` only, whatever company the reader has (PJ-08; CS-05)', function () {
    $keeper = Authorization::admin([Company::factory()->create()->id], ['stock.view', 'evidence.view', 'evidence.upload']);
    $pool = EvidenceDocument::factory()->create(['evidence_scope' => 'POOL', 'company_id' => null, 'evidence_type' => 'WAREHOUSE_RECORD', 'issuer_reference' => null,
        'identity_key' => 'gudang bersama|WAREHOUSE_RECORD||2026-10-01']);

    $this->actingAs($keeper)->get('/dokumen/bukti?cabang=bersama')->assertInertia(fn (AssertableInertia $page) => $page->where('branch', 'bersama')->where('rows.0.id', $pool->public_id));
    $this->actingAs($this->projects)->get("/dokumen/bukti?cabang=bersama&bukti={$pool->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('branch', $this->company->public_id)->where('evidence', null));
});
