<?php

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentType;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use App\Modules\FUL\Models\Delivery;
use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\FUL\Models\ServiceHandover;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\StockMovement;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\Purchase;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), the DOC rows of DATABASE §18 on documents, versions and renditions (§10): each type on
// its record-bound subject and one chain per subject and type, one issued and one draft version per
// chain, an issued version changed only by its supersession or its void, one issue per document per
// command (C-11); numbers unique in their company, type and period (C-10); a version's company, scheme
// and bank account its document's company's own (C-32); rendering never reissuing (C-12).

uses(RefreshDatabase::class);

/**
 * A document of the type on its subjects; each subject is named by a label, one real record of the
 * document's company per label, since TASK-007–TASK-009 key them in the company (DIR-089 (1); C-32).
 *
 * @param  array<string, mixed>  $attributes
 */
function documentOf(string $type, array $attributes = []): Document
{
    $company = $attributes['company_id'] ??= Company::factory()->create()->id;

    if (isset($attributes['project_id'])) {
        $number = "CTH/PRJ/LABEL/{$attributes['project_id']}";
        $attributes['project_id'] = Project::query()->where('company_id', $company)->where('project_number', $number)->value('id')
            ?? Project::factory()->create(['company_id' => $company, 'project_number' => $number])->id;
    }

    if (isset($attributes['purchase_id'])) {
        $number = "CTH/PO/LABEL/{$attributes['purchase_id']}";
        $attributes['purchase_id'] = Purchase::query()->where('company_id', $company)->where('purchase_number', $number)->value('id')
            ?? Purchase::factory()->create(['company_id' => $company, 'purchase_number' => $number])->id;
    }

    // A dispatch or receipt movement of the company per label, keyed by TASK-008 (DIR-089 (1)).
    foreach (['dispatch_movement_id' => 'DISPATCH', 'receiving_movement_id' => 'RECEIPT'] as $column => $movementType) {
        if (isset($attributes[$column])) {
            $label = "LABEL-{$attributes[$column]}";
            $attributes[$column] = StockMovement::query()->where('company_id', $company)->where('movement_type', $movementType)->where('reason', $label)->value('id')
                ?? StockMovement::factory()->create(['company_id' => $company, 'movement_type' => $movementType, 'reason' => $label])->id;
        }
    }

    // An invoice, payment, drop-ship confirmation, delivery or handover of the company per label, keyed by TASK-009 (DIR-089 (1)).
    static $subjects = [];
    $models = ['invoice_id' => Invoice::class, 'payment_id' => Payment::class, 'dropship_confirmation_id' => DropshipConfirmation::class,
        'delivery_id' => Delivery::class, 'service_handover_id' => ServiceHandover::class];

    foreach ($models as $column => $model) {
        if (isset($attributes[$column])) {
            $attributes[$column] = $subjects["{$company}/{$column}/{$attributes[$column]}"] ??= $model::factory()->create(['company_id' => $company])->id;
        }
    }

    return Document::factory()->create(['document_type_code' => DocumentTypeFactory::ensure($type), 'project_id' => null, ...$attributes]);
}

it('accepts each type on its own subject (§4.10; BR-DOC-04)', function (string $type, array $subject) {
    expect(documentOf($type, $subject)->exists)->toBeTrue();
})->with([
    'DOC-01 on a project' => ['DOC-01', ['project_id' => 7]],
    'DOC-02 on a purchase' => ['DOC-02', ['purchase_id' => 7]],
    'DOC-03 on a dispatch' => ['DOC-03', ['dispatch_movement_id' => 7]],
    'DOC-03 on a drop-ship confirmation' => ['DOC-03', ['dropship_confirmation_id' => 7]],
    'DOC-05 on a payment and its invoice' => ['DOC-05', ['mode' => 'RECEIPT', 'payment_id' => 7, 'invoice_id' => 8]],
    'DOC-05 before payment, on the invoice' => ['DOC-05', ['mode' => 'PRE_PAYMENT', 'invoice_id' => 8]],
    'DOC-08 on a service handover' => ['DOC-08', ['service_handover_id' => 7]],
    'DOC-10 on a delivery' => ['DOC-10', ['delivery_id' => 7]],
    'DOC-11 on a project' => ['DOC-11', ['project_id' => 7]],
    'DOC-12 on a receipt' => ['DOC-12', ['receiving_movement_id' => 7]],
    'DOC-13 on an invoice' => ['DOC-13', ['invoice_id' => 8]],
]);

