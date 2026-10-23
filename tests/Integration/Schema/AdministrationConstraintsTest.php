<?php

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\ADM\Models\AdminRequirementLink;
use App\Modules\ADM\Models\AdminRequirementWaiver;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-007), criteria 1 and 2: the ADM rows of DATABASE §18 on a project's administrative
// requirements (§4.11) — each requirement within its project's company and its links to that company's
// documents and evidence (C-32), a client original satisfied only by an evidence (§10; FS-12), a link
// unlinked once (§19), a removal with its actor, time and reason, and waivers corrected only by a linear
// chain within their requirement (L-32; C-59).

uses(RefreshDatabase::class);

it('states what a requirement asks for by its kind, and a client original only by upload (BR-ADM-01; FS-12)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => AdminRequirement::factory()->create($attributes));
})->with([
    'a catalogue document without its type' => [['requirement_kind' => 'CATALOG_DOCUMENT'], 'admin_requirements_reference_by_kind'],
    'an external document without its type' => [['requirement_kind' => 'EXTERNAL_DOCUMENT'], 'admin_requirements_reference_by_kind'],
    'free text naming an evidence type' => [['evidence_type' => 'CLIENT_ORDER'], 'admin_requirements_reference_by_kind'],
    'an unknown evidence type' => [['requirement_kind' => 'EXTERNAL_DOCUMENT', 'evidence_type' => 'RECEIPT'], 'admin_requirements_evidence_type_code'],
    'a client original that may be generated' => [['requires_client_original' => true, 'satisfaction_mode' => 'EITHER'], 'admin_requirements_client_original_uploaded'],
    'an unknown mode' => [['satisfaction_mode' => 'SCANNED'], 'admin_requirements_satisfaction_mode_code'],
]);

