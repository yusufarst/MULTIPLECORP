<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `deduction_settlements` (AF with a state) and §11.4: a fee or tax the client deducted, anchored
// to its payment and invoice of one company and client (L-28; C-14) and to its evidence of the type its class
// and direction take (C-58), never Cash-In, an application or revenue (BR-FIN-09/16). One ACTIVE settlement
// per payment, invoice, class and specific type (C-22). Its state follows ACTIVE → RESIDUAL, ACTIVE → CONTRAED
// and RESIDUAL → CONTRAED only, each once, CONTRAED terminal; a RESIDUAL carries its case, set in that same
// update when not already present — a column grant and the trigger (CM-22/24; C-60).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_settlements', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('client_organization_id');
            $table->bigInteger('invoice_id');
            $table->bigInteger('payment_id');
            $table->text('settlement_class');
            Columns::reference($table, 'tax_type_id', 'tax_types')->nullable();
            $table->text('fee_type')->nullable();
            $table->text('tax_direction')->nullable();
            $table->text('specific_type_key');
            Columns::reference($table, 'treatment_rule_id', 'tax_treatment_rules')->nullable();
            Columns::money($table, 'amount');
            $table->bigInteger('evidence_document_id');
            $table->text('evidence_type');
            Columns::businessDate($table);
            $table->text('state')->default('ACTIVE');
            $table->bigInteger('correction_case_id')->nullable();
            Columns::commandIdentity($table, 'deduction_settlements');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('invoice_id');
            $table->index('evidence_document_id');
        });

        TableRules::for('deduction_settlements')
            ->unique('deduction_settlements_c32_company_key', ['company_id', 'id'])
            ->foreign('deduction_settlements_c14_invoice_foreign', ['company_id', 'client_organization_id', 'invoice_id'], 'invoices', ['company_id', 'client_organization_id', 'id'])
            ->foreign('deduction_settlements_c14_payment_foreign', ['company_id', 'client_organization_id', 'payment_id'], 'payments', ['company_id', 'client_organization_id', 'id'])
            ->foreign('deduction_settlements_c32_evidence_foreign', ['company_id', 'evidence_document_id'], 'evidence_documents', ['company_id', 'id'])
            ->foreign('deduction_settlements_c58_evidence_type_foreign', ['evidence_document_id', 'evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->foreign('deduction_settlements_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('deduction_settlements_class_code', 'settlement_class', ['FEE', 'TAX'])
            ->codes('deduction_settlements_direction_code', 'tax_direction', ['WITHHELD', 'COLLECTED'], nullable: true)
            ->codes('deduction_settlements_state_code', 'state', ['ACTIVE', 'RESIDUAL', 'CONTRAED'])
            ->check('deduction_settlements_type_by_class', <<<'SQL'
                (settlement_class = 'TAX') = (tax_type_id is not null and tax_direction is not null and treatment_rule_id is not null)
                and (settlement_class = 'FEE') = (fee_type is not null)
                and (settlement_class = 'TAX' or num_nonnulls(tax_type_id, tax_direction, treatment_rule_id) = 0)
                SQL)
            ->check('deduction_settlements_c22_specific_type', "specific_type_key is not distinct from (case settlement_class when 'TAX' then tax_type_id::text || ':' || tax_direction else fee_type end)")
            ->check('deduction_settlements_c58_evidence_type', <<<'SQL'
                evidence_type = case
                    when settlement_class = 'FEE' then 'FEE_ADVICE'
                    when tax_direction = 'WITHHELD' then 'TAX_WITHHOLDING_PROOF'
                    else 'TAX_COLLECTION_PROOF'
                end
                SQL)
            ->check('deduction_settlements_amount_positive', 'amount > 0')
            ->check('deduction_settlements_c60_residual_case', "state <> 'RESIDUAL' or correction_case_id is not null")
            ->uniqueIndex('deduction_settlements_c22_one_active', 'payment_id, invoice_id, settlement_class, specific_type_key', "state = 'ACTIVE'")
            // §20 and QS-07: unresolved residuals.
            ->index('deduction_settlements_qs07_residual', 'company_id', "state = 'RESIDUAL'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'correction_case_id']);

        DB::unprepared(<<<'SQL'
            create or replace function deduction_settlements_c60_transition() returns trigger
            language plpgsql as $$
            begin
                if (to_jsonb(new) - array['state', 'correction_case_id']) is distinct from (to_jsonb(old) - array['state', 'correction_case_id'])
                    or (old.state, new.state) not in (('ACTIVE', 'RESIDUAL'), ('ACTIVE', 'CONTRAED'), ('RESIDUAL', 'CONTRAED'))
                    or (new.correction_case_id is distinct from old.correction_case_id and not (old.correction_case_id is null and new.state = 'RESIDUAL')) then
                    raise exception 'A settlement moves ACTIVE → RESIDUAL, ACTIVE → CONTRAED or RESIDUAL → CONTRAED, once (DATABASE §4.12, §11.4; C-60).'
                        using errcode = 'check_violation', constraint = 'deduction_settlements_c60_transition';
                end if;

                return new;
            end
            $$;

            create trigger deduction_settlements_c60_transition
                before update on deduction_settlements
                for each row execute function deduction_settlements_c60_transition();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('deduction_settlements');
        DB::unprepared('drop function if exists deduction_settlements_c60_transition()');
    }
};