it('refuses a type on another subject, on two, on none, and a mode outside DOC-05 (§4.10)', function (string $type, array $subject, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => documentOf($type, $subject));
})->with([
    'DOC-01 without its project' => ['DOC-01', [], 'documents_subject_by_type'],
    'DOC-01 on a project and an invoice' => ['DOC-01', ['project_id' => 7, 'invoice_id' => 8], 'documents_subject_by_type'],
    'DOC-03 on a dispatch and a drop-ship confirmation' => ['DOC-03', ['dispatch_movement_id' => 7, 'dropship_confirmation_id' => 8], 'documents_subject_by_type'],
    'DOC-05 in the standard mode' => ['DOC-05', ['invoice_id' => 8], 'documents_mode_by_type'],
    'DOC-05 as a receipt without its payment' => ['DOC-05', ['mode' => 'RECEIPT', 'invoice_id' => 8], 'documents_subject_by_type'],
    'DOC-04 as a receipt' => ['DOC-04', ['mode' => 'RECEIPT', 'invoice_id' => 8], 'documents_mode_by_type'],
    'DOC-08 on nothing' => ['DOC-08', [], 'documents_subject_by_type'],
    'DOC-10 on a service handover' => ['DOC-10', ['service_handover_id' => 7], 'documents_subject_by_type'],
    'DOC-12 on a dispatch' => ['DOC-12', ['dispatch_movement_id' => 7], 'documents_subject_by_type'],
]);

