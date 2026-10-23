<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_line_adjustments` (AF): a signed change of a purchase line's or a charge's value
// after its first effect — an exclusive arc (D-DB-10). Only the three *_CORRECTION kinds drive the cost
// cascade (CM-17; AX-34), a charge's correction on the charge; the RETURN_* kinds record a supplier
// return's purchase value (WF-PUR-03) and never touch lot cost again. The line, the charge and the case are
// the purchase's company's (§13; C-32).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_line_adjustments', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('purchase_line_id')->nullable();
            $table->bigInteger('purchase_charge_id')->nullable();
            $table->bigInteger('company_id');
            $table->text('kind');
            Columns::money($table, 'amount_delta');
            $table->text('reason');
            $table->bigInteger('correction_case_id')->nullable();
            Columns::commandIdentity($table, 'purchase_line_adjustments');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('purchase_line_adjustments')
            ->unique('purchase_line_adjustments_c32_company_key', ['company_id', 'id'])
            ->foreign('purchase_line_adjustments_c32_line_foreign', ['company_id', 'purchase_line_id'], 'purchase_lines', ['company_id', 'id'])
            ->foreign('purchase_line_adjustments_c32_charge_foreign', ['company_id', 'purchase_charge_id'], 'purchase_charges', ['company_id', 'id'])
            ->foreign('purchase_line_adjustments_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('purchase_line_adjustments_kind_code', 'kind', ['PRICE_CORRECTION', 'TAX_CORRECTION', 'CHARGE_CORRECTION', 'RETURN_REFUNDED', 'RETURN_REPLACED'])
            ->check('purchase_line_adjustments_one_target', 'num_nonnulls(purchase_line_id, purchase_charge_id) = 1')
            ->check('purchase_line_adjustments_target_by_kind', "(kind = 'CHARGE_CORRECTION') = (purchase_charge_id is not null)")
            ->index('purchase_line_adjustments_line', 'purchase_line_id', 'purchase_line_id is not null')
            ->index('purchase_line_adjustments_charge', 'purchase_charge_id', 'purchase_charge_id is not null')
            ->index('purchase_line_adjustments_correction_case', 'correction_case_id', 'correction_case_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('purchase_line_adjustments');
    }
};
