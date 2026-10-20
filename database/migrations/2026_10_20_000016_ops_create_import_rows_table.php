<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `import_rows` (SR, frozen once COMMITTED) and §24 (DB24-02, DB24-03): each source row
// with its stable legacy identity, validation result and created record. The committed identity is
// unique per import kind and every created fact carries a UNIQUE `import_row_id`, so running the
// opening import again creates no second lot, receivable, credit or project for one legacy row
// (AX-28; BD-11; C-47). `company_id` references `companies`, whose foreign key TASK-006 adds with the
// table (DIR-088).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            Columns::id($table);
            $table->foreignId('import_batch_id');
            $table->integer('row_number');
            $table->text('import_kind');
            $table->text('import_identity');
            // Typed, for every row that creates a company-owned fact or an opening lot.
            $table->bigInteger('company_id')->nullable();
            $table->jsonb('normalized_payload')->nullable();
            $table->text('validation_state');
            $table->jsonb('errors')->nullable();
            $table->text('result_entity_type')->nullable();
            $table->bigInteger('result_entity_id')->nullable();
            Columns::recordedAt($table);
            Columns::timestamp($table, 'updated_at')->nullable();
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->restrictOnDelete();
        });

        TableRules::for('import_rows')
            ->unique('import_rows_row_number', ['import_batch_id', 'row_number'])
            // The key opening lots carry their provenance and source company in (§4.7; C-08).
            ->unique('import_rows_company_key', ['id', 'company_id'])
            ->codes('import_rows_validation_state_code', 'validation_state', ['VALID', 'INVALID', 'DUPLICATE', 'SKIPPED', 'COMMITTED'])
            ->uniqueIndex('import_rows_c47_committed_identity', 'import_kind, import_identity', "validation_state = 'COMMITTED'")
            // IX-14: the duplicate rows of a batch.
            ->index('import_rows_ix14_duplicate', 'import_batch_id, row_number', "validation_state = 'DUPLICATE'")
            ->grant('select, insert')
            ->grantUpdate(['validation_state', 'errors', 'result_entity_type', 'result_entity_id', 'updated_at']);

        // §28: a committed import row never changes.
        DB::unprepared(<<<'SQL'
            create or replace function import_rows_committed_frozen() returns trigger
            language plpgsql as $$
            begin
                if old.validation_state = 'COMMITTED' then
                    raise exception 'A committed import row never changes (DATABASE §28).'
                        using errcode = 'check_violation', constraint = 'import_rows_committed_frozen';
                end if;

                return new;
            end
            $$;

            create trigger import_rows_committed_frozen
                before update on import_rows
                for each row execute function import_rows_committed_frozen();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('import_rows');
        DB::unprepared('drop function if exists import_rows_committed_frozen()');
    }
};
