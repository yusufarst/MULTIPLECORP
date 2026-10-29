<?php

use App\Modules\ADM\Services\Readiness;
use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentVersionFactory;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-035; L-41; BR-ADM-03; NX-12; NX-14; AX-15; architecture challenge 7b, 20a; criteria 7, 8): DOC's void, a
// revision's issue, an evidence's unlink and its replacement each re-evaluate the requirement they satisfied inside
// DOC's own transaction through ADM's `DocumentChangeParticipant` — the voided version's link unlinked with its reason,
// the revised version's moved to the revision that superseded it, the unlinked evidence's link unlinked, a replaced
// evidence judged on its new current version — and the administration predicate reads the item open again.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Queue::fake();
    Authorization::catalogue();
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->today = BusinessDate::today();
    $year = substr($this->today, 0, 4);
    Numbering::goLive("{$year}-01-01");
    $this->company = Projects::company();
    Numbering::scheme($this->company, SequenceType::Doc09, "{$year}-01-01", 'YEAR', 'CTH/SPK/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc09, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'evidence.view', 'evidence.upload', 'requirements.manage', 'documents.issue', 'documents.correct']);
    $this->project = Administration::project($this->admin->id, $this->company);
    $this->item = Administration::requirement($this->project, ['satisfaction_mode' => 'EITHER', 'document_type_code' => 'DOC-09']);
});

afterEach(fn () => @unlink($this->hotFile));

function changeLink(object $test, array $target): void
{
    $test->actingAs($test->admin)->post("/proyek/{$test->project->public_id}/persyaratan/".Administration::handle($test->item).'/tautkan', ['command_id' => (string) Str::uuid(), ...$target]);
}

/** DOC-09 of the project prepared and issued through DOC's own commands, its rendition then rendered. */
function changeIssuedSpk(object $test): Document
{
    $test->actingAs($test->admin)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $test->project->public_id]]);
    $document = Document::query()->where('project_id', $test->project->id)->where('document_type_code', 'DOC-09')->sole();
    changeIssue($test, $document);

    return $document;
}

function changeIssue(object $test, Document $document): DocumentVersion
{
    $draft = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->sole();
    $test->actingAs($test->admin)->post("/dokumen/{$document->public_id}/terbitkan", [
        'command_id' => (string) Str::uuid(), 'lock_version' => $draft->lock_version, 'draf' => $draft->public_id, 'tanggal' => $test->today, 'tanggal_otomatis' => true,
    ])->assertRedirect();
    $issued = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->sole();
    Administration::rendered($issued);

    return $issued;
}

function changeActiveLinks(object $test): array
{
    return DB::table('admin_requirement_links')->where('admin_requirement_id', $test->item)->whereNull('unlinked_at')->get(['document_version_id', 'evidence_document_id'])->map(fn ($row) => (array) $row)->all();
}

it('re-evaluates the item a voided version satisfied inside the void\'s transaction (NX-12; L-41; architecture challenge 7b; criterion 7)', function () {
    $document = changeIssuedSpk($this);
    $issued = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->sole();
    changeLink($this, ['dokumen' => $document->public_id]);
    expect(Administration::status($this->project, $this->item))->toBe(Readiness::SATISFIED);

    $this->actingAs($this->admin)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah pihak'])->assertRedirect();

    $link = DB::table('admin_requirement_links')->where('admin_requirement_id', $this->item)->sole();
    expect(DocumentVersion::query()->whereKey($issued->id)->value('state'))->toBe('VOIDED')
        ->and($link)->toMatchObject(['document_version_id' => $issued->id, 'unlink_reason' => 'The linked version was voided (L-41).', 'unlinked_by' => $this->admin->id])
        // The unlink belongs to the void's command: one transaction, one command identity.
        ->and($link->unlinked_at)->toBe(DB::table('document_versions')->where('id', $issued->id)->value('voided_at'))
        ->and(Administration::status($this->project, $this->item))->toBe(Readiness::MISSING)
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementUnlinked')->where('command_id', DB::table('audit_events')->where('action', 'DOC.DocumentVoided')->value('command_id'))->exists())->toBeTrue();
});

it('moves the item to the revision that supersedes its version when that revision issues (AX-15; L-41; criterion 7)', function () {
    $document = changeIssuedSpk($this);
    $first = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->sole();
    changeLink($this, ['dokumen' => $document->public_id]);

    $this->actingAs($this->admin)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Nama sekolah keliru'])->assertRedirect();
    // The revision's draft prepares nothing new and the first version still satisfies the item until it issues.
    expect(changeActiveLinks($this))->toBe([['document_version_id' => $first->id, 'evidence_document_id' => null]]);

    $second = changeIssue($this, $document);
    expect(DocumentVersion::query()->whereKey($first->id)->value('state'))->toBe('SUPERSEDED')
        ->and(changeActiveLinks($this))->toBe([['document_version_id' => $second->id, 'evidence_document_id' => null]])
        ->and(DB::table('admin_requirement_links')->where('document_version_id', $first->id)->value('unlink_reason'))->toBe('The linked version was revised (L-41).')
        ->and(Administration::status($this->project, $this->item))->toBe(Readiness::SATISFIED);
});

it('opens the item an unlinked evidence satisfied, in the unlink\'s transaction (NX-14; L-41; architecture challenge 20a; criterion 8)', function () {
    $evidence = Administration::evidence($this->company, 'CLIENT_SPK');
    changeLink($this, ['bukti' => $evidence->public_id]);
    expect(Administration::status($this->project, $this->item))->toBe(Readiness::SATISFIED);
    $evidenceLink = (int) DB::table('evidence_links')->where('evidence_document_id', $evidence->id)->where('admin_requirement_id', $this->item)->value('id');

    $this->actingAs($this->admin)->post("/bukti/{$evidence->public_id}/tautan", [
        'command_id' => (string) Str::uuid(), 'action' => 'unlink', 'link' => LinkHandle::of($evidenceLink), 'reason' => 'Bukti salah unggah',
    ])->assertRedirect();

    expect(changeActiveLinks($this))->toBe([])
        ->and(DB::table('admin_requirement_links')->where('admin_requirement_id', $this->item)->value('unlink_reason'))->toBe('Bukti salah unggah')
        ->and(Administration::status($this->project, $this->item))->toBe(Readiness::MISSING);
});

it('judges a replaced evidence on its new current version — a reproduction no longer satisfies (NX-14; FS-12; architecture challenge 20a; criterion 8)', function () {
    $evidence = Administration::evidence($this->company, 'CLIENT_SPK');
    changeLink($this, ['bukti' => $evidence->public_id]);
    expect(Administration::status($this->project, $this->item))->toBe(Readiness::SATISFIED);

    // Its second version reproduces a company document's rendition.
    $company = Administration::document($this->project, 'DOC-09');
    $copy = FileObjectFactory::new()->createOne(['company_id' => $this->company->id, 'sha256' => Administration::renditionDigest($company)]);
    EvidenceDocumentVersionFactory::new()->createOne(['evidence_document_id' => $evidence->id, 'version_no' => 2, 'file_object_id' => $copy->id]);

    $this->actingAs($this->admin)->post("/bukti/{$evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'replace', 'version' => 2, 'reason' => 'Berkas yang benar'])->assertRedirect();

    expect(changeActiveLinks($this))->toBe([['document_version_id' => null, 'evidence_document_id' => $evidence->id]])
        ->and(Administration::status($this->project, $this->item))->toBe(Readiness::MISSING);
});
