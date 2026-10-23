<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_scope_balances` (GB), §5.2 and §5.8: the physical quantity of a product at a location
// in a condition — lot claims less pending reductions plus pending additions, generated —, every pending
// reduction backed by lot claims of its own scope (C-03); its `version` and last entry are a count's
// staleness basis (L-14; C-33).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_scope_balances', function (Blueprint $table) {
            Columns::reference($table, 'product_id', 'products');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            Columns::quantity($table, 'claims_qty')->default(0);
            Columns::quantity($table, 'pending_reduction_qty')->default(0);
            Columns::quantity($table, 'pending_addition_qty')->default(0);
            Columns::quantity($table, 'qty')->storedAs('claims_qty - pending_reduction_qty + pending_addition_qty');
            $table->bigInteger('version')->default(0);
            Columns::reference($table, 'last_entry_id', 'stock_movement_entries')->nullable();
            Columns::recordedAt($table);
            $table->primary(['product_id', 'location_id', 'condition']);
        });

        TableRules::for('stock_scope_balances')
            ->codes('stock_scope_balances_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('stock_scope_balances_c03_claims', 'claims_qty >= pending_reduction_qty and pending_reduction_qty >= 0 and pending_addition_qty >= 0')
            // IX-12: stock by rack or location (QB-05; AC-22).
            ->index('stock_scope_balances_ix12_location', 'location_id, product_id, condition')
            ->grant('select, insert')
            ->grantUpdate(['claims_qty', 'pending_reduction_qty', 'pending_addition_qty', 'version', 'last_entry_id']);
    }

    public function down(): void
    {
        Schema::drop('stock_scope_balances');
    }
};
