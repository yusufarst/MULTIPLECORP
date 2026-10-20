<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `import_batches` (SR) and §24 (DB24-01): one opening-data source file per import kind,
// prepared, dry-run, signed off by the Admin and the Owner and committed by the Owner (WF-MIG-01; L-33).
// `source_file_id` references `file_objects`, which TASK-006 creates; the import kinds are TASK-057's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('import_kind');
            $table->bigInteger('source_file_id');
            $table->text('source_sha256');
            Columns::businessDate($table, 'cutoff_date');
            // The companies the batch covers: a set the commit reads, so an array, never jsonb (§17).
            $table->rawColumn('company_scope', 'bigint[]');
            $table->text('state')->default('PREPARED');
            $table->jsonb('control_totals')->nullable();
            $table->jsonb('dry_run_report')->nullable();
            Columns::user($table, 'prepared_by')->nullable();
            Columns::user($table, 'admin_signoff_by')->nullable();
            Columns::timestamp($table, 'admin_signoff_at')->nullable();
            Columns::user($table, 'owner_signoff_by')->nullable();
            Columns::timestamp($table, 'owner_signoff_at')->nullable();
            // The Owner's commit request, recorded on the SIGNED_OFF batch: the marker that its rows are
            // owed (CONCURRENCY_IDEMPOTENCY JB-07, NX-13).
            Columns::user($table, 'committed_by')->nullable();
            Columns::timestamp($table, 'committed_at')->nullable();
            Columns::commandIdentity($table, 'import_batches');
            Columns::recordedAt($table);
            Columns::editable($table);
        });

        TableRules::for('import_batches')
            ->unique('import_batches_c47_source', ['import_kind', 'source_sha256'])
            // A content hash: 64 lowercase hexadecimal characters of SHA-256 (§17).
            ->check('import_batches_source_sha256_format', "source_sha256 ~ '^[0-9a-f]{64}\$'")
            ->codes('import_batches_state_code', 'state', ['PREPARED', 'DRY_RUN_PASSED', 'SIGNED_OFF', 'COMMITTED', 'ABORTED'])
            // IX-15: the sweep JB-09 re-dispatches a committing batch whose chunk was lost.
            ->index('import_batches_ix15_commit_requested', 'id', "state = 'SIGNED_OFF' and committed_at is not null")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'state', 'control_totals', 'dry_run_report',
                'admin_signoff_by', 'admin_signoff_at', 'owner_signoff_by', 'owner_signoff_at',
                'committed_by', 'committed_at', 'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('import_batches');
    }
};
