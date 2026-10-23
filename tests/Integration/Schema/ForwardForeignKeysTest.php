<?php

use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), criterion 6 and DIR-088 (1): the foreign keys this Task adds from TASK-005's columns
// to `companies` and `file_objects` exist, exactly those, each restricting a delete, and each refuses a
// row that references no company or file — the legacy archive item's file also another company's, its
// key carrying the company (C-32; DATABASE §15). `command_log`'s and `security_events`' company columns
// stay plain values (DATABASE §4.13).

uses(RefreshDatabase::class);

const FOUNDATION_TABLES = [
    'users', 'roles', 'capabilities', 'role_capabilities', 'user_roles', 'user_capability_grants', 'user_company_grants', 'user_external_identities',
    'audit_events', 'security_events', 'command_log', 'correction_cases', 'import_batches', 'import_rows', 'legacy_archive_items',
    'sessions', 'credential_tokens', 'export_requests', 'failed_jobs',
];

it('adds exactly the foreign keys DIR-088 assigns to this Task, each restricting a delete', function () {
    $keys = DB::select("select conname, pg_get_constraintdef(oid) as definition from pg_constraint
        where contype = 'f' and conrelid::regclass::text = any (?::text[]) and confrelid::regclass::text in ('companies', 'file_objects')
        order by conname", ['{'.implode(',', FOUNDATION_TABLES).'}']);

    expect(array_column($keys, 'definition', 'conname'))->toBe([
        'audit_events_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'correction_cases_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'export_requests_file_object_id_foreign' => 'FOREIGN KEY (file_object_id) REFERENCES file_objects(id) ON DELETE RESTRICT',
        'import_batches_source_file_id_foreign' => 'FOREIGN KEY (source_file_id) REFERENCES file_objects(id) ON DELETE RESTRICT',
        'import_rows_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'legacy_archive_items_c32_file_object_foreign' => 'FOREIGN KEY (company_id, file_object_id) REFERENCES file_objects(company_id, id) ON DELETE RESTRICT',
        'legacy_archive_items_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'user_company_grants_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
    ]);
});

it('refuses a TASK-005 row that references no company (DIR-088; C-32)', function (Closure $write, string $constraint) {
    Refusal::assert(Refusal::FOREIGN_KEY, $constraint, $write);
})->with([
    'a company grant' => [fn () => UserCompanyGrant::factory()->create(['company_id' => 999_999]), 'user_company_grants_company_id_foreign'],
    'a correction case' => [fn () => CorrectionCase::factory()->create(['company_id' => 999_999]), 'correction_cases_company_id_foreign'],
    'an import row' => [fn () => ImportRow::factory()->create(['company_id' => 999_999]), 'import_rows_company_id_foreign'],
    'a legacy archive item' => [fn () => LegacyArchiveItem::factory()->create(['company_id' => 999_999]), 'legacy_archive_items_company_id_foreign'],
    'an audit event' => [fn () => AuditEvent::factory()->create(['company_id' => 999_999]), 'audit_events_company_id_foreign'],
]);

it('refuses a reference to no file, and a legacy archive item holding a file not its company\'s (DIR-088; C-32; §15)', function () {
    $company = Company::factory()->create();
    $archived = fn (int $file) => LegacyArchiveItem::factory()->create(['company_id' => $company->id, 'file_object_id' => $file]);
    $request = DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => "{{$company->id}}",
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(FileObject::factory()->create(['purpose' => 'ARCHIVE'])->id));
    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(FileObject::factory()->productImage()->create()->id));
    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(999_999));
    Refusal::assert(Refusal::FOREIGN_KEY, 'import_batches_source_file_id_foreign', fn () => ImportBatch::factory()->create(['source_file_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'export_requests_file_object_id_foreign', fn () => DB::table('export_requests')->where('id', $request)->update(['file_object_id' => 999_999]));
});

it('accepts the rows that reference their own company and file', function () {
    $company = Company::factory()->create();
    $file = FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'ARCHIVE']);
    // An export belongs to no company: its request names them (DIR-089 (5)).
    $export = FileObject::factory()->create(['company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'EXPORT']);
    $request = DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => "{{$company->id}}",
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ]);
    DB::table('export_requests')->where('id', $request)->update(['state' => 'READY', 'file_object_id' => $export->id]);

    expect(UserCompanyGrant::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(CorrectionCase::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(ImportRow::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(AuditEvent::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(LegacyArchiveItem::factory()->create(['company_id' => $company->id, 'file_object_id' => $file->id])->file?->is($file))->toBeTrue()
        ->and(ImportBatch::factory()->create()->sourceFile?->purpose)->toBe('IMPORT_SOURCE')
        ->and(DB::table('export_requests')->where('id', $request)->value('file_object_id'))->toBe($export->id)
        // The company context of an audit event and of an import row is optional (DATABASE §4.13).
        ->and(AuditEvent::factory()->create()->company_id)->toBeNull()
        ->and(ImportRow::factory()->create()->company_id)->toBeNull();
});
