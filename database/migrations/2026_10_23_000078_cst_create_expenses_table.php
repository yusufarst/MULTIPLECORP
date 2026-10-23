<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `expenses` (AF): a company's or a project's expense — manual, a fee deduction created only
// by its settlement's posting (BR-FIN-09; C-23), a non-attributable or fallback purchase charge (BR-PUR-05),
// or a configured tax treatment (BR-FIN-16). A fee's evidence is a FEE_ADVICE and a tax's a withholding or
// collection proof, of the expense's company (C-32, C-58); a bill is cited once, by its charge or by the
// expense directly, so one bill caps its charges and expenses together (L-43; C-20). The deduction
// settlement is TASK-009's, which keys it (DIR-088's rule).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('project_id')->nullable();
            $table->text('kind');
            $table->text('category');
            Columns::money($table, 'amount');
            Columns::businessDate($table);
            $table->bigInteger('deduction_settlement_id')->nullable();
            $table->bigInteger('evidence_document_id')->nullable();
            $table->text('evidence_type')->nullable();
            $table->bigInteger('purchase_charge_id')->nullable();
            $table->bigInteger('purchase_line_id')->nullable();
            $table->bigInteger('source_bill_evidence_id')->nullable();
            $table->text('source_bill_evidence_type')->nullable();
            Columns::commandIdentity($table, 'expenses');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('expenses')
            ->unique('expenses_c32_company_key', ['company_id', 'id'])
            ->foreign('expenses_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->foreign('expenses_c58_evidence_type_foreign', ['evidence_document_id', 'evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->foreign('expenses_c32_evidence_foreign', ['company_id', 'evidence_document_id'], 'evidence_documents', ['company_id', 'id'])
            ->foreign('expenses_c32_charge_foreign', ['company_id', 'purchase_charge_id'], 'purchase_charges', ['company_id', 'id'])
            ->foreign('expenses_c32_purchase_line_foreign', ['company_id', 'purchase_line_id'], 'purchase_lines', ['company_id', 'id'])
            ->foreign('expenses_c58_source_bill_type_foreign', ['source_bill_evidence_id', 'source_bill_evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->foreign('expenses_c32_source_bill_foreign', ['company_id', 'source_bill_evidence_id'], 'evidence_documents', ['company_id', 'id'])
            ->codes('expenses_kind_code', 'kind', ['MANUAL', 'FEE_DEDUCTION', 'PURCHASE_CHARGE', 'TAX_TREATMENT'])
            ->check('expenses_amount_positive', 'amount > 0')
            // The evidence column holds a fee advice or a tax proof (§4.8); a bill is cited as the source bill, other proof by an evidence link.
            ->check('expenses_evidence_fee_or_tax', "evidence_document_id is null or kind in ('FEE_DEDUCTION', 'TAX_TREATMENT')")
            ->check('expenses_typed_pairs', 'num_nonnulls(evidence_document_id, evidence_type) in (0, 2) and num_nonnulls(source_bill_evidence_id, source_bill_evidence_type) in (0, 2)')
            ->check('expenses_c58_evidence_by_kind', <<<'SQL'
                (kind not in ('FEE_DEDUCTION', 'TAX_TREATMENT') or evidence_document_id is not null)
                and (kind <> 'FEE_DEDUCTION' or evidence_type = 'FEE_ADVICE')
                and (kind <> 'TAX_TREATMENT' or evidence_type in ('TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF'))
                SQL)
            ->check('expenses_c23_fee_from_settlement', "kind <> 'FEE_DEDUCTION' or deduction_settlement_id is not null")
            ->check('expenses_charge_when_purchase_charge', "kind <> 'PURCHASE_CHARGE' or purchase_charge_id is not null")
            ->check('expenses_fallback_line', "purchase_line_id is null or kind = 'PURCHASE_CHARGE'")
            ->check('expenses_c58_source_bill', "source_bill_evidence_type is null or source_bill_evidence_type = 'SUPPLIER_BILL'")
            ->check('expenses_c20_bill_cited_once', 'purchase_charge_id is null or source_bill_evidence_id is null')
            // IX-11 and §20: a company's expenses by business date (CALC-09, CALC-11; PF-13).
            ->index('expenses_ix11_company_business_date', 'company_id, business_date, id')
            ->index('expenses_project', 'project_id', 'project_id is not null')
            ->index('expenses_charge', 'purchase_charge_id', 'purchase_charge_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('expenses');
    }
};
