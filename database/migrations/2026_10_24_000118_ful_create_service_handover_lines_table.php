<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `service_handover_lines` (AF): the quantity of a demand line handed over, at its entered-unit
// snapshot and its product's scale like every other quantity line (§1, §7; BR-INV-03; C-09), within its
// handover's company (C-32). Σ handed over ≤ pinned demand is the line guard's (C-30); a reversal line is
// exact and once.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_handover_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('service_handover_id');
            $table->bigInteger('company_id');
            $table->bigInteger('project_line_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            $table->text('scope_note')->nullable();
            $table->bigInteger('reverses_line_id')->nullable();
            Columns::commandIdentity($table, 'service_handover_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('service_handover_id');
            $table->index('project_line_id');
        });

        TableRules::for('service_handover_lines')
            ->unique('service_handover_lines_c30_quantity_key', ['id', 'qty_base'])
            ->foreign('service_handover_lines_c32_handover_foreign', ['company_id', 'service_handover_id'], 'service_handovers', ['company_id', 'id'])
            ->foreign('service_handover_lines_c09_project_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('service_handover_lines_c30_reverses_foreign', ['reverses_line_id', 'qty_base'], 'service_handover_lines', ['id', 'qty_base'])
            ->check('service_handover_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('service_handover_lines_c09_scale', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->uniqueIndex('service_handover_lines_c30_reversed_once', 'reverses_line_id', 'reverses_line_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('service_handover_lines');
    }
};
