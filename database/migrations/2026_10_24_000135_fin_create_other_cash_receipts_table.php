<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `other_cash_receipts` (AF) and §11.5: non-client Cash-In into its company's own account (C-32),
// income or not by its category — received cashback is income (BR-FIN-10), supplier refunds and inter-company
// transfers in are not (L-08). An INTERCOMPANY_TRANSFER_IN cites exactly the other company's transfer-out
// (BR-FIN-07 b; §15); the bank-line citation is set once and corrected only with reason, as on payments
// (C-19; C-59).
return new class extends Migration
{
    private const CITATION = ['bank_statement_line_id', 'bank_citation_corrected_at', 'bank_citation_corrected_by', 'bank_citation_corrected_reason'];

    public function up(): void
    {
        Schema::create('other_cash_receipts', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            Columns::businessDate($table);
            Columns::money($table, 'amount');
            $table->text('category');
            $table->text('income_class');
            $table->bigInteger('company_bank_account_id');
            $table->bigInteger('bank_statement_line_id')->nullable();
            Columns::timestamp($table, 'bank_citation_corrected_at')->nullable();
            Columns::user($table, 'bank_citation_corrected_by')->nullable();
            $table->text('bank_citation_corrected_reason')->nullable();
            $table->bigInteger('transfer_from_disbursement_id')->nullable();
            $table->text('transfer_from_disbursement_kind')->nullable();
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            Columns::commandIdentity($table, 'other_cash_receipts');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('company_bank_account_id');
            $table->index('bank_statement_line_id');
            $table->index('transfer_from_disbursement_id');
            $table->index('purchase_id');
        });

        TableRules::for('other_cash_receipts')
            ->unique('other_cash_receipts_c32_company_key', ['company_id', 'id'])
            ->foreign('other_cash_receipts_c32_bank_account_foreign', ['company_id', 'company_bank_account_id'], 'company_bank_accounts', ['company_id', 'id'])
            ->foreign('other_cash_receipts_c19_statement_line_foreign', ['company_id', 'bank_statement_line_id'], 'bank_statement_lines', ['company_id', 'id'])
            ->foreign('other_cash_receipts_transfer_foreign', ['transfer_from_disbursement_id', 'transfer_from_disbursement_kind'], 'disbursements', ['id', 'kind'])
            ->foreign('other_cash_receipts_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->foreign('other_cash_receipts_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('other_cash_receipts_category_code', 'category', ['SUPPLIER_REFUND', 'CASHBACK_INCOME', 'INTERCOMPANY_TRANSFER_IN', 'OTHER_INCOME', 'OTHER_NON_INCOME'])
            ->codes('other_cash_receipts_income_class_code', 'income_class', ['INCOME', 'NON_INCOME'])
            ->check('other_cash_receipts_amount_positive', 'amount > 0')
            ->check('other_cash_receipts_income_by_category', "(income_class = 'INCOME') = (category in ('CASHBACK_INCOME', 'OTHER_INCOME'))")
            ->check('other_cash_receipts_transfer_by_category', <<<'SQL'
                num_nonnulls(transfer_from_disbursement_id, transfer_from_disbursement_kind) in (0, 2)
                and (category = 'INTERCOMPANY_TRANSFER_IN') = (transfer_from_disbursement_id is not null)
                and (transfer_from_disbursement_kind is null or transfer_from_disbursement_kind = 'INTERCOMPANY_TRANSFER_OUT')
                SQL)
            ->check('other_cash_receipts_c19_citation_correction', 'num_nonnulls(bank_citation_corrected_at, bank_citation_corrected_by, bank_citation_corrected_reason) in (0, 3) and (bank_citation_corrected_at is null or bank_statement_line_id is not null)')
            // §20 and IX-11: company and date reporting, keyset by business date.
            ->index('other_cash_receipts_ix11_business_date', 'company_id, business_date, id')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(self::CITATION);

        DB::unprepared(<<<'SQL'
            create or replace function other_cash_receipts_c19_citation_once_then_corrected() returns trigger
            language plpgsql as $$
            declare
                citation text[] := array['bank_statement_line_id', 'bank_citation_corrected_at', 'bank_citation_corrected_by', 'bank_citation_corrected_reason'];
            begin
                if (to_jsonb(new) - citation) is distinct from (to_jsonb(old) - citation)
                    or new.bank_statement_line_id is null
                    or not (
                        (old.bank_statement_line_id is null and new.bank_citation_corrected_at is not distinct from old.bank_citation_corrected_at)
                        or (old.bank_statement_line_id is not null and new.bank_statement_line_id <> old.bank_statement_line_id
                            and new.bank_citation_corrected_at is not null and new.bank_citation_corrected_at is distinct from old.bank_citation_corrected_at)
                    ) then
                    raise exception 'A Cash-In changes only by its bank-line citation, set once and then corrected with reason (DATABASE §4.12; C-19, C-59).'
                        using errcode = 'check_violation', constraint = 'other_cash_receipts_c19_citation_once_then_corrected';
                end if;

                return new;
            end
            $$;

            create trigger other_cash_receipts_c19_citation_once_then_corrected
                before update on other_cash_receipts
                for each row execute function other_cash_receipts_c19_citation_once_then_corrected();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('other_cash_receipts');
        DB::unprepared('drop function if exists other_cash_receipts_c19_citation_once_then_corrected()');
    }
};
