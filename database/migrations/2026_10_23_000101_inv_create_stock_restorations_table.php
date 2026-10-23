<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_restorations` (AF), §5.3 and §5.7: SF-RESTORE — the return of consumed units to their
// lot, or of a drop-ship confirmation line, capped by its consumption guard (C-27, C-55). Its source is an
// exclusive arc by kind and its source company the cited consumption's (C-28). A return is entered in the
// cited event's own unit at that event's ratio or in the product's base unit, never at a master ratio (L-31):
// a dispatch's restoration cites its own consumption's dispatch line and that line's unit snapshot, the
// base-unit alternative and the citation by the consumption's kind checked by the trigger after the keys.
// The drop-ship kinds may carry no movement; a DROPSHIP_ERROR_REVERSAL undoes a supplier return or a
// confirmation reversal in error once and exactly, on the same line (CM-37; C-59). The drop-ship line is
// TASK-009's, which keys it. A drop-ship return lot's restoration is keyed here, with its source company.
return new class extends Migration
{
    private const DROPSHIP = "'DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL', 'DROPSHIP_ERROR_REVERSAL'";

    public function up(): void
    {
        Schema::create('stock_restorations', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'stock_movement_id', 'stock_movements')->nullable();
            $table->bigInteger('stock_consumption_id')->nullable();
            $table->bigInteger('dropship_confirmation_line_id')->nullable();
            Columns::reference($table, 'source_company_id', 'companies');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('cited_dispatch_line_id')->nullable();
            Columns::reference($table, 'cited_unit_id', 'units')->nullable();
            $table->bigInteger('cited_ratio_to_base')->nullable();
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'qty_base');
            $table->text('kind');
            $table->boolean('after_delivery')->nullable();
            $table->bigInteger('reverses_restoration_id')->nullable();
            $table->text('reverses_restoration_kind')->nullable();
            $table->text('returned_condition')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            $table->bigInteger('case_company_id')->nullable();
            Columns::commandIdentity($table, 'stock_restorations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('stock_restorations')
            ->unique('stock_restorations_c08_source_key', ['id', 'source_company_id'])
            ->unique('stock_restorations_c55_exact_key', ['id', 'qty_base', 'dropship_confirmation_line_id', 'kind'])
            ->foreign('stock_restorations_c28_consumption_foreign', ['stock_consumption_id', 'source_company_id'], 'stock_consumptions', ['id', 'source_company_id'])
            ->foreign('stock_restorations_l31_cited_dispatch_foreign', ['stock_consumption_id', 'cited_dispatch_line_id'], 'stock_consumptions', ['id', 'dispatch_line_id'])
            ->foreign('stock_restorations_l31_cited_unit_foreign', ['cited_dispatch_line_id', 'cited_unit_id', 'cited_ratio_to_base'], 'dispatch_lines', ['id', 'entered_unit_id', 'ratio_to_base'])
            ->foreign('stock_restorations_c55_reverses_foreign', ['reverses_restoration_id', 'qty_base', 'dropship_confirmation_line_id', 'reverses_restoration_kind'], 'stock_restorations', ['id', 'qty_base', 'dropship_confirmation_line_id', 'kind'])
            ->foreign('stock_restorations_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('stock_restorations_c32_correction_case_foreign', ['case_company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('stock_restorations_kind_code', 'kind', [
                'DISPATCH_REVERSAL', 'UNDELIVERED_RETURN', 'SALES_RETURN', 'DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL',
                'DROPSHIP_ERROR_REVERSAL', 'SUBSTITUTION', 'LOSS_REVERSAL', 'RETURN_ERROR_REVERSAL',
            ])
            ->codes('stock_restorations_returned_condition_code', 'returned_condition', ['USABLE', 'UNUSABLE'], nullable: true)
            ->check('stock_restorations_movement_by_kind', "(stock_movement_id is null) = (kind in ('DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL', 'DROPSHIP_ERROR_REVERSAL')) and (stock_movement_id is null) = (returned_condition is null)")
            ->check('stock_restorations_c27_source_by_kind', 'num_nonnulls(stock_consumption_id, dropship_confirmation_line_id) = 1 and (kind in ('.self::DROPSHIP.')) = (dropship_confirmation_line_id is not null)')
            ->check('stock_restorations_l31_cited_unit', 'num_nonnulls(cited_unit_id, cited_ratio_to_base) in (0, 2)'
                .' and (kind not in ('.self::DROPSHIP.') or (cited_unit_id is not null and cited_dispatch_line_id is null))'
                .' and (cited_dispatch_line_id is null or cited_unit_id is not null)')
            ->check('stock_restorations_c09_conversion', 'ratio_to_base >= 1 and qty_base > 0 and qty_base = entered_qty * ratio_to_base and qty_base = round(qty_base, quantity_scale)')
            ->check('stock_restorations_l31_cited_or_base_unit', 'ratio_to_base = 1 or (cited_unit_id is not null and entered_unit_id = cited_unit_id and ratio_to_base = cited_ratio_to_base)')
            ->check('stock_restorations_after_delivery_by_kind', <<<'SQL'
                case
                    when kind = 'SALES_RETURN' then after_delivery is true
                    when kind in ('UNDELIVERED_RETURN', 'DISPATCH_REVERSAL') then after_delivery is false
                    when kind in ('DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN') then after_delivery is not null
                    else after_delivery is null
                end
                SQL)
            ->check('stock_restorations_c55_error_reversal', "(kind = 'DROPSHIP_ERROR_REVERSAL') = (reverses_restoration_id is not null) and (reverses_restoration_id is null) = (reverses_restoration_kind is null) and (reverses_restoration_kind is null or reverses_restoration_kind in ('DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL'))")
            ->check('stock_restorations_case_pair', 'num_nonnulls(correction_case_id, case_company_id) in (0, 2)')
            ->uniqueIndex('stock_restorations_c55_one_error_reversal', 'reverses_restoration_id', 'reverses_restoration_id is not null')
            // IX-08: a consumption's restorations; a drop-ship line's.
            ->index('stock_restorations_ix08_consumption', 'stock_consumption_id', 'stock_consumption_id is not null')
            ->index('stock_restorations_dropship_line', 'dropship_confirmation_line_id', 'dropship_confirmation_line_id is not null')
            ->commandIdentity()
            ->grant('select, insert');

        TableRules::for('inventory_lots')
            ->foreign('inventory_lots_c08_restoration_foreign', ['restoration_id', 'source_company_id'], 'stock_restorations', ['id', 'source_company_id']);

        DB::unprepared(<<<'SQL'
            create or replace function stock_restorations_l31_cited_unit_by_source() returns trigger
            language plpgsql as $$
            declare
                consumption_kind text := (select c.kind from stock_consumptions c where c.id = new.stock_consumption_id);
            begin
                if (consumption_kind = 'DISPATCH') <> (new.cited_dispatch_line_id is not null)
                    or (consumption_kind is not null and consumption_kind <> 'DISPATCH' and new.cited_unit_id is not null)
                    or (new.ratio_to_base = 1 and not coalesce(new.entered_unit_id = new.cited_unit_id and new.cited_ratio_to_base = 1, false)
                        and not exists (select from products p where p.id = new.product_id and p.base_unit_id = new.entered_unit_id)) then
                    raise exception 'A return is entered in its cited event''s unit at that ratio, or in the product''s base unit (DATABASE §4.7; L-31).'
                        using errcode = 'check_violation', constraint = 'stock_restorations_l31_cited_unit_by_source';
                end if;

                return null;
            end
            $$;

            create constraint trigger stock_restorations_l31_cited_unit_by_source
                after insert on stock_restorations
                for each row execute function stock_restorations_l31_cited_unit_by_source();
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table inventory_lots drop constraint inventory_lots_c08_restoration_foreign');
        Schema::drop('stock_restorations');
        DB::unprepared('drop function if exists stock_restorations_l31_cited_unit_by_source()');
    }
};
