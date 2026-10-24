<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `delivery_lines` (AF): the quantity of a demand line handed to the client from one shipment —
// a dispatch line or a drop-ship confirmation line, an exclusive arc (§1) — at its ratio snapshot and its
// product's scale (§1, §7; C-09), each reference its delivery's company's (C-32). Delivered ≤ shipped is the
// line guard's (AX-09; C-30). An entry error is reversed once and exactly by a reversal line, then recorded
// again (CM-08). `dispatch_lines` gains its company key (§1).
return new class extends Migration
{
    public function up(): void
    {
        TableRules::for('dispatch_lines')->unique('dispatch_lines_c32_company_key', ['company_id', 'id']);

        Schema::create('delivery_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('delivery_id');
            $table->bigInteger('company_id');
            $table->bigInteger('project_line_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('dispatch_line_id')->nullable();
            $table->bigInteger('dropship_confirmation_line_id')->nullable();
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            $table->bigInteger('reverses_delivery_line_id')->nullable();
            Columns::commandIdentity($table, 'delivery_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('delivery_id');
            $table->index('dispatch_line_id');
            $table->index('dropship_confirmation_line_id');
        });

        TableRules::for('delivery_lines')
            // The key a reversal line cites its original's exact quantity through (CM-08; C-30).
            ->unique('delivery_lines_c30_quantity_key', ['id', 'qty_base'])
            ->foreign('delivery_lines_c32_delivery_foreign', ['company_id', 'delivery_id'], 'deliveries', ['company_id', 'id'])
            ->foreign('delivery_lines_c09_project_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('delivery_lines_c32_dispatch_line_foreign', ['company_id', 'dispatch_line_id'], 'dispatch_lines', ['company_id', 'id'])
            ->foreign('delivery_lines_c32_dropship_line_foreign', ['company_id', 'dropship_confirmation_line_id'], 'dropship_confirmation_lines', ['company_id', 'id'])
            ->foreign('delivery_lines_c30_reverses_foreign', ['reverses_delivery_line_id', 'qty_base'], 'delivery_lines', ['id', 'qty_base'])
            ->check('delivery_lines_one_shipment', 'num_nonnulls(dispatch_line_id, dropship_confirmation_line_id) = 1')
            ->check('delivery_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('delivery_lines_c09_scale', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->uniqueIndex('delivery_lines_c30_reversed_once', 'reverses_delivery_line_id', 'reverses_delivery_line_id is not null')
            // IX-08: a project line's deliveries.
            ->index('delivery_lines_ix08_project_line', 'project_line_id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('delivery_lines');
        DB::statement('alter table dispatch_lines drop constraint dispatch_lines_c32_company_key');
    }
};
