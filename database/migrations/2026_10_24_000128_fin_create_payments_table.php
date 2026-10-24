<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `payments` (AF) and §11.3: an immutable client Cash-In fact (BR-FIN-06; AX-18; L-29), into its
// company's own account unless in cash (C-32), citing its company's bank statement line. A payment recorded
// before its line is flagged until cited; the citation is set once and afterwards changes only by a reasoned
// correction, never back to none — a column grant and the trigger (C-19; C-59). Money for a written-off
// invoice names it (WF-FIN-05); a re-recorded payment names the contra'd one it replaces (CM-19); client
// money moved from another company cites that company's transfer-out (BR-FIN-07 b; §15).
return new class extends Migration
{
    private const CITATION = ['bank_statement_line_id', 'bank_citation_corrected_at', 'bank_citation_corrected_by', 'bank_citation_corrected_reason'];

    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            Columns::reference($table, 'client_organization_id', 'client_organizations');
            Columns::businessDate($table);
            Columns::money($table, 'amount');
            $table->text('method');
            $table->bigInteger('company_bank_account_id')->nullable();
            $table->bigInteger('bank_statement_line_id')->nullable();
            Columns::timestamp($table, 'bank_citation_corrected_at')->nullable();
            Columns::user($table, 'bank_citation_corrected_by')->nullable();
            $table->text('bank_citation_corrected_reason')->nullable();
            $table->text('payer_intermediary')->nullable();
            $table->text('reference')->nullable();
            $table->text('unevidenced_reason')->nullable();
            $table->bigInteger('recovery_for_invoice_id')->nullable();
            $table->bigInteger('rerecords_payment_id')->nullable()->unique();
            $table->bigInteger('transfer_from_disbursement_id')->nullable();
            $table->text('transfer_from_disbursement_kind')->nullable();
            Columns::commandIdentity($table, 'payments');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('bank_statement_line_id');
            $table->index('reference');
            $table->index('recovery_for_invoice_id');
            $table->index('transfer_from_disbursement_id');
        });

        TableRules::for('payments')
            ->unique('payments_c32_company_key', ['company_id', 'id'])
            ->unique('payments_c14_client_key', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payments_c32_bank_account_foreign', ['company_id', 'company_bank_account_id'], 'company_bank_accounts', ['company_id', 'id'])
            ->foreign('payments_c19_statement_line_foreign', ['company_id', 'bank_statement_line_id'], 'bank_statement_lines', ['company_id', 'id'])
            ->foreign('payments_c14_recovery_foreign', ['company_id', 'client_organization_id', 'recovery_for_invoice_id'], 'invoices', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payments_c59_rerecords_foreign', ['company_id', 'rerecords_payment_id'], 'payments', ['company_id', 'id'])
            ->foreign('payments_transfer_foreign', ['transfer_from_disbursement_id', 'transfer_from_disbursement_kind'], 'disbursements', ['id', 'kind'])
            ->codes('payments_method_code', 'method', ['TRANSFER', 'CASH', 'VIRTUAL_ACCOUNT', 'OTHER'])
            ->check('payments_amount_positive', 'amount > 0')
            ->check('payments_c32_account_unless_cash', "method = 'CASH' or company_bank_account_id is not null")
            ->check('payments_c19_citation_correction', 'num_nonnulls(bank_citation_corrected_at, bank_citation_corrected_by, bank_citation_corrected_reason) in (0, 3) and (bank_citation_corrected_at is null or bank_statement_line_id is not null)')
            ->check('payments_transfer_typed', "num_nonnulls(transfer_from_disbursement_id, transfer_from_disbursement_kind) in (0, 2) and (transfer_from_disbursement_kind is null or transfer_from_disbursement_kind = 'INTERCOMPANY_TRANSFER_OUT')")
            // IX-01: the duplicate query of BD-02.
            ->index('payments_ix01_duplicate', 'company_bank_account_id, amount, business_date')
            // §20 and IX-11: company and date reporting, keyset by business date.
            ->index('payments_ix11_business_date', 'company_id, business_date, id')
            // §20, IX-14 and QS-12: bank payments that cite no statement line, payments without proof.
            ->index('payments_ix14_uncited', 'company_id', 'company_bank_account_id is not null and bank_statement_line_id is null')
            ->index('payments_ix14_unevidenced', 'company_id', 'unevidenced_reason is not null')
            // §20 and QS-14: money received for a written-off invoice.
            ->index('payments_qs14_recovery', 'company_id', 'recovery_for_invoice_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(self::CITATION);

        DB::unprepared(<<<'SQL'
            create or replace function payments_c19_citation_once_then_corrected() returns trigger
            language plpgsql as $$
            declare
                citation text[] := array['bank_statement_line_id', 'bank_citation_corrected_at', 'bank_citation_corrected_by', 'bank_citation_corrected_reason'];
            begin
                if (to_jsonb(new) - citation) is distinct from (to_jsonb(old) - citation)
                    or new.bank_statement_line_id is null
                    or not (
                        -- Set once when the line arrives,
                        (old.bank_statement_line_id is null and new.bank_citation_corrected_at is not distinct from old.bank_citation_corrected_at)
                        -- or corrected, with reason, to another line.
                        or (old.bank_statement_line_id is not null and new.bank_statement_line_id <> old.bank_statement_line_id
                            and new.bank_citation_corrected_at is not null and new.bank_citation_corrected_at is distinct from old.bank_citation_corrected_at)
                    ) then
                    raise exception 'A payment changes only by its bank-line citation, set once and then corrected with reason (DATABASE §4.12; C-19, C-59).'
                        using errcode = 'check_violation', constraint = 'payments_c19_citation_once_then_corrected';
                end if;

                return new;
            end
            $$;

            create trigger payments_c19_citation_once_then_corrected
                before update on payments
                for each row execute function payments_c19_citation_once_then_corrected();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('payments');
        DB::unprepared('drop function if exists payments_c19_citation_once_then_corrected()');
    }
};
