<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `project_lines` (SR) and §9: the demand spine every reservation, purchase link, dispatch,
// delivery and invoice line cites. Its draft description, quantity, unit and price are edited while it
// has no pin; a confirmation pins them (`confirmation_line_pins`) and moves `current_pin_id`, the key of
// which 2026_10_23_000060 adds. The line carries its product's scale (§1, §7; C-09), so the pins,
// quotation lines and cancellations that cite it take the product and scale through one key with the
// company (C-32). A re-plan split names the line it came from (L-21). An unconfirmed line is deleted —
// with its DRAFT project, which cascades —, a pinned one never (§13, §19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->integer('line_no');
            $table->text('kind');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->text('description');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            Columns::money($table, 'unit_price')->nullable();
            $table->text('fulfillment_mode');
            $table->bigInteger('split_from_line_id')->nullable();
            $table->bigInteger('current_pin_id')->nullable();
            Columns::commandIdentity($table, 'project_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('project_lines')
            ->unique('project_lines_line_no', ['project_id', 'line_no'])
            ->unique('project_lines_c32_company_key', ['company_id', 'id'])
            // The key a pin, a quotation line or a cancellation cites its line through, with its company, product and scale (§1; C-09, C-32).
            ->unique('project_lines_c09_scale_key', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->unique('project_lines_project_key', ['project_id', 'id'])
            ->unique('project_lines_mode_key', ['id', 'fulfillment_mode'])
            ->foreign('project_lines_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'], cascadeDraftDelete: true)
            ->foreign('project_lines_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('project_lines_split_from_foreign', ['project_id', 'split_from_line_id'], 'project_lines', ['project_id', 'id'])
            ->codes('project_lines_kind_code', 'kind', ['GOODS', 'SERVICE'])
            ->codes('project_lines_fulfillment_mode_code', 'fulfillment_mode', ['WAREHOUSE', 'DROPSHIP', 'SERVICE'])
            ->check('project_lines_mode_by_kind', "(kind = 'SERVICE') = (fulfillment_mode = 'SERVICE')")
            ->check('project_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('project_lines_c09_scale', 'qty_base >= 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('project_lines_unit_price', 'unit_price is null or unit_price >= 0')
            ->index('project_lines_split_from', 'split_from_line_id', 'split_from_line_id is not null')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate([
                'kind', 'product_id', 'quantity_scale', 'description', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base',
                'unit_price', 'fulfillment_mode', 'current_pin_id', 'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function project_lines_unconfirmed_delete_only() returns trigger
            language plpgsql as $$
            begin
                if old.current_pin_id is not null then
                    raise exception 'Only an unconfirmed project line is deleted (DATABASE §13, §19).'
                        using errcode = 'check_violation', constraint = 'project_lines_unconfirmed_delete_only';
                end if;

                return old;
            end
            $$;

            create trigger project_lines_unconfirmed_delete_only
                before delete on project_lines
                for each row execute function project_lines_unconfirmed_delete_only();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('project_lines');
        DB::unprepared('drop function if exists project_lines_unconfirmed_delete_only()');
    }
};
