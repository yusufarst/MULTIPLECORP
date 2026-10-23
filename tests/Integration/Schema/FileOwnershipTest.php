<?php

use App\Modules\CAT\Models\ProductImage;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), DIR-089 (5) and C-32 on the files (DATABASE §4.10, §22): `file_objects` takes only the
// company its purpose allows, and each reference to it holds a file of its owning record's company — or of
// none where the purpose has none — and of the purpose its role takes, refused at insert and at update;
// every reference to `file_objects` is guarded, so a later table cannot hold a file unchecked.

uses(RefreshDatabase::class);

it('refuses a file whose company its purpose does not take (DIR-089 (5); C-32)', function (array $attributes) {
    Refusal::assert(Refusal::CHECK, 'file_objects_c32_company_by_purpose', fn () => FileObject::factory()->create($attributes));
})->with([
    'a product image of a company' => [['purpose' => 'PRODUCT_IMAGE', 'sensitivity' => 'S0']],
    'an import source of a company' => [['purpose' => 'IMPORT_SOURCE', 'sensitivity' => 'S4']],
    'an export of a company' => [['purpose' => 'EXPORT', 'sensitivity' => 'S4']],
    'a rendition of no company' => [['purpose' => 'RENDITION', 'company_id' => null]],
    'an identity asset of no company' => [['purpose' => 'IDENTITY_ASSET', 'company_id' => null]],
    'an archive file of no company' => [['purpose' => 'ARCHIVE', 'company_id' => null]],
]);

it('accepts each purpose with the company it takes', function (string $purpose, bool $ofACompany) {
    $file = FileObject::factory()->create(['purpose' => $purpose, ...($ofACompany ? [] : ['company_id' => null])]);

    expect($file->company_id !== null)->toBe($ofACompany);
})->with([
    'a product image of no company' => ['PRODUCT_IMAGE', false],
    'an import source of no company' => ['IMPORT_SOURCE', false],
    'an export of no company' => ['EXPORT', false],
    'a rendition of a company' => ['RENDITION', true],
    'an identity asset of a company' => ['IDENTITY_ASSET', true],
    'an archive file of a company' => ['ARCHIVE', true],
    'an upload of a company' => ['UPLOAD', true],
    'an upload of no company, for pool evidence' => ['UPLOAD', false],
]);

it('holds an evidence version to an upload of its evidence\'s company, or of none for pool evidence (DIR-089 (5); C-32)', function () {
    $evidence = EvidenceDocument::factory()->create();
    $pool = EvidenceDocument::factory()->pool()->create();
    $version = fn (EvidenceDocument $owner, FileObject $file) => EvidenceDocumentVersion::factory()->for($owner, 'evidence')->create(['file_object_id' => $file->id]);

    Refusal::assert(Refusal::CHECK, 'evidence_document_versions_c32_file', fn () => $version($evidence, FileObject::factory()->create()));
    Refusal::assert(Refusal::CHECK, 'evidence_document_versions_c32_file', fn () => $version($evidence, FileObject::factory()->create(['company_id' => null])));
    Refusal::assert(Refusal::CHECK, 'evidence_document_versions_c32_file', fn () => $version($evidence, FileObject::factory()->create(['company_id' => $evidence->company_id, 'purpose' => 'RENDITION'])));
    Refusal::assert(Refusal::CHECK, 'evidence_document_versions_c32_file', fn () => $version($pool, FileObject::factory()->create()));

    expect($version($evidence, FileObject::factory()->create(['company_id' => $evidence->company_id]))->exists)->toBeTrue()
        ->and($version($pool, FileObject::factory()->create(['company_id' => null]))->exists)->toBeTrue();
});

it('holds a rendition to a rendered file of its version\'s company, at insert and at update (DIR-089 (5); C-32)', function () {
    $version = DocumentVersion::factory()->issued()->create();
    $pending = DocumentRendition::factory()->for($version, 'version')->create();
    $own = FileObject::factory()->create(['company_id' => $version->company_id, 'purpose' => 'RENDITION']);
    $foreign = FileObject::factory()->create(['purpose' => 'RENDITION']);
    $upload = FileObject::factory()->create(['company_id' => $version->company_id]);

    Refusal::assert(Refusal::CHECK, 'document_renditions_c32_file', fn () => DocumentRendition::factory()->for($version, 'version')->create(['attempt_no' => 2, 'file_object_id' => $foreign->id]));
    Refusal::assert(Refusal::CHECK, 'document_renditions_c32_file', fn () => DB::table('document_renditions')->where('id', $pending->id)->update(['file_object_id' => $foreign->id]));
    Refusal::assert(Refusal::CHECK, 'document_renditions_c32_file', fn () => DB::table('document_renditions')->where('id', $pending->id)->update(['file_object_id' => $upload->id]));

    DB::table('document_renditions')->where('id', $pending->id)->update(['state' => 'READY', 'file_object_id' => $own->id]);

    expect($pending->refresh()->file_object_id)->toBe($own->id);
});

