<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_count_lines` (AF): an observation within a scope the count opened, at the product's
// scale (§1; C-09) — a counted quantity, with a registered serial or the text of an unregistered one, which
// becomes a finding (WF-INV-05).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('stock_count_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('location_id');
            $table->text('condition');
            Columns::quantity($table, 'counted_qty');
            Columns::reference($table, 'serial_unit_id', 'serial_units')->nullable();
            $table->text('unregistered_serial_text')->nullable();
            Columns::user($table, 'counted_by');
            Columns::commandIdentity($table, 'stock_count_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('stock_count_lines')
            ->foreign('stock_count_lines_scope_foreign', ['stock_count_id', 'product_id', 'location_id', 'condition'], 'stock_count_scopes', ['stock_count_id', 'product_id', 'location_id', 'condition'])
            ->foreign('stock_count_lines_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->check('stock_count_lines_c09_counted', 'counted_qty >= 0 and counted_qty = round(counted_qty, quantity_scale)')
            ->check('stock_count_lines_one_serial', 'num_nonnulls(serial_unit_id, unregistered_serial_text) <= 1')
            ->index('stock_count_lines_count', 'stock_count_id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('stock_count_lines');
    }
};
