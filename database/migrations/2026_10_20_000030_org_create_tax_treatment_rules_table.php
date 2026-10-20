<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `tax_treatment_rules` (CF): the D-1 NET model, configurable and evidenced, with no
// invented formula (§12; BR-FIN-17). An empty `company_id` is a rule for every company, which a
// company-specific rule overrides for the same context, tax type and date; the exclusion keys on
// `coalesce(company_id, 0)`, so global rules cannot overlap either (C-52). An empty range is refused.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_treatment_rules', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies')->nullable();
            $table->text('transaction_context');
            Columns::reference($table, 'tax_type_id', 'tax_types');
            $table->text('treatment');
            $table->boolean('evidence_required')->default(false);
            $table->rawColumn('effective', 'daterange');
            Columns::commandIdentity($table, 'tax_treatment_rules');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('tax_treatment_rules')
            ->codes('tax_treatment_rules_context_code', 'transaction_context', [
                'SALES_INVOICE', 'PURCHASE_LINE', 'PAYMENT_WITHHELD', 'PAYMENT_COLLECTED', 'TAX_REMITTANCE',
            ])
            ->codes('tax_treatment_rules_treatment_code', 'treatment', [
                'OUTPUT_EXCLUDED_FROM_REVENUE', 'INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST', 'SETTLEMENT_NO_EXPENSE',
                'SETTLEMENT_TO_EXPENSE', 'REMITTANCE_NOT_COST', 'REMITTANCE_TO_EXPENSE',
            ])
            ->check('tax_treatment_rules_effective_not_empty', 'not isempty(effective)')
            ->exclude('tax_treatment_rules_c52_no_overlap', 'coalesce(company_id, 0) with =, transaction_context with =, tax_type_id with =, effective with &&')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['treatment', 'evidence_required', 'effective', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('tax_treatment_rules');
    }
};