it('gives each record-bound subject one chain per type, each layout of one record its own (C-11; L-26)', function () {
    $company = Company::factory()->create();
    $own = ['company_id' => $company->id];

    documentOf('DOC-01', [...$own, 'project_id' => 7]);
    documentOf('DOC-04', [...$own, 'invoice_id' => 8]);
    documentOf('DOC-06', [...$own, 'invoice_id' => 8]);
    documentOf('DOC-05', [...$own, 'mode' => 'RECEIPT', 'payment_id' => 5, 'invoice_id' => 8]);
    documentOf('DOC-02', [...$own, 'purchase_id' => 3]);
    documentOf('DOC-03', [...$own, 'dispatch_movement_id' => 3]);
    documentOf('DOC-03', [...$own, 'dropship_confirmation_id' => 6]);
    documentOf('DOC-08', [...$own, 'delivery_id' => 4]);
    documentOf('DOC-08', [...$own, 'service_handover_id' => 9]);
    documentOf('DOC-10', [...$own, 'delivery_id' => 4]);
    documentOf('DOC-12', [...$own, 'receiving_movement_id' => 2]);

    Refusal::assert(Refusal::UNIQUE, 'documents_c11_quotation_chain', fn () => documentOf('DOC-01', [...$own, 'project_id' => 7]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_invoice_chain', fn () => documentOf('DOC-04', [...$own, 'invoice_id' => 8]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_receipt_chain', fn () => documentOf('DOC-05', [...$own, 'mode' => 'RECEIPT', 'payment_id' => 5, 'invoice_id' => 8]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_purchase_chain', fn () => documentOf('DOC-02', [...$own, 'purchase_id' => 3]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_dispatch_chain', fn () => documentOf('DOC-03', [...$own, 'dispatch_movement_id' => 3]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_dropship_chain', fn () => documentOf('DOC-03', [...$own, 'dropship_confirmation_id' => 6]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_handover_chain', fn () => documentOf('DOC-08', [...$own, 'service_handover_id' => 9]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_delivery_chain', fn () => documentOf('DOC-10', [...$own, 'delivery_id' => 4]));
    Refusal::assert(Refusal::UNIQUE, 'documents_c11_receiving_chain', fn () => documentOf('DOC-12', [...$own, 'receiving_movement_id' => 2]));

    // A pre-payment Kuitansi, DOC-09 and DOC-11 may have several chains (§4.10).
    documentOf('DOC-05', [...$own, 'mode' => 'PRE_PAYMENT', 'invoice_id' => 8]);
    documentOf('DOC-05', [...$own, 'mode' => 'PRE_PAYMENT', 'invoice_id' => 8]);
    documentOf('DOC-09', [...$own, 'project_id' => 7]);
    documentOf('DOC-09', [...$own, 'project_id' => 7]);

    expect(Document::query()->where('company_id', $company->id)->count())->toBe(15);
});

it('attaches a Kuitansi\'s detail to a Kuitansi of its own company, once (C-32; C-11)', function () {
    [$own, $other] = [Company::factory()->create(), Company::factory()->create()];
    $kuitansi = documentOf('DOC-05', ['company_id' => $own->id, 'mode' => 'RECEIPT', 'payment_id' => 51, 'invoice_id' => 52]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'documents_c32_parent_foreign', fn () => documentOf('DOC-07', ['company_id' => $other->id, 'parent_document_id' => $kuitansi->id]));

    documentOf('DOC-07', ['company_id' => $own->id, 'parent_document_id' => $kuitansi->id]);

    Refusal::assert(Refusal::UNIQUE, 'documents_c11_attachment_chain', fn () => documentOf('DOC-07', ['company_id' => $own->id, 'parent_document_id' => $kuitansi->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table documents', fn () => DB::table('documents')->where('id', $kuitansi->id)->update(['invoice_id' => 53]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table documents', fn () => DB::table('documents')->where('id', $kuitansi->id)->delete());
});

it('keeps one issued and one draft version per document (C-11)', function () {
    $document = documentOf('DOC-01', ['project_id' => 7]);
    DocumentVersion::factory()->for($document)->create();
    DocumentVersion::factory()->issued()->for($document)->create(['version_no' => 2]);

    Refusal::assert(Refusal::UNIQUE, 'document_versions_c11_one_draft', fn () => DocumentVersion::factory()->for($document)->create(['version_no' => 3]));
    Refusal::assert(Refusal::UNIQUE, 'document_versions_c11_one_issued', fn () => DocumentVersion::factory()->issued()->for($document)->create(['version_no' => 3]));
});

it('issues a version only with its number, payload and business date, and a Kuitansi only with its amount (C-11; §10)', function (Closure $write, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, $write);
})->with([
    'without its payload' => [fn () => DocumentVersion::factory()->issued()->create(['payload' => null]), 'document_versions_c11_issued_content'],
    'without its number' => [fn () => DocumentVersion::factory()->issued()->create(['formatted_number' => null]), 'document_versions_c11_issued_content'],
    'without its business date' => [fn () => DocumentVersion::factory()->issued()->create(['business_date' => null]), 'document_versions_issued_business_date'],
    'a Kuitansi without its amount' => [fn () => DocumentVersion::factory()->for(documentOf('DOC-05', ['mode' => 'RECEIPT', 'payment_id' => 1, 'invoice_id' => 2]))->create(), 'document_versions_kuitansi_amount'],
    'a Kuitansi of zero' => [fn () => DocumentVersion::factory()->for(documentOf('DOC-05', ['mode' => 'RECEIPT', 'payment_id' => 1, 'invoice_id' => 2]))->create(['kuitansi_amount' => '0.00']), 'document_versions_kuitansi_amount'],
    'a period that is no year or month' => [fn () => DocumentVersion::factory()->issued()->create(['period_key' => '26']), 'document_versions_period_key_format'],
    'a payload hash that is no SHA-256' => [fn () => DocumentVersion::factory()->issued()->create(['payload_sha256' => 'ABC']), 'document_versions_payload_sha256_hex'],
    'an unknown state' => [fn () => DocumentVersion::factory()->issued()->create(['state' => 'CANCELLED']), 'document_versions_state_code'],
]);

it('changes an issued version only by its supersession or its void, with its reason and actor, the number staying occupied (C-11; BR-DOC-01/02)', function () {
    $issued = DocumentVersion::factory()->issued()->create();
    $version = fn () => DB::table('document_versions')->where('id', $issued->id);
    $void = ['voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Nama klien salah ketik'];

    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['payload' => json_encode(['judul' => 'Diubah'])]));
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['formatted_number' => 'CTH/DOK/2026/9999']));
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['business_date' => '2026-10-02']));
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['company_bank_account_id' => null, 'kuitansi_amount' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['state' => 'DRAFT']));
    Refusal::assert(Refusal::CHECK, 'document_versions_void_columns', fn () => $version()->update(['state' => 'VOIDED']));

    $version()->update(['state' => 'VOIDED', ...$void]);

    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['voided_reason' => 'Alasan lain']));
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => $version()->update(['state' => 'SUPERSEDED']));

    $draft = DocumentVersion::factory()->create();

    Refusal::assert(Refusal::CHECK, 'document_versions_c11_issued_immutable', fn () => DB::table('document_versions')->where('id', $draft->id)->update(['state' => 'VOIDED', ...$void]));

    expect($version()->value('formatted_number'))->toBe($issued->formatted_number)
        ->and($version()->value('state'))->toBe('VOIDED');
});

