<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `command_log` (OP): the idempotency boundary of §26 and CONCURRENCY_IDEMPOTENCY
// CI-01–CI-09, never business truth. Its actor and company are plain values without foreign keys, so the
// command's first statement takes no lock out of order (CI-03). Its expired rows are purged (JB-08).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_log', function (Blueprint $table) {
            $table->uuid('command_id')->primary();
            $table->text('command_type');
            $table->bigInteger('actor_user_id')->nullable();
            $table->bigInteger('company_id')->nullable();
            // `v1:` and the SHA-256 of the canonical payload; the payload itself is never stored (CI-02).
            $table->text('payload_fingerprint');
            $table->text('state');
            $table->text('result_entity_type')->nullable();
            $table->bigInteger('result_entity_id')->nullable();
            $table->text('outcome_code')->nullable();
            Columns::timestamp($table, 'completed_at')->nullable();
            Columns::timestamp($table, 'expires_at')->nullable();
            Columns::recordedAt($table);
            // IX-03: the purge JB-08.
            $table->index('expires_at', 'command_log_ix03_expires_at');
        });

        TableRules::for('command_log')
            ->codes('command_log_state_code', 'state', ['IN_PROGRESS', 'COMMITTED', 'REJECTED', 'CONFLICT'])
            // IN_PROGRESS exists only inside its command's transaction; the terminal state is written with
            // its completion and expiry (CI-03, CI-07), so every finished row is purged in time.
            ->check('command_log_terminal_expiry', <<<'SQL'
                (state = 'IN_PROGRESS' and completed_at is null and expires_at is null)
                or (state <> 'IN_PROGRESS' and completed_at is not null and expires_at is not null)
                SQL)
            ->grant('select, insert, delete')
            ->grantUpdate(['state', 'result_entity_type', 'result_entity_id', 'outcome_code', 'completed_at', 'expires_at']);

        // DATABASE §19 (TECH-021): the one table besides the drafts whose rows the runtime deletes, and then
        // only a row whose expiry has passed.
        DB::unprepared(<<<'SQL'
            create or replace function command_log_delete_expired_only() returns trigger
            language plpgsql as $$
            begin
                if old.expires_at is null or old.expires_at > now() then
                    raise exception 'Only an expired command log row is deleted (DATABASE §19; JB-08).'
                        using errcode = 'check_violation', constraint = 'command_log_delete_expired_only';
                end if;

                return old;
            end
            $$;

            create trigger command_log_delete_expired_only
                before delete on command_log
                for each row execute function command_log_delete_expired_only();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('command_log');
        DB::unprepared('drop function if exists command_log_delete_expired_only()');
    }
};
