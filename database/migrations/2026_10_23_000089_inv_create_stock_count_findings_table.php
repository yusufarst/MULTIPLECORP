<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_count_findings` (SR) and §5.6: a count's surplus in one of its scopes, at its
// product's scale (§1), which only the Owner attributes (AX-07; DIR-024 D-5): one OPEN finding per scope
// and product (C-34). The surplus lot cites its finding through (`id`, `attributed_company_id`), never the
// reverse.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_findings', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('stock_count_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('location_id');
            $table->text('condition');
            Columns::quantity($table, 'surplus_qty_base');
            $table->text('state')->default('OPEN');
            Columns::reference($table, 'attributed_company_id', 'companies')->nullable();
            Columns::money($table, 'flagged_cost_basis')->nullable();
            Columns::user($table, 'decided_by')->nullable();
            $table->text('reason')->nullable();
            Columns::commandIdentity($table, 'stock_count_findings');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('stock_count_findings')
            ->unique('stock_count_findings_c08_company_key', ['id', 'attributed_company_id'])
            ->foreign('stock_count_findings_scope_foreign', ['stock_count_id', 'product_id', 'location_id', 'condition'], 'stock_count_scopes', ['stock_count_id', 'product_id', 'location_id', 'condition'])
            ->foreign('stock_count_findings_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->codes('stock_count_findings_state_code', 'state', ['OPEN', 'ATTRIBUTED', 'REVERSED_LOSS', 'WITHDRAWN'])
            ->check('stock_count_findings_c09_surplus', 'surplus_qty_base > 0 and surplus_qty_base = round(surplus_qty_base, quantity_scale)')
            ->check('stock_count_findings_flagged_cost_basis', 'flagged_cost_basis is null or flagged_cost_basis >= 0')
            ->uniqueIndex('stock_count_findings_c34_one_open', 'product_id, location_id, condition', "state = 'OPEN'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'attributed_company_id', 'flagged_cost_basis', 'decided_by', 'reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('stock_count_findings');
    }
};