it('supersedes linearly, within the version\'s own document (C-11; §13)', function () {
    $document = documentOf('DOC-01', ['project_id' => 11]);
    $first = DocumentVersion::factory()->issued()->for($document)->create();
    DB::table('document_versions')->where('id', $first->id)->update(['state' => 'SUPERSEDED']);
    DocumentVersion::factory()->issued()->for($document)->create(['version_no' => 2, 'supersedes_version_id' => $first->id]);
    $elsewhere = DocumentVersion::factory()->issued()->create();

    Refusal::assert(Refusal::UNIQUE, 'document_versions_c11_linear_chain', fn () => DocumentVersion::factory()->for($document)->create(['version_no' => 3, 'supersedes_version_id' => $first->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_c11_supersedes_foreign', fn () => DocumentVersion::factory()->for($document)->create(['version_no' => 3, 'supersedes_version_id' => $elsewhere->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table document_versions', fn () => DB::table('document_versions')->where('id', $first->id)->update(['supersedes_version_id' => null]));
});

it('supersedes an issued version only with the new version of its document, issued with it (C-11; §10)', function () {
    $document = documentOf('DOC-01', ['project_id' => 12]);
    $issued = DocumentVersion::factory()->issued()->for($document)->create();
    $supersede = fn () => DB::table('document_versions')->where('id', $issued->id)->update(['state' => 'SUPERSEDED']);
    // The check runs when the transaction commits; a test's transaction never does, so the test asks for it.
    $atCommit = fn () => DB::statement('set constraints document_versions_c11_superseded_by_successor immediate');

    Refusal::assert(Refusal::CHECK, 'document_versions_c11_superseded_by_successor', function () use ($supersede, $atCommit): void {
        $supersede();
        $atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'document_versions_c11_superseded_by_successor', function () use ($document, $issued, $supersede, $atCommit): void {
        DocumentVersion::factory()->for($document)->create(['version_no' => 2, 'supersedes_version_id' => $issued->id]);
        $supersede();
        $atCommit();
    });

    // The revision's draft, then its issue, which supersedes the version before it.
    $revision = DocumentVersion::factory()->for($document)->create(['version_no' => 2, 'supersedes_version_id' => $issued->id]);
    $supersede();
    DB::table('document_versions')->where('id', $revision->id)->update([
        'state' => 'ISSUED', 'business_date' => '2026-10-02', 'period_key' => '2026', 'sequence_no' => 990, 'formatted_number' => 'CTH/PNW/2026/0990',
        'payload' => json_encode(['judul' => 'Revisi']), 'issue_command_id' => (string) Str::uuid7(),
    ]);
    $atCommit();

    expect(DocumentVersion::query()->where('document_id', $document->id)->orderBy('version_no')->pluck('state')->all())->toBe(['SUPERSEDED', 'ISSUED']);
});

it('issues several documents in one command, each of them once (C-11; §1)', function () {
    $command = (string) Str::uuid7();
    DocumentVersion::factory()->issued()->create(['issue_command_id' => $command]);
    DocumentVersion::factory()->issued()->create(['issue_command_id' => $command]);
    $document = documentOf('DOC-01', ['project_id' => 21]);
    $first = DocumentVersion::factory()->issued()->for($document)->create(['issue_command_id' => (string) Str::uuid7()]);
    DB::table('document_versions')->where('id', $first->id)->update(['state' => 'SUPERSEDED']);

    Refusal::assert(Refusal::UNIQUE, 'document_versions_c11_one_issue_per_command', fn () => DocumentVersion::factory()->issued()->for($document)->create([
        'version_no' => 2, 'supersedes_version_id' => $first->id, 'issue_command_id' => $first->issue_command_id,
    ]));

    expect(DocumentVersion::query()->where('issue_command_id', $command)->count())->toBe(2);
});

it('keeps a number unique in its company, type and period, its formatted number too, a revision keeping it with a suffix (C-10; NM-09)', function () {
    $company = Company::factory()->create();
    [$first, $second] = [documentOf('DOC-01', ['company_id' => $company->id, 'project_id' => 31]), documentOf('DOC-01', ['company_id' => $company->id, 'project_id' => 32])];
    $number = ['period_key' => '2026', 'sequence_no' => 7, 'formatted_number' => 'CTH/PNW/2026/0007'];
    $issued = DocumentVersion::factory()->issued()->for($first)->create($number);

    Refusal::assert(Refusal::UNIQUE, 'document_versions_c10_number', fn () => DocumentVersion::factory()->issued()->for($second)->create([...$number, 'formatted_number' => 'CTH/PNW/2026/0007-B']));
    Refusal::assert(Refusal::UNIQUE, 'document_versions_c10_formatted_number', fn () => DocumentVersion::factory()->issued()->for($second)->create([...$number, 'sequence_no' => 8]));

    // BASE_SUFFIX: the revision keeps the base number with its suffix (§10; NM-07).
    DB::table('document_versions')->where('id', $issued->id)->update(['state' => 'SUPERSEDED']);
    $revision = DocumentVersion::factory()->issued()->for($first)->create([...$number, 'version_no' => 2, 'revision_suffix' => 1, 'formatted_number' => 'CTH/PNW/2026/0007-R1', 'supersedes_version_id' => $issued->id]);

    expect($revision->exists)->toBeTrue()
        // Another type and another company have series of their own.
        ->and(DocumentVersion::factory()->issued()->for(documentOf('DOC-09', ['company_id' => $company->id, 'project_id' => 31]))->create($number)->exists)->toBeTrue()
        ->and(DocumentVersion::factory()->issued()->for(documentOf('DOC-01', ['project_id' => 33]))->create($number)->exists)->toBeTrue();
});

it('keeps a version\'s company, type, numbering scheme and bank account its document\'s company\'s own (C-32; §14)', function () {
    [$own, $other] = [Company::factory()->create(), Company::factory()->create()];
    $document = documentOf('DOC-01', ['company_id' => $own->id, 'project_id' => 41]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_c32_bank_account_foreign', fn () => DocumentVersion::factory()->for($document)->create(['company_bank_account_id' => CompanyBankAccount::factory()->for($other)->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_c32_numbering_scheme_foreign', fn () => DocumentVersion::factory()->for($document)->create(['numbering_scheme_id' => NumberingScheme::factory()->for($other)->create(['sequence_type' => 'DOC-01'])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_document_foreign', fn () => DocumentVersion::factory()->for($document)->create(['company_id' => $other->id, 'document_type_code' => 'DOC-01']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_document_foreign', fn () => DocumentVersion::factory()->for($document)->create(['company_id' => $own->id, 'document_type_code' => DocumentTypeFactory::ensure('DOC-06')]));

    expect(DocumentVersion::factory()->for($document)->create([
        'company_bank_account_id' => CompanyBankAccount::factory()->for($own)->create()->id,
        'numbering_scheme_id' => NumberingScheme::factory()->for($own)->create(['sequence_type' => 'DOC-01'])->id,
    ])->exists)->toBeTrue();
});

it('deletes only a draft version (§13, §19)', function () {
    $draft = DocumentVersion::factory()->create();
    $issued = DocumentVersion::factory()->issued()->create();

    DB::table('document_versions')->where('id', $draft->id)->delete();

    Refusal::assert(Refusal::CHECK, 'document_versions_draft_delete_only', fn () => DB::table('document_versions')->where('id', $issued->id)->delete());

    expect(DB::table('document_versions')->where('id', $draft->id)->exists())->toBeFalse()
        ->and(DB::table('document_versions')->where('id', $issued->id)->exists())->toBeTrue();
});

it('renders without reissuing: no number or payload of its own, one READY attempt, each attempt once (C-12; BR-DOC-03)', function () {
    $version = DocumentVersion::factory()->issued()->create();
    $file = FileObject::factory()->create(['company_id' => $version->company_id, 'purpose' => 'RENDITION']);
    DocumentRendition::factory()->for($version, 'version')->create(['state' => 'FAILED', 'error_summary' => 'Batas waktu penyaji']);
    DocumentRendition::factory()->for($version, 'version')->create(['attempt_no' => 2, 'state' => 'READY', 'file_object_id' => $file->id]);

    Refusal::assert(Refusal::UNIQUE, 'document_renditions_one_ready', fn () => DocumentRendition::factory()->for($version, 'version')->create(['attempt_no' => 3, 'state' => 'READY']));
    Refusal::assert(Refusal::UNIQUE, 'document_renditions_attempt_unique', fn () => DocumentRendition::factory()->for($version, 'version')->create(['attempt_no' => 2]));
    Refusal::assert(Refusal::CHECK, 'document_renditions_state_code', fn () => DocumentRendition::factory()->for($version, 'version')->create(['attempt_no' => 4, 'state' => 'DONE']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table document_renditions', fn () => DB::table('document_renditions')->where('document_version_id', $version->id)->delete());

    expect(Schema::getColumnListing('document_renditions'))->not->toContain('formatted_number', 'sequence_no', 'period_key', 'payload', 'payload_sha256');
});

it('keeps the document catalogue to the fourteen types and their three modes (CAP-08)', function (array $type, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => DocumentType::factory()->create($type));
})->with([
    'a fifteenth type' => [['code' => 'DOC-15'], 'document_types_code_catalogue'],
    'an unknown mode' => [['code' => 'DOC-02', 'allowed_modes' => ['DRAFT']], 'document_types_allowed_modes'],
    'no mode' => [['code' => 'DOC-02', 'allowed_modes' => []], 'document_types_allowed_modes'],
]);
