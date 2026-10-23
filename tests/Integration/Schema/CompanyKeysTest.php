<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005, TASK-006), C-32's keys: every company-owned table carries UNIQUE (company_id, id) — and
// `import_rows` (id, company_id) for the opening lots' provenance — so every reference to it is a
// composite foreign key that cannot cross companies (DATABASE §1, §15). Where the referencing tables
// arrive with TASK-007–TASK-009, a scratch table, created by the migration role inside a transaction
// that is rolled back, references the key the way they will; the references TASK-006 makes itself are
// proven by their own tests.

uses(RefreshDatabase::class);

it('lets a composite reference reach its own company\'s row and refuses another company\'s (C-32)', function (string $table, string $columns, string $key, array $row) {
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        [$own, $other] = [
            $owner->table('companies')->insertGetId(['code' => 'KUNCI-A', 'legal_name' => 'CV Kunci A']),
            $owner->table('companies')->insertGetId(['code' => 'KUNCI-B', 'legal_name' => 'CV Kunci B']),
        ];

        if ($table === 'import_rows') {
            $file = $owner->table('file_objects')->insertGetId([
                'storage_disk' => 'local', 'storage_key' => 'berkas/kunci', 'mime_type' => 'text/csv', 'size_bytes' => 1,
                'sha256' => hash('sha256', 'kunci'), 'company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'IMPORT_SOURCE',
            ]);
            $row['import_batch_id'] = $owner->table('import_batches')->insertGetId(['import_kind' => 'CONTOH', 'source_file_id' => $file, 'source_sha256' => hash('sha256', 'kunci'), 'cutoff_date' => '2026-09-30', 'company_scope' => "{{$own}}"]);
        }

        $id = $owner->table($table)->insertGetId([...$row, 'company_id' => $own]);
        $owner->statement("create table company_reference (company_id bigint not null, record_id bigint not null, foreign key {$columns} references {$table} {$key})");

        $owner->table('company_reference')->insert(['company_id' => $own, 'record_id' => $id]);

        Refusal::assert(Refusal::FOREIGN_KEY, 'company_reference', fn () => $owner->table('company_reference')->insert(['company_id' => $other, 'record_id' => $id]), DatabaseRole::MIGRATION_CONNECTION);
    } finally {
        $owner->rollBack();
    }
})->with([
    'correction cases' => ['correction_cases', '(company_id, record_id)', '(company_id, id)', ['case_kind' => 'OTHER']],
    'legacy archive items' => ['legacy_archive_items', '(company_id, record_id)', '(company_id, id)', ['archive_kind' => 'CONTOH', 'legacy_reference' => 'ARSIP-K']],
    'import rows' => ['import_rows', '(record_id, company_id)', '(id, company_id)', ['row_number' => 1, 'import_kind' => 'CONTOH', 'import_identity' => 'LEGACY-K', 'validation_state' => 'VALID']],
    'company tax profiles' => ['company_tax_profiles', '(company_id, record_id)', '(company_id, id)', ['registration_status' => 'TERDAFTAR_CONTOH', 'effective' => '[2026-01-01,)']],
]);

it('gives every company-owned table of ORG and DOC its company key (DATABASE §1, §15)', function () {
    $keyed = collect(DB::select("select conrelid::regclass::text as table_name from pg_constraint
        where contype = 'u' and connamespace = 'public'::regnamespace
          and (select array_agg(attname order by attname) from pg_attribute where attrelid = conrelid and attnum = any (conkey)) = array['company_id', 'id']::name[]"))
        ->pluck('table_name')->sort()->values()->all();

    expect($keyed)->toContain(
        'company_bank_accounts', 'numbering_schemes', 'retired_numbers', 'company_tax_profiles', 'documents',
        'document_versions', 'file_objects', 'evidence_documents', 'bank_statement_lines',
    );
});
