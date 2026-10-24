<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `receivable_dispute_holds` (SR) and §11.2: a dispute keeps the receivable active and aging and
// never changes an amount (BR-FIN-08); at most one uncleared hold per invoice (C-42). A hold is cleared once,
// with reason and actor, through a column grant and the trigger; its lapse at zero outstanding is derived
// (L-17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_dispute_holds', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'invoice_id', 'invoices');
            $table->text('reason');
            Columns::user($table, 'set_by');
            Columns::timestamp($table, 'set_at')->useCurrent();
            Columns::user($table, 'cleared_by')->nullable();
            Columns::timestamp($table, 'cleared_at')->nullable();
            $table->text('clear_reason')->nullable();
            Columns::commandIdentity($table, 'receivable_dispute_holds');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('receivable_dispute_holds')
            ->check('receivable_dispute_holds_clear_columns', 'num_nonnulls(cleared_by, cleared_at, clear_reason) in (0, 3)')
            ->uniqueIndex('receivable_dispute_holds_c42_one_open', 'invoice_id', 'cleared_at is null')
            ->index('receivable_dispute_holds_invoice', 'invoice_id')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['cleared_by', 'cleared_at', 'clear_reason']);

        DB::unprepared(<<<'SQL'
            create or replace function receivable_dispute_holds_cleared_once() returns trigger
            language plpgsql as $$
            begin
                if old.cleared_at is not null
                    or (to_jsonb(new) - array['cleared_by', 'cleared_at', 'clear_reason']) is distinct from (to_jsonb(old) - array['cleared_by', 'cleared_at', 'clear_reason']) then
                    raise exception 'A dispute hold changes only by its one clearing (DATABASE §4.12; BR-FIN-08).'
                        using errcode = 'check_violation', constraint = 'receivable_dispute_holds_cleared_once';
                end if;

                return new;
            end
            $$;

            create trigger receivable_dispute_holds_cleared_once
                before update on receivable_dispute_holds
                for each row execute function receivable_dispute_holds_cleared_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('receivable_dispute_holds');
        DB::unprepared('drop function if exists receivable_dispute_holds_cleared_once()');
    }
};
