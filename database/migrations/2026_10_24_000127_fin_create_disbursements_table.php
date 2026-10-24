<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `disbursements` (AF) and §11.5: the only Cash-Out (BR-FIN-11; L-43), from its company's own
// account (C-32), each kind citing its own reference — the purchase, the expense, or the TAX_PAYMENT_PROOF and
// the treatment rule of a remittance (C-58; §12); purchase creation never creates one (FS-04). A REFUND is of
// one client, backed by the REFUND applications that target it and committed fully backed by its money-fact
// guard (AX-23; C-44); an INTERCOMPANY_TRANSFER_OUT backs the Cash-In that cites it (BR-FIN-07 b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            Columns::reference($table, 'client_organization_id', 'client_organizations')->nullable();
            Columns::businessDate($table);
            Columns::money($table, 'amount');
            $table->text('kind');
            $table->bigInteger('company_bank_account_id');
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('expense_id')->nullable();
            $table->bigInteger('tax_evidence_id')->nullable();
            $table->text('tax_evidence_type')->nullable();
            Columns::reference($table, 'treatment_rule_id', 'tax_treatment_rules')->nullable();
            Columns::commandIdentity($table, 'disbursements');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('company_bank_account_id');
            $table->index('purchase_id');
            $table->index('expense_id');
            $table->index('tax_evidence_id');
        });

        TableRules::for('disbursements')
            ->unique('disbursements_c32_company_key', ['company_id', 'id'])
            ->unique('disbursements_c14_client_key', ['company_id', 'client_organization_id', 'id'])
            // The typed key a transferred Cash-In cites its transfer-out through (BR-FIN-07 b).
            ->unique('disbursements_kind_key', ['id', 'kind'])
            ->foreign('disbursements_c32_bank_account_foreign', ['company_id', 'company_bank_account_id'], 'company_bank_accounts', ['company_id', 'id'])
            ->foreign('disbursements_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->foreign('disbursements_c32_expense_foreign', ['company_id', 'expense_id'], 'expenses', ['company_id', 'id'])
            ->foreign('disbursements_c32_tax_evidence_foreign', ['company_id', 'tax_evidence_id'], 'evidence_documents', ['company_id', 'id'])
            ->foreign('disbursements_c58_tax_evidence_type_foreign', ['tax_evidence_id', 'tax_evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->codes('disbursements_kind_code', 'kind', ['PURCHASE_PAYMENT', 'EXPENSE_PAYMENT', 'REFUND', 'INTERCOMPANY_TRANSFER_OUT', 'TAX_REMITTANCE'])
            ->check('disbursements_amount_positive', 'amount > 0')
            ->check('disbursements_c58_tax_evidence_type', "num_nonnulls(tax_evidence_id, tax_evidence_type) in (0, 2) and (tax_evidence_type is null or tax_evidence_type = 'TAX_PAYMENT_PROOF')")
            ->check('disbursements_reference_by_kind', <<<'SQL'
                (kind = 'REFUND') = (client_organization_id is not null)
                and (kind = 'PURCHASE_PAYMENT') = (purchase_id is not null)
                and (kind = 'EXPENSE_PAYMENT') = (expense_id is not null)
                and (kind = 'TAX_REMITTANCE') = (tax_evidence_id is not null and treatment_rule_id is not null)
                and (kind = 'TAX_REMITTANCE' or num_nonnulls(tax_evidence_id, treatment_rule_id) = 0)
                SQL)
            // §20 and IX-11: company and date reporting, keyset by business date.
            ->index('disbursements_ix11_business_date', 'company_id, business_date, id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('disbursements');
    }
};
