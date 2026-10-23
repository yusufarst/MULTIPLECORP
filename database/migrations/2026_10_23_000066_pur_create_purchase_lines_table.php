<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_lines` (AF, draft-editable before the first effect): the actual price snapshot
// of a purchased quantity at its ratio (§7, §14; C-09), for a project line of the purchase's company or
// none (C-32). A non-stock line is always delivered to the warehouse. The line is edited only before its
// first effect (L-23), afterwards through `purchase_line_adjustments`; the trigger refuses the edit once
// any fact of the line exists — a lot cost or a direct cost attribution, which a receipt and a drop-ship
// confirmation post with it (AX-04, AX-08), a closure, an adjustment, or a guard that shows a received,
// closed or confirmed quantity or a posted value. A purchase line is never deleted (§19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('purchase_id');
            $table->bigInteger('company_id');
            $table->integer('line_no');
            $table->text('kind');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('project_line_id')->nullable();
            $table->text('delivery_mode');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            // Actual, excluding creditable tax.
            Columns::money($table, 'unit_price');
            Columns::money($table, 'line_value');
            $table->text('description');
            Columns::commandIdentity($table, 'purchase_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('purchase_id');
        });

        TableRules::for('purchase_lines')
            ->unique('purchase_lines_c32_company_key', ['company_id', 'id'])
            // The key a closure cites its line through, with its company, product and scale (§1; C-09, C-32).
            ->unique('purchase_lines_c09_scale_key', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('purchase_lines_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->foreign('purchase_lines_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('purchase_lines_c32_project_line_foreign', ['company_id', 'project_line_id'], 'project_lines', ['company_id', 'id'])
            ->codes('purchase_lines_kind_code', 'kind', ['GOODS', 'NONSTOCK'])
            ->codes('purchase_lines_delivery_mode_code', 'delivery_mode', ['WAREHOUSE', 'DROPSHIP'])
            ->check('purchase_lines_nonstock_to_warehouse', "kind = 'GOODS' or delivery_mode = 'WAREHOUSE'")
            ->check('purchase_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('purchase_lines_c09_scale', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('purchase_lines_values', 'unit_price >= 0 and line_value >= 0')
            ->index('purchase_lines_project_line', 'project_line_id', 'project_line_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'kind', 'product_id', 'quantity_scale', 'project_line_id', 'delivery_mode', 'entered_qty', 'entered_unit_id', 'ratio_to_base',
                'qty_base', 'unit_price', 'line_value', 'description', 'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            -- PL/pgSQL resolves the fact tables of the later migrations when it runs, not when it is created.
            create or replace function purchase_line_has_effect(line bigint) returns boolean
            language plpgsql stable as $$
            begin
                return exists (select from lot_cost_entries where purchase_line_id = line)
                    or exists (select from cost_attributions where purchase_line_id = line)
                    or exists (select from purchase_line_closures where purchase_line_id = line)
                    or exists (select from purchase_line_adjustments where purchase_line_id = line)
                    or exists (
                        select from purchase_line_balances
                        where purchase_line_id = line and (normal_received_net_qty_base > 0 or closed_qty_base > 0
                            or dropship_confirmed_net_qty_base > 0 or replacement_received_net_qty_base > 0
                            or posted_goods_value > 0 or posted_noncreditable_tax_value > 0)
                    );
            end
            $$;

            create or replace function purchase_lines_draft_until_first_effect() returns trigger
            language plpgsql as $$
            begin
                if purchase_line_has_effect(old.id) then
                    raise exception 'A purchase line is edited only before its first effect, then adjusted (DATABASE §4.6; L-23).'
                        using errcode = 'check_violation', constraint = 'purchase_lines_draft_until_first_effect';
                end if;

                return new;
            end
            $$;

            create trigger purchase_lines_draft_until_first_effect
                before update on purchase_lines
                for each row execute function purchase_lines_draft_until_first_effect();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('purchase_lines');
        DB::unprepared('drop function if exists purchase_lines_draft_until_first_effect()');
        DB::unprepared('drop function if exists purchase_line_has_effect(bigint)');
    }
};
