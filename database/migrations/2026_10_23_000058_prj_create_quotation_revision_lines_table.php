<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `quotation_revision_lines` (SN once SENT): the quoted description, quantity at its ratio
// snapshot (§7; C-09), price and tax components of a project line. Added, edited and deleted while its
// revision is a DRAFT; from SENT on the trigger freezes them (§28). The line is its revision's company's,
// with its product and scale (C-32).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_revision_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('quotation_revision_id');
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
            Columns::commandIdentity($table, 'quotation_revision_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('quotation_revision_id');
            $table->index('project_line_id');
        });

        TableRules::for('quotation_revision_lines')
            ->foreign('quotation_revision_lines_c32_revision_foreign', ['company_id', 'quotation_revision_id'], 'quotation_revisions', ['company_id', 'id'], cascadeDraftDelete: true)
            ->foreign('quotation_revision_lines_c09_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->check('quotation_revision_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('quotation_revision_lines_c09_scale', 'qty_base >= 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('quotation_revision_lines_values', 'unit_price >= 0 and sales_value >= 0')
            ->check('quotation_revision_lines_tax_components_list', "jsonb_typeof(tax_components) = 'array'")
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate([
                'description', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base', 'unit_price', 'sales_value', 'tax_components',
                'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function quotation_revision_lines_frozen_once_sent() returns trigger
            language plpgsql as $$
            begin
                -- A cascade from its deleted DRAFT revision finds no parent left.
                if exists (
                    select from quotation_revisions r
                    where r.id = case when tg_op = 'INSERT' then new.quotation_revision_id else old.quotation_revision_id end and r.state <> 'DRAFT'
                ) then
                    raise exception 'A quotation line is frozen once its revision is SENT (DATABASE §4.5, §28).'
                        using errcode = 'check_violation', constraint = 'quotation_revision_lines_frozen_once_sent';
                end if;

                return case when tg_op = 'DELETE' then old else new end;
            end
            $$;

            create trigger quotation_revision_lines_frozen_once_sent
                before insert or update or delete on quotation_revision_lines
                for each row execute function quotation_revision_lines_frozen_once_sent();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('quotation_revision_lines');
        DB::unprepared('drop function if exists quotation_revision_lines_frozen_once_sent()');
    }
};
