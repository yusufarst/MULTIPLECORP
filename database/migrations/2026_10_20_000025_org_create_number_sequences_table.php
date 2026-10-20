<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `number_sequences` (SR, the authoritative allocator; D-DB-12; C-10): one counter per
// company, sequence type and period, keyed by them, which the issuing transaction increments and whose
// pre-increment value it uses, so numbers are unique, monotonic, append-only and never reused — never
// the highest issued number plus one, never a PostgreSQL sequence (CONCURRENCY_IDEMPOTENCY NM-02,
// NM-10). A counter is never lowered or rebuilt downward, its seeds cannot be derived from issued rows
// (§3.1): the trigger refuses a lower value. A seed states its first number — from the migration, keyed
// to its import row and that row's company, or from the Owner's numbering command —; a counter created
// on first use carries none. A seed that meets an existing counter raises it and moves its seed value
// with it (NM-03), so the runtime may update `next_value` and the seed columns, never the key; a seed
// value, once set, is never lowered or cleared either. Which periods may have a counter is the
// allocator's rule (NM-03; TASK-021).
return new class extends Migration
{
    private const SEQUENCE_TYPES = [
        'DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-09', 'DOC-10',
        'DOC-11', 'DOC-12', 'DOC-13', 'DOC-14', 'PROJECT', 'PURCHASE',
    ];

    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            Columns::reference($table, 'company_id', 'companies');
            $table->text('sequence_type');
            // `YYYY` or `YYYY-MM`, from the business date and the scheme's granularity (§10, §17).
            $table->text('period_key');
            $table->bigInteger('next_value')->default(1);
            $table->bigInteger('seed_value')->nullable();
            $table->bigInteger('seeded_by_import_row_id')->nullable();
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->primary(['company_id', 'sequence_type', 'period_key']);
        });

        TableRules::for('number_sequences')
            ->codes('number_sequences_sequence_type_code', 'sequence_type', self::SEQUENCE_TYPES)
            ->check('number_sequences_period_key_format', "period_key ~ '^[0-9]{4}(-(0[1-9]|1[0-2]))?$'")
            ->check('number_sequences_next_value_positive', 'next_value >= 1')
            ->check('number_sequences_seed_states_first_number', 'seeded_by_import_row_id is null or seed_value is not null')
            ->foreign('number_sequences_c32_seed_import_row_foreign', ['seeded_by_import_row_id', 'company_id'], 'import_rows', ['id', 'company_id'])
            ->grant('select, insert')
            ->grantUpdate(['next_value', 'seed_value', 'seeded_by_import_row_id']);

        DB::unprepared(<<<'SQL'
            create or replace function number_sequences_c10_never_lowered() returns trigger
            language plpgsql as $$
            begin
                if new.next_value < old.next_value
                    or (old.seed_value is not null and (new.seed_value is null or new.seed_value < old.seed_value)) then
                    raise exception 'A number counter and its seed value are never lowered (DATABASE §3.1; C-10; NM-03).'
                        using errcode = 'check_violation', constraint = 'number_sequences_c10_never_lowered';
                end if;

                return new;
            end
            $$;

            create trigger number_sequences_c10_never_lowered
                before update on number_sequences
                for each row execute function number_sequences_c10_never_lowered();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('number_sequences');
        DB::unprepared('drop function if exists number_sequences_c10_never_lowered()');
    }
};
