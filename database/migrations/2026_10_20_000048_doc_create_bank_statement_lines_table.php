<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `bank_statement_lines` (AF with a void state): the bank-line identity every bank Cash-In
// cites (L-22; AX-18; C-19), on the company's own account and its own BANK_STATEMENT evidence (C-32;
// C-58). Its reference is the bank's transaction reference or, where the bank prints none, the running
// balance after the line — never a position — so one receipt in two overlapping statements has one
// identity (C-49). A line mistyped at entry is voided once, with reason and actor, through a column
// grant and the trigger, and the true line entered (C-59); its evidence guard then proves 0, so the void
// cannot commit while a Cash-In still cites it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('company_bank_account_id');
            $table->bigInteger('statement_evidence_id');
            $table->text('statement_evidence_type');
            Columns::businessDate($table);
            Columns::money($table, 'amount');
            $table->text('line_reference');
            $table->text('state')->default('ACTIVE');
            Columns::user($table, 'voided_by')->nullable();
            Columns::timestamp($table, 'voided_at')->nullable();
            $table->text('voided_reason')->nullable();
            Columns::commandIdentity($table, 'bank_statement_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            // An account's lines, voided ones included, joined from the account (§20) and checked by its
            // details' trigger (BR-SN-02).
            $table->index('company_bank_account_id');
        });

        TableRules::for('bank_statement_lines')
            ->unique('bank_statement_lines_c32_company_key', ['company_id', 'id'])
            ->foreign('bank_statement_lines_c32_bank_account_foreign', ['company_id', 'company_bank_account_id'], 'company_bank_accounts', ['company_id', 'id'])
            ->foreign('bank_statement_lines_c32_statement_foreign', ['company_id', 'statement_evidence_id'], 'evidence_documents', ['company_id', 'id'])
            ->foreign('bank_statement_lines_c58_statement_type_foreign', ['statement_evidence_id', 'statement_evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->check('bank_statement_lines_c58_statement_type', "statement_evidence_type = 'BANK_STATEMENT'")
            ->check('bank_statement_lines_amount_positive', 'amount > 0')
            ->codes('bank_statement_lines_state_code', 'state', ['ACTIVE', 'VOIDED'])
            ->check('bank_statement_lines_void_columns', "num_nonnulls(voided_by, voided_at, voided_reason) in (0, 3) and (state = 'VOIDED') = (voided_at is not null)")
            ->uniqueIndex('bank_statement_lines_c19_identity', 'company_bank_account_id, business_date, amount, line_reference', "state = 'ACTIVE'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'voided_by', 'voided_at', 'voided_reason']);

        DB::unprepared(<<<'SQL'
            create or replace function bank_statement_lines_c19_void_once() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'ACTIVE' or new.state <> 'VOIDED'
                    or (to_jsonb(new) - array['state', 'voided_by', 'voided_at', 'voided_reason'])
                        is distinct from (to_jsonb(old) - array['state', 'voided_by', 'voided_at', 'voided_reason']) then
                    raise exception 'A bank statement line changes only by its one void (DATABASE §4.10; C-19).'
                        using errcode = 'check_violation', constraint = 'bank_statement_lines_c19_void_once';
                end if;

                return new;
            end
            $$;

            create trigger bank_statement_lines_c19_void_once
                before update on bank_statement_lines
                for each row execute function bank_statement_lines_c19_void_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('bank_statement_lines');
        DB::unprepared('drop function if exists bank_statement_lines_c19_void_once()');
    }
};
