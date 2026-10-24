<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoice_version_lines` (SN once issued, draft-editable): the itemized snapshot of a version —
// a demand line's description, quantity at its ratio snapshot and product scale (§1, §7; C-09) and price, or
// for a value-only down payment or termin a value with no line and no quantity. Added, edited and deleted
// while its version is a DRAFT, and deleted with it (§19); the trigger freezes it once issued (§28). Its
// project line is its version's company's (C-32).
return new class extends Migration
{
    private const QUANTITY = 'project_line_id, product_id, quantity_scale, entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price';

    public function up(): void
    {
        Schema::create('invoice_version_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('invoice_version_id');
            $table->bigInteger('company_id');
            $table->bigInteger('project_line_id')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->smallInteger('quantity_scale')->nullable();
            $table->text('description');
            Columns::quantity($table, 'entered_qty')->nullable();
            Columns::reference($table, 'entered_unit_id', 'units')->nullable();
            $table->bigInteger('ratio_to_base')->nullable();
            Columns::quantity($table, 'qty_base')->nullable();
            Columns::money($table, 'unit_price')->nullable();
            Columns::money($table, 'line_sales_value');
            Columns::commandIdentity($table, 'invoice_version_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('invoice_version_id');
            $table->index('project_line_id');
        });

        TableRules::for('invoice_version_lines')
            ->foreign('invoice_version_lines_c32_version_foreign', ['company_id', 'invoice_version_id'], 'invoice_versions', ['company_id', 'id'], cascadeDraftDelete: true)
            ->foreign('invoice_version_lines_c09_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            // A demand line's quantity and price are all present, or — a value-only line — none (§1).
            ->check('invoice_version_lines_quantity_all_or_none', 'num_nonnulls('.self::QUANTITY.') in (0, 8)')
            ->check('invoice_version_lines_c09_conversion', 'qty_base is null or (ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base)')
            ->check('invoice_version_lines_c09_scale', 'qty_base is null or (qty_base >= 0 and qty_base = round(qty_base, quantity_scale))')
            ->check('invoice_version_lines_values', '(unit_price is null or unit_price >= 0) and line_sales_value >= 0')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate([
                'project_line_id', 'product_id', 'quantity_scale', 'description', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base',
                'unit_price', 'line_sales_value', 'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function invoice_version_lines_frozen_once_issued() returns trigger
            language plpgsql as $$
            begin
                -- A cascade from its deleted DRAFT version finds no parent left.
                if exists (
                    select from invoice_versions v
                    where v.id = case when tg_op = 'INSERT' then new.invoice_version_id else old.invoice_version_id end and v.state <> 'DRAFT'
                ) then
                    raise exception 'An invoice line is frozen once its version is issued (DATABASE §4.12, §28).'
                        using errcode = 'check_violation', constraint = 'invoice_version_lines_frozen_once_issued';
                end if;

                return case when tg_op = 'DELETE' then old else new end;
            end
            $$;

            create trigger invoice_version_lines_frozen_once_issued
                before insert or update or delete on invoice_version_lines
                for each row execute function invoice_version_lines_frozen_once_issued();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('invoice_version_lines');
        DB::unprepared('drop function if exists invoice_version_lines_frozen_once_issued()');
    }
};
