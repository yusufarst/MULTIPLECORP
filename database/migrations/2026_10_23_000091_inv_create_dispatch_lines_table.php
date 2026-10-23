<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `dispatch_lines` (AF): the outbound shipment of a demand line (WF-INV-03) at its ratio
// snapshot and its product's scale (§1, §7; C-09), within its line's project and company (C-32), reserved
// and unreserved parts summing to the shipped quantity, consuming only its own line's reservation. DOC-03
// renders its movement; a return converts at this snapshot (L-31), through the keys consumptions and
// restorations cite. `project_lines` gains the (`id`, `project_id`, `company_id`) key §4.7 references.
return new class extends Migration
{
    public function up(): void
    {
        TableRules::for('project_lines')->unique('project_lines_dispatch_key', ['id', 'project_id', 'company_id']);

        Schema::create('dispatch_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('stock_movement_id');
            $table->bigInteger('project_line_id');
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            Columns::quantity($table, 'reserved_qty_base');
            Columns::quantity($table, 'unreserved_qty_base');
            $table->bigInteger('reservation_id')->nullable();
            Columns::commandIdentity($table, 'dispatch_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('stock_movement_id');
        });

        TableRules::for('dispatch_lines')
            // The keys a consumption's bearer and a restoration's cited unit go through (§4.7).
            ->unique('dispatch_lines_c28_bearer_key', ['id', 'company_id', 'project_id'])
            ->unique('dispatch_lines_c09_unit_key', ['id', 'entered_unit_id', 'ratio_to_base'])
            ->foreign('dispatch_lines_c32_line_foreign', ['project_line_id', 'project_id', 'company_id'], 'project_lines', ['id', 'project_id', 'company_id'])
            ->foreign('dispatch_lines_c09_line_scale_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('dispatch_lines_c32_movement_foreign', ['company_id', 'stock_movement_id'], 'stock_movements', ['company_id', 'id'])
            ->foreign('dispatch_lines_c05_reservation_foreign', ['reservation_id', 'project_line_id'], 'reservations', ['id', 'project_line_id'])
            ->check('dispatch_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('dispatch_lines_c09_scale', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale) and reserved_qty_base = round(reserved_qty_base, quantity_scale)')
            ->check('dispatch_lines_c05_reserved_parts', 'reserved_qty_base >= 0 and unreserved_qty_base >= 0 and reserved_qty_base + unreserved_qty_base = qty_base')
            ->check('dispatch_lines_c05_reservation_when_reserved', '(reserved_qty_base > 0) = (reservation_id is not null)')
            // IX-08: a project line's dispatches.
            ->index('dispatch_lines_ix08_project_line', 'project_line_id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('dispatch_lines');
        DB::statement('alter table project_lines drop constraint project_lines_dispatch_key');
    }
};