it('keeps a requirement in its project\'s company and removes it with its actor, time and reason, never deleting it (C-32; §19)', function () {
    $requirement = AdminRequirement::factory()->create();
    $row = fn () => DB::table('admin_requirements')->where('id', $requirement->id);

    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirements_c32_project_foreign', fn () => AdminRequirement::factory()->create(['project_id' => $requirement->project_id, 'company_id' => Project::factory()->create()->company_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirements_document_type_code_foreign', fn () => AdminRequirement::factory()->create(['requirement_kind' => 'CATALOG_DOCUMENT', 'document_type_code' => 'DOC-09']));
    Refusal::assert(Refusal::CHECK, 'admin_requirements_removal_columns', fn () => $row()->update(['state' => 'REMOVED']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table admin_requirements', fn () => $row()->delete());

    $row()->update(['state' => 'REMOVED', 'removed_by' => User::factory()->create()->id, 'removed_at' => now(), 'removed_reason' => 'Tidak diminta klien']);

    expect($row()->value('state'))->toBe('REMOVED');
});

it('links a requirement to one document version or evidence of its company (C-32; D-DB-10)', function () {
    $requirement = AdminRequirement::factory()->create();
    $own = ['admin_requirement_id' => $requirement->id];
    $version = DocumentVersion::factory()->issued()->create(['document_id' => Document::factory()->create(['company_id' => $requirement->company_id])->id]);

    Refusal::assert(Refusal::CHECK, 'admin_requirement_links_one_target', fn () => AdminRequirementLink::factory()->create([...$own, 'document_version_id' => $version->id]));
    Refusal::assert(Refusal::CHECK, 'admin_requirement_links_one_target', fn () => AdminRequirementLink::factory()->create([...$own, 'evidence_document_id' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirement_links_c32_evidence_foreign', fn () => AdminRequirementLink::factory()->create([...$own, 'evidence_document_id' => EvidenceDocument::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirement_links_c32_document_version_foreign', fn () => AdminRequirementLink::factory()->create([...$own, 'evidence_document_id' => null, 'document_version_id' => DocumentVersion::factory()->issued()->create()->id]));

    expect(AdminRequirementLink::factory()->create([...$own, 'evidence_document_id' => null, 'document_version_id' => $version->id])->documentVersion?->is($version))->toBeTrue();
});

it('satisfies a client original only by an evidence, never a company document (§10; FS-12)', function () {
    $requirement = AdminRequirement::factory()->clientOriginal()->create();
    $version = DocumentVersion::factory()->issued()->create(['document_id' => Document::factory()->create(['company_id' => $requirement->company_id])->id]);

    Refusal::assert(Refusal::CHECK, 'admin_requirement_links_client_original_evidence', fn () => AdminRequirementLink::factory()->create([
        'admin_requirement_id' => $requirement->id, 'evidence_document_id' => null, 'document_version_id' => $version->id,
    ]));

    expect(AdminRequirementLink::factory()->create(['admin_requirement_id' => $requirement->id])->evidence?->company_id)->toBe($requirement->company_id);
});

it('makes a requirement one for a client original only once no document version satisfies it (§10; FS-12)', function () {
    $requirement = AdminRequirement::factory()->create();
    $version = DocumentVersion::factory()->issued()->create(['document_id' => Document::factory()->create(['company_id' => $requirement->company_id])->id]);
    $link = AdminRequirementLink::factory()->create(['admin_requirement_id' => $requirement->id, 'evidence_document_id' => null, 'document_version_id' => $version->id]);
    $tighten = fn () => DB::table('admin_requirements')->where('id', $requirement->id)->update(['requires_client_original' => true]);

    Refusal::assert(Refusal::CHECK, 'admin_requirements_client_original_evidence', $tighten);

    DB::table('admin_requirement_links')->where('id', $link->id)->update(['unlinked_at' => now(), 'unlinked_by' => User::factory()->create()->id, 'unlink_reason' => 'Klien meminta dokumen asli']);
    $tighten();

    expect(DB::table('admin_requirements')->where('id', $requirement->id)->value('requires_client_original'))->toBeTrue();
});

it('unlinks a link once, with its reason and actor, changing nothing else (§19)', function () {
    $link = AdminRequirementLink::factory()->create();
    $row = fn () => DB::table('admin_requirement_links')->where('id', $link->id);
    $unlink = ['unlinked_at' => now(), 'unlinked_by' => User::factory()->create()->id, 'unlink_reason' => 'Berkas salah unggah'];

    Refusal::assert(Refusal::CHECK, 'admin_requirement_links_unlink_columns', fn () => $row()->update(['unlinked_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table admin_requirement_links', fn () => $row()->update(['evidence_document_id' => EvidenceDocument::factory()->create()->id]));

    $row()->update($unlink);

    Refusal::assert(Refusal::CHECK, 'admin_requirement_links_unlink_once', fn () => $row()->update(['unlink_reason' => 'Alasan lain']));

    expect($row()->value('unlink_reason'))->toBe('Berkas salah unggah');
});

it('corrects a waiver only by a linear chain of superseding decisions on its requirement (BR-ADM-02; L-32)', function () {
    $waiver = AdminRequirementWaiver::factory()->create();
    $same = ['admin_requirement_id' => $waiver->admin_requirement_id];

    AdminRequirementWaiver::factory()->create([...$same, 'supersedes_waiver_id' => $waiver->id, 'reopened_by_correction' => true]);

    Refusal::assert(Refusal::UNIQUE, 'admin_requirement_waivers_linear_chain', fn () => AdminRequirementWaiver::factory()->create([...$same, 'supersedes_waiver_id' => $waiver->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirement_waivers_supersedes_foreign', fn () => AdminRequirementWaiver::factory()->create(['supersedes_waiver_id' => AdminRequirementWaiver::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'admin_requirement_waivers_c32_requirement_foreign', fn () => AdminRequirementWaiver::factory()->create([...$same, 'company_id' => Project::factory()->create()->company_id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table admin_requirement_waivers', fn () => DB::table('admin_requirement_waivers')->where('id', $waiver->id)->update(['reason' => 'Diubah']));
});
