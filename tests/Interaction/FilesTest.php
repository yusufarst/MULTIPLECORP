<?php

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Queries\ContentMatches;
use App\Modules\DOC\Services\UploadRefused;
use App\Modules\DOC\Services\Uploads;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-022; IG-01, IG-02): INT-013's server side — an upload outside FL-'s types or sizes refused with nothing
// stored, a duplicate of evidence content and a reproduction of a rendition found by their checksum (BD-15; FL-10;
// FL-05; criterion 2) —; INT-014 and INT-226 — a download authorized at request time, so a capability revoked after the
// page loaded denies the next one, never a signed link —; INT-227 and INT-285 — the denied and unknown cases alike, the
// status answering the state as it changes between polls.

uses(RefreshDatabase::class);

// A denial renders the not-found page through the shell's layout: a hot file stands in for the build, which these
// suites precede in CI.
beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
});

it('refuses an upload outside its purpose\'s types or size with nothing stored (INT-013; FL-01–FL-06)', function () {
    $uploader = Authorization::admin([$this->company->id], ['evidence.view', 'evidence.upload']);

    foreach ([[Files::svg(), 'gambar.svg'], [Files::png().str_repeat("\0", 21 * 1024 * 1024), 'besar.png'], [Files::png(), 'foto.pdf']] as [$bytes, $name]) {
        expect(fn () => app(Uploads::class)->accept(Files::path($bytes), $name, FilePurpose::Upload, $this->company->id, $uploader->id))
            ->toThrow(UploadRefused::class);
    }

    expect(FileObject::query()->count())->toBe(0)
        ->and(Storage::disk('files')->allFiles())->toBe([]);
});

it('finds a duplicate of evidence content and a reproduction of a rendition by their checksum (BD-15; FL-10; FL-05)', function () {
    $existing = EvidenceDocumentVersion::factory()->create();
    $evidenceFile = FileObject::query()->findOrFail($existing->file_object_id);
    $version = DocumentVersion::factory()->issued()->create();
    $rendered = FileObjectFactory::new()->create(['purpose' => 'RENDITION', 'company_id' => $version->company_id, 'sha256' => hash('sha256', 'pdf terbit')]);
    DocumentRendition::factory()->create(['document_version_id' => $version->id, 'state' => 'READY', 'file_object_id' => $rendered->id]);
    $upload = FileObjectFactory::new()->create(['company_id' => $this->company->id, 'sha256' => $evidenceFile->sha256]);

    expect(app(ContentMatches::class)->of($evidenceFile->sha256, $upload->id))
        ->toBe([['kind' => 'evidence', 'file_id' => $evidenceFile->id, 'company_id' => $evidenceFile->company_id, 'type' => 'CLIENT_ORDER',
            'record_id' => $existing->evidence_document_id, 'public_id' => EvidenceDocument::query()->findOrFail($existing->evidence_document_id)->public_id]])
        ->and(app(ContentMatches::class)->of(hash('sha256', 'pdf terbit')))
        ->toBe([['kind' => 'rendition', 'file_id' => $rendered->id, 'company_id' => $version->company_id, 'type' => $version->document_type_code,
            'record_id' => $version->id, 'public_id' => $version->public_id]])
        ->and(app(ContentMatches::class)->of(hash('sha256', 'tidak ada')))->toBe([]);
});

it('authorizes each download at request time, so a capability revoked meanwhile denies the next (INT-014; INT-226)', function () {
    $company = Company::factory()->create();
    $file = FileObjectFactory::new()->create(['company_id' => $company->id, 'storage_disk' => 'files']);
    EvidenceDocumentVersion::factory()->create([
        'evidence_document_id' => EvidenceDocument::factory()->create(['company_id' => $company->id])->id,
        'file_object_id' => $file->id,
    ]);
    $companyId = $company->id;
    $viewer = Authorization::admin([$companyId], ['evidence.view', 'projects.view']);
    Storage::disk('files')->put($file->storage_key, Files::pdf());

    $this->actingAs($viewer)->get("/berkas/{$file->public_id}/unduh")->assertOk();

    Authorization::revoke($viewer, 'evidence.view');
    $this->actingAs($viewer)->get("/berkas/{$file->public_id}/unduh")->assertNotFound();
});

it('answers the polled status as it changes, and a denied and an unknown version alike (INT-285; INT-227)', function () {
    $attempt = DocumentRendition::factory()->create();
    $version = DocumentVersion::query()->findOrFail($attempt->document_version_id);
    $viewer = Authorization::admin([$version->company_id], ['projects.view']);
    $stranger = Authorization::admin([Company::factory()->create()->id], ['projects.view']);

    $this->actingAs($viewer)->getJson("/status/rendisi/{$version->public_id}")->assertJsonPath('state', 'PENDING');
    DB::table('document_renditions')->where('id', $attempt->id)->update(['state' => 'RENDERING']);
    $this->actingAs($viewer)->getJson("/status/rendisi/{$version->public_id}")->assertJsonPath('state', 'RENDERING');

    $denied = $this->actingAs($stranger)->getJson("/status/rendisi/{$version->public_id}");
    $unknown = $this->actingAs($stranger)->getJson('/status/rendisi/'.Str::uuid7());

    $denied->assertNotFound();
    $unknown->assertNotFound();
    expect($denied->json('message'))->toBe($unknown->json('message'));
});

afterEach(function () {
    @unlink($this->hotFile);
});
