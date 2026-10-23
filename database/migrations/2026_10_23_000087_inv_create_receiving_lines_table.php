<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `receiving_lines` (AF): a receipt of a purchase line of its company, at its ratio snapshot
// and its product's scale (§1, §7; C-09, C-32), in three outcomes — usable, damaged and rejected, which
// never enters stock (L-13). A SUPPLEMENT (CM-04) or RE_RECEIPT (CM-02) cites a receipt of the same line,
// a RE_RECEIPT only once that receipt's lot carries a RECEIPT_REVERSAL (trigger); a REPLACEMENT names its
// purchase-return case. The original delivery note is unique per line (AX-04; C-49) without blocking the
// corrections, which cite the original instead. The lot it creates cites it, never the reverse.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('stock_movement_id');
            $table->bigInteger('company_id');
            $table->bigInteger('purchase_line_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            Columns::quantity($table, 'usable_qty_base');
            Columns::quantity($table, 'damaged_qty_base');
            Columns::quantity($table, 'rejected_qty_base');
            $table->text('receipt_kind');
            $table->bigInteger('corrects_receiving_line_id')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            $table->text('supplier_delivery_reference')->nullable();
            Columns::commandIdentity($table, 'receiving_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('stock_movement_id');
        });

        TableRules::for('receiving_lines')
            // The key a lot cites its receipt through, with its source company (§4.7; C-08).
            ->unique('receiving_lines_company_key', ['id', 'company_id'])
            ->unique('receiving_lines_purchase_line_key', ['id', 'purchase_line_id'])
            ->foreign('receiving_lines_c32_movement_foreign', ['company_id', 'stock_movement_id'], 'stock_movements', ['company_id', 'id'])
            ->foreign('receiving_lines_c09_purchase_line_foreign', ['company_id', 'purchase_line_id', 'product_id', 'quantity_scale'], 'purchase_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('receiving_lines_c59_corrects_foreign', ['corrects_receiving_line_id', 'purchase_line_id'], 'receiving_lines', ['id', 'purchase_line_id'])
            ->foreign('receiving_lines_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('receiving_lines_kind_code', 'receipt_kind', ['ORIGINAL', 'SUPPLEMENT', 'RE_RECEIPT', 'REPLACEMENT'])
            ->check('receiving_lines_c09_conversion', 'ratio_to_base >= 1 and qty_base = entered_qty * ratio_to_base')
            ->check('receiving_lines_c09_scale', <<<'SQL'
                qty_base > 0 and qty_base = round(qty_base, quantity_scale) and usable_qty_base = round(usable_qty_base, quantity_scale)
                and damaged_qty_base = round(damaged_qty_base, quantity_scale) and rejected_qty_base = round(rejected_qty_base, quantity_scale)
                SQL)
            ->check('receiving_lines_outcomes', 'usable_qty_base >= 0 and damaged_qty_base >= 0 and rejected_qty_base >= 0 and usable_qty_base + damaged_qty_base + rejected_qty_base = qty_base')
            ->check('receiving_lines_c59_correction_by_kind', "(receipt_kind in ('SUPPLEMENT', 'RE_RECEIPT')) = (corrects_receiving_line_id is not null)")
            ->check('receiving_lines_replacement_case', "(receipt_kind = 'REPLACEMENT') = (correction_case_id is not null)")
            ->uniqueIndex('receiving_lines_c49_original_delivery', 'purchase_line_id, supplier_delivery_reference', "receipt_kind = 'ORIGINAL' and supplier_delivery_reference is not null")
            ->index('receiving_lines_purchase_line', 'purchase_line_id')
            // §20: correction lineage.
            ->index('receiving_lines_corrects', 'corrects_receiving_line_id', 'corrects_receiving_line_id is not null')
            ->commandIdentity()
            ->grant('select, insert');

        // PL/pgSQL resolves the lots and entries of the later migrations when it runs.
        DB::unprepared(<<<'SQL'
            create or replace function receiving_lines_c59_re_receipt_after_reversal() returns trigger
            language plpgsql as $$
            begin
                if not exists (
                    select from inventory_lots l
                    join stock_movement_entries e on e.lot_id = l.id and e.movement_type = 'RECEIPT_REVERSAL'
                    where l.receiving_line_id = new.corrects_receiving_line_id
                ) then
                    raise exception 'A re-receipt cites a receipt whose lot carries a RECEIPT_REVERSAL (DATABASE §4.7; CM-02).'
                        using errcode = 'check_violation', constraint = 'receiving_lines_c59_re_receipt_after_reversal';
                end if;

                return null;
            end
            $$;

            create constraint trigger receiving_lines_c59_re_receipt_after_reversal
                after insert on receiving_lines
                for each row when (new.receipt_kind = 'RE_RECEIPT')
                execute function receiving_lines_c59_re_receipt_after_reversal();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('receiving_lines');
        DB::unprepared('drop function if exists receiving_lines_c59_re_receipt_after_reversal()');
    }
};
