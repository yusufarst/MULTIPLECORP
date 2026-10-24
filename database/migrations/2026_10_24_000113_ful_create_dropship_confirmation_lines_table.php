<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `dropship_confirmation_lines` (AF): the confirmed quantity of a purchase line for a demand
// line, at its ratio snapshot and its product's scale (§1, §7; C-09), each line, purchase line and project
// line its confirmation's company's, one serial per row for serialized goods (§8). A supplier's replacement
// shipment after a CM-15 return carries its case and counts against the replacement cap only. Its cost is
// attributed through direct `cost_attributions` (L-18); returns and reversals cite its unit snapshot, its
// company and its product (§4.7; DIR-091).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_confirmation_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('dropship_confirmation_id');
            $table->bigInteger('company_id');
            $table->bigInteger('purchase_line_id');
            $table->bigInteger('project_line_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            Columns::reference($table, 'serial_unit_id', 'serial_units')->nullable();
            $table->boolean('is_replacement')->default(false);
            $table->bigInteger('correction_case_id')->nullable();
            Columns::commandIdentity($table, 'dropship_confirmation_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('dropship_confirmation_id');
            $table->index('purchase_line_id');
            $table->index('project_line_id');
            $table->index('serial_unit_id');
        });

        TableRules::for('dropship_confirmation_lines')
            ->unique('dropship_confirmation_lines_c32_company_key', ['company_id', 'id'])
            // The keys a drop-ship restoration cites its unit snapshot and its product through (§4.7; DIR-091).
            ->unique('dropship_confirmation_lines_c09_unit_key', ['id', 'entered_unit_id', 'ratio_to_base'])
            ->unique('dropship_confirmation_lines_product_key', ['id', 'product_id'])
            ->foreign('dropship_confirmation_lines_c32_confirmation_foreign', ['company_id', 'dropship_confirmation_id'], 'dropship_confirmations', ['company_id', 'id'])
            ->foreign('dropship_confirmation_lines_c09_purchase_line_foreign', ['company_id', 'purchase_line_id', 'product_id', 'quantity_scale'], 'purchase_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('dropship_confirmation_lines_c09_project_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('dropship_confirmation_lines_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->check('dropship_confirmation_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('dropship_confirmation_lines_c09_scale', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('dropship_confirmation_lines_c02_serial_one', 'serial_unit_id is null or qty_base = 1')
            ->check('dropship_confirmation_lines_c59_replacement_case', 'is_replacement = (correction_case_id is not null)')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('dropship_confirmation_lines');
    }
};