it('holds a product image to a product-image file of no company (DIR-089 (5); C-32)', function () {
    Refusal::assert(Refusal::CHECK, 'product_images_c32_file', fn () => ProductImage::factory()->create(['file_object_id' => FileObject::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'product_images_c32_file', fn () => ProductImage::factory()->create(['file_object_id' => FileObject::factory()->create(['company_id' => null])->id]));

    expect(ProductImage::factory()->create()->exists)->toBeTrue();
});

it('holds a company\'s logo, stamp and signature to its own identity-asset files (DIR-089 (5); C-32)', function (string $column) {
    $company = Company::factory()->create();
    $rendition = FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'RENDITION']);
    $asset = FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'IDENTITY_ASSET']);

    Refusal::assert(Refusal::CHECK, 'companies_c32_file', fn () => DB::table('companies')->where('id', $company->id)->update([$column => $rendition->id]));

    DB::table('companies')->where('id', $company->id)->update([$column => $asset->id]);

    expect(DB::table('companies')->where('id', $company->id)->value($column))->toBe($asset->id);
})->with(['logo_file_id', 'stamp_file_id', 'signature_file_id']);

it('holds a legacy archive item to an archive file of its company (DIR-089 (5); C-32)', function () {
    $company = Company::factory()->create();
    $item = fn (FileObject $file) => LegacyArchiveItem::factory()->create(['company_id' => $company->id, 'file_object_id' => $file->id]);

    Refusal::assert(Refusal::CHECK, 'legacy_archive_items_c32_file', fn () => $item(FileObject::factory()->create(['company_id' => $company->id])));

    expect($item(FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'ARCHIVE']))->exists)->toBeTrue();
});

it('holds an import batch\'s source to an import-source file of no company (DIR-089 (5); C-32)', function () {
    Refusal::assert(Refusal::CHECK, 'import_batches_c32_file', fn () => ImportBatch::factory()->create(['source_file_id' => FileObject::factory()->create(['purpose' => 'RENDITION'])->id]));
    Refusal::assert(Refusal::CHECK, 'import_batches_c32_file', fn () => ImportBatch::factory()->create(['source_file_id' => FileObject::factory()->create(['company_id' => null])->id]));

    expect(ImportBatch::factory()->create()->sourceFile?->purpose)->toBe('IMPORT_SOURCE');
});

it('holds an export request to an export file of no company, at insert and at update (DIR-089 (5); C-32)', function () {
    $request = fn (array $columns = []) => DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => '{1}',
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
        ...$columns,
    ]);
    $upload = FileObject::factory()->create();
    $export = FileObject::factory()->create(['company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'EXPORT']);
    $pending = $request();

    Refusal::assert(Refusal::CHECK, 'export_requests_c32_file', fn () => $request(['file_object_id' => $upload->id]));
    Refusal::assert(Refusal::CHECK, 'export_requests_c32_file', fn () => DB::table('export_requests')->where('id', $pending)->update(['file_object_id' => $upload->id]));

    DB::table('export_requests')->where('id', $pending)->update(['state' => 'READY', 'file_object_id' => $export->id]);

    expect(DB::table('export_requests')->where('id', $pending)->value('file_object_id'))->toBe($export->id);
});

it('refuses a second import batch on another batch\'s source file (DIR-089 (5); C-32)', function () {
    $batch = ImportBatch::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'import_batches_c32_source_file_unique', fn () => ImportBatch::factory()->create(['source_file_id' => $batch->source_file_id]));
});

it('refuses a second export request on another request\'s export file (DIR-089 (5); C-32)', function () {
    $export = FileObject::factory()->create(['company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'EXPORT']);
    $request = fn () => DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => '{1}',
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
        'file_object_id' => $export->id,
    ]);
    $request();

    Refusal::assert(Refusal::UNIQUE, 'export_requests_c32_file_unique', $request);
});

it('refuses another evidence version on a file a version already holds, pool evidence too (DIR-089 (5); C-32)', function () {
    $pool = EvidenceDocument::factory()->pool()->create();
    $held = EvidenceDocumentVersion::factory()->for($pool, 'evidence')->create();
    $other = EvidenceDocument::factory()->pool()->create();

    Refusal::assert(Refusal::UNIQUE, 'evidence_document_versions_c32_file_unique', fn () => EvidenceDocumentVersion::factory()->for($other, 'evidence')->create(['file_object_id' => $held->file_object_id]));
});

it('guards every reference to a file, after its row, at insert and at update of the reference (DIR-089 (5); C-32)', function () {
    $references = DB::select(<<<'SQL'
        select c.conrelid::regclass::text || '.' || a.attname as reference,
            exists (
                select from pg_trigger t
                where t.tgrelid = c.conrelid and not t.tgisinternal and t.tgname = c.conrelid::regclass::text || '_c32_file'
                    and (t.tgtype::int & 1) = 1 and (t.tgtype::int & 2) = 0 and (t.tgtype::int & 4) = 4 and (t.tgtype::int & 16) = 16
                    and k.referencing_attnum = any (t.tgattr::int2[])
            ) as guarded
        from pg_constraint c
        cross join lateral unnest(c.conkey, c.confkey) as k(referencing_attnum, referenced_attnum)
        join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.referencing_attnum
        join pg_attribute r on r.attrelid = c.confrelid and r.attnum = k.referenced_attnum
        where c.contype = 'f' and c.confrelid = 'file_objects'::regclass and r.attname = 'id'
        order by (c.conrelid::regclass::text || '.' || a.attname) collate "C"
        SQL);

    expect(array_column($references, 'reference'))->toBe([
        'companies.logo_file_id', 'companies.signature_file_id', 'companies.stamp_file_id',
        'document_renditions.file_object_id', 'evidence_document_versions.file_object_id', 'export_requests.file_object_id',
        'import_batches.source_file_id', 'legacy_archive_items.file_object_id', 'product_images.file_object_id',
    ])->and(array_column($references, 'guarded'))->each->toBeTrue();
});
