<?php

use App\Modules\CAT\Models\ProductImage;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Queries\FileAccess;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\FileObjectFactory;
use Database\Factories\OPS\ImportBatchFactory;
use Database\Factories\OPS\LegacyArchiveItemFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-022; PERMISSIONS_MATRIX DP-05, PJ-08, PJ-15, PJ-19, PJ-20, rows 27 and 124; SECURITY FL-07; TM-20, TM-55;
// criterion 1): a file is served only through its owning record, by family and scope — a rendition by its document's
// family, an evidence file by `evidence.view` and its type's family, pool evidence by the pool's readers, a logo to its
// company's viewers and a stamp or signature to the Owner only, a product image to every account, an import source by
// the import rule, an archive item's file to `finance.view` with `evidence.view` — and never across companies.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->other = Company::factory()->create();
});

function allowed(User $viewer, FileObject $file): bool
{
    app(EffectiveCapabilities::class)->forget();

    try {
        app(FileAccess::class)->find(app(EffectiveCapabilities::class)->for($viewer), $file->public_id);

        return true;
    } catch (ModelNotFoundException) {
        return false;
    }
}

function renditionOf(int $companyId, string $type): FileObject
{
    $version = DocumentVersion::factory()->issued()->for(
        Document::factory()->state(['company_id' => $companyId, 'document_type_code' => DocumentTypeFactory::ensure($type)]),
        'document',
    )->create();
    $file = FileObjectFactory::new()->create(['purpose' => 'RENDITION', 'company_id' => $companyId]);
    DocumentRendition::factory()->create(['document_version_id' => $version->id, 'state' => 'READY', 'file_object_id' => $file->id]);

    return $file;
}

function evidenceOf(?int $companyId, string $type): FileObject
{
    $evidence = EvidenceDocument::factory()->create([
        'evidence_scope' => $companyId === null ? 'POOL' : 'COMPANY', 'company_id' => $companyId, 'evidence_type' => $type,
        'identity_key' => 'contoh|'.$type.'|'.uniqid(), 'issuer_reference' => uniqid(),
    ]);
    $version = EvidenceDocumentVersion::factory()->create(['evidence_document_id' => $evidence->id]);

    return FileObject::query()->findOrFail($version->file_object_id);
}

it('opens a rendition by its document\'s family within its company (PJ-15; TM-20)', function (string $type, string $family, string $other) {
    $file = renditionOf($this->company->id, $type);

    expect(allowed(Authorization::admin([$this->company->id], [$family]), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->company->id], [$other]), $file))->toBeFalse()
        ->and(allowed(Authorization::admin([$this->other->id], [$family]), $file))->toBeFalse()
        ->and(allowed(Authorization::owner(), $file))->toBeTrue();
})->with([
    'an invoice, finance' => ['DOC-01', 'finance.view', 'projects.view'],
]);

it('opens an evidence file by evidence.view and its type\'s family within its company (PJ-08; TM-55)', function (string $type, string $family, string $other) {
    $file = evidenceOf($this->company->id, $type);

    expect(allowed(Authorization::admin([$this->company->id], ['evidence.view', $family]), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->company->id], [$family]), $file))->toBeFalse()
        ->and(allowed(Authorization::admin([$this->company->id], ['evidence.view', $other]), $file))->toBeFalse()
        ->and(allowed(Authorization::admin([$this->other->id], ['evidence.view', $family]), $file))->toBeFalse();
})->with([
    'a bank statement, finance' => ['BANK_STATEMENT', 'finance.view', 'cost.view'],
    'a supplier bill, cost' => ['SUPPLIER_BILL', 'cost.view', 'finance.view'],
    'a client order, project' => ['CLIENT_ORDER', 'projects.view', 'finance.view'],
    'a tax invoice, cost' => ['TAX_INVOICE', 'cost.view', 'projects.view'],
]);

it('opens pool evidence to the pool\'s readers, with no company needed (PJ-08; CS-05)', function () {
    $file = evidenceOf(null, 'WAREHOUSE_RECORD');

    // `evidence.view` holds while some company is granted (its GRANT prerequisite); the pool needs none of them.
    expect(allowed(Authorization::admin([$this->other->id], ['stock.view', 'evidence.view']), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->other->id], ['stock.view']), $file))->toBeFalse()
        ->and(allowed(Authorization::admin([$this->other->id], ['evidence.view']), $file))->toBeFalse();
});

it('opens a company\'s logo to its viewers and its stamp and signature to the Owner only (PJ-19)', function () {
    $logo = FileObjectFactory::new()->create(['purpose' => 'IDENTITY_ASSET', 'company_id' => $this->company->id, 'mime_type' => 'image/png']);
    $stamp = FileObjectFactory::new()->create(['purpose' => 'IDENTITY_ASSET', 'company_id' => $this->company->id, 'mime_type' => 'image/png']);
    DB::table('companies')->where('id', $this->company->id)->update(['logo_file_id' => $logo->id, 'stamp_file_id' => $stamp->id]);
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);

    expect(allowed($viewer, $logo))->toBeTrue()
        ->and(allowed($viewer, $stamp))->toBeFalse()
        ->and(allowed(Authorization::owner(), $stamp))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->other->id], ['projects.view']), $logo))->toBeFalse();
});

it('opens an import source to the Owner and to import.prepare with every company of its batch (PJ-20)', function () {
    $batch = ImportBatchFactory::new()->create(['company_scope' => [$this->company->id, $this->other->id]]);
    $file = FileObject::query()->findOrFail($batch->source_file_id);

    expect(allowed(Authorization::owner(), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->company->id, $this->other->id], ['projects.view', 'import.prepare']), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->company->id], ['import.prepare']), $file))->toBeFalse();
});

it('opens an archive item\'s file to finance.view with evidence.view in its company (row 124)', function () {
    $file = FileObjectFactory::new()->create(['purpose' => 'ARCHIVE', 'company_id' => $this->company->id]);
    LegacyArchiveItemFactory::new()->create(['company_id' => $this->company->id, 'file_object_id' => $file->id]);

    expect(allowed(Authorization::admin([$this->company->id], ['finance.view', 'evidence.view']), $file))->toBeTrue()
        ->and(allowed(Authorization::admin([$this->company->id], ['finance.view']), $file))->toBeFalse();
});

it('opens a product image to every active account, and nothing to an inactive one (row 27)', function () {
    $image = ProductImage::factory()->create();
    $file = FileObject::query()->findOrFail($image->file_object_id);
    $inactive = Authorization::admin();
    DB::table('users')->where('id', $inactive->id)->update(['is_active' => false]);

    expect(allowed(Authorization::admin(), $file))->toBeTrue()
        ->and(allowed($inactive, $file))->toBeFalse();
});
