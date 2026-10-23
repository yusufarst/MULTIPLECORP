<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `confirmation_line_pins` (SN) and §9: the conversion, price and tax a confirmation pins for
// each of its lines (BR-INV-03; BR-SN-01), immutable; a re-confirmation adds new pins (WF-PRJ-02) and moves
// the line's `current_pin_id` in the same transaction, a pointer this migration keys to a pin of the same
// line. The pin cites its line with the confirmation's company, the line's product and scale (C-09, C-32).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmation_line_pins', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('commercial_confirmation_id');
            $table->bigInteger('company_id');
            $table->bigInteger('project_line_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->text('description');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            Columns::money($table, 'unit_price');
            Columns::money($table, 'sales_value');
            // Each component's type, rate, base, amount and treatment rule (§14).
            $table->jsonb('tax_components')->default('[]');
            Columns::commandIdentity($table, 'confirmation_line_pins');
            Columns::recordedAt($table);
        });

        TableRules::for('confirmation_line_pins')
            ->unique('confirmation_line_pins_one_per_line', ['commercial_confirmation_id', 'project_line_id'])
            ->unique('confirmation_line_pins_line_key', ['project_line_id', 'id'])
            ->foreign('confirmation_line_pins_c32_confirmation_foreign', ['company_id', 'commercial_confirmation_id'], 'commercial_confirmations', ['company_id', 'id'])
            ->foreign('confirmation_line_pins_c09_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->check('confirmation_line_pins_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('confirmation_line_pins_c09_scale', 'qty_base >= 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('confirmation_line_pins_values', 'unit_price >= 0 and sales_value >= 0')
            ->check('confirmation_line_pins_tax_components_list', "jsonb_typeof(tax_components) = 'array'")
            ->commandIdentity()
            ->grant('select, insert');

        TableRules::for('project_lines')
            ->foreign('project_lines_current_pin_foreign', ['id', 'current_pin_id'], 'confirmation_line_pins', ['project_line_id', 'id']);
    }

    public function down(): void
    {
        DB::statement('alter table project_lines drop constraint project_lines_current_pin_foreign');
        Schema::drop('confirmation_line_pins');
    }
};
