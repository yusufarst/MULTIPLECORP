<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_charges` (AF): a charge on a purchase (BR-PUR-05; SF-CHARGE), as recorded —
// corrections are CHARGE_CORRECTION adjustments. Every charge names its source bill, a SUPPLIER_BILL of the
// purchase's company (L-43; C-32, C-58), whose cap `evidence_balances` holds (C-20). An attributable charge
// states how it is spread; a non-attributable one is expensed and states none.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_charges', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('purchase_id');
            $table->bigInteger('company_id');
            $table->bigInteger('source_bill_evidence_id');
            $table->text('source_bill_evidence_type');
            $table->text('classification');
            Columns::money($table, 'amount');
            $table->text('allocation_basis')->nullable();
            Columns::commandIdentity($table, 'purchase_charges');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('purchase_id');
            $table->index('source_bill_evidence_id');
        });

        TableRules::for('purchase_charges')
            ->unique('purchase_charges_c32_company_key', ['company_id', 'id'])
            ->foreign('purchase_charges_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->foreign('purchase_charges_c58_source_bill_type_foreign', ['source_bill_evidence_id', 'source_bill_evidence_type'], 'evidence_documents', ['id', 'evidence_type'])
            ->foreign('purchase_charges_c32_source_bill_foreign', ['company_id', 'source_bill_evidence_id'], 'evidence_documents', ['company_id', 'id'])
            ->check('purchase_charges_c58_source_bill', "source_bill_evidence_type = 'SUPPLIER_BILL'")
            ->codes('purchase_charges_classification_code', 'classification', ['ATTRIBUTABLE', 'NON_ATTRIBUTABLE'])
            ->codes('purchase_charges_allocation_basis_code', 'allocation_basis', ['LINE_VALUE', 'QUANTITY', 'WEIGHT', 'EXPLICIT'], nullable: true)
            ->check('purchase_charges_amount_positive', 'amount > 0')
            ->check('purchase_charges_basis_when_attributable', "(classification = 'ATTRIBUTABLE') = (allocation_basis is not null)")
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('purchase_charges');
    }
};
