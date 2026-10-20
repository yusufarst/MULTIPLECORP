<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005), C-32's part on these tables: every company-owned table carries UNIQUE (company_id, id)
// — and `import_rows` (id, company_id) for the opening lots' provenance — so every reference to it is a
// composite foreign key that cannot cross companies (DATABASE §1, §15). The referencing tables arrive
// with TASK-006–TASK-009; here a scratch table, created by the migration role inside a transaction that
// is rolled back, references each key the way they will.

uses(RefreshDatabase::class);

it('lets a composite reference reach its own company\'s row and refuses another company\'s (C-32)', function (string $table, string $columns, string $key, array $row) {
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        if ($table === 'import_rows') {
            $row['import_batch_id'] = $owner->table('import_batches')->insertGetId(['import_kind' => 'CONTOH', 'source_file_id' => 2001, 'source_sha256' => hash('sha256', 'kunci'), 'cutoff_date' => '2026-09-30', 'company_scope' => '{1001}']);
        }

        $id = $owner->table($table)->insertGetId($row);
        $owner->statement("create table company_reference (company_id bigint not null, record_id bigint not null, foreign key {$columns} references {$table} {$key})");

        $owner->table('company_reference')->insert(['company_id' => 1001, 'record_id' => $id]);

        Refusal::assert(Refusal::FOREIGN_KEY, 'company_reference', fn () => $owner->table('company_reference')->insert(['company_id' => 1002, 'record_id' => $id]), DatabaseRole::MIGRATION_CONNECTION);
    } finally {
        $owner->rollBack();
    }
})->with([
    'correction cases' => ['correction_cases', '(company_id, record_id)', '(company_id, id)', ['company_id' => 1001, 'case_kind' => 'OTHER']],
    'legacy archive items' => ['legacy_archive_items', '(company_id, record_id)', '(company_id, id)', ['company_id' => 1001, 'archive_kind' => 'CONTOH', 'legacy_reference' => 'ARSIP-K']],
    'import rows' => ['import_rows', '(record_id, company_id)', '(id, company_id)', ['row_number' => 1, 'import_kind' => 'CONTOH', 'import_identity' => 'LEGACY-K', 'company_id' => 1001, 'validation_state' => 'VALID']],
]);
