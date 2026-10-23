<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `inventory_lots` (AF) and §5.4: one lot per acquisition (BR-INV-04/11/12; L-15), its source
// company — the economic owner — keyed to the acquisition that created it by exactly one source reference
// of its kind, each a composite key carrying that company and each UNIQUE, so provenance can never
// contradict its cause (C-08, C-28): a receipt's receiving line, an opening's import row, a drop-ship
// return's restoration (keyed by 2026_10_23_000101) or a surplus's count finding. The lot cites its
// source, never the reverse; its quantity at its ratio and its product's scale (§1, §7; C-09).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            Columns::id($table);
            $table->text('lot_code')->unique();
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::reference($table, 'source_company_id', 'companies');
            $table->text('acquisition_kind');
            $table->bigInteger('receiving_line_id')->nullable()->unique();
            $table->bigInteger('import_row_id')->nullable()->unique();
            $table->bigInteger('restoration_id')->nullable()->unique();
            $table->bigInteger('stock_count_finding_id')->nullable()->unique();
            Columns::quantity($table, 'entered_qty');
            Columns::reference($table, 'entered_unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            Columns::quantity($table, 'acquired_qty_base');
            Columns::businessDate($table);
            $table->boolean('cost_basis_flagged')->default(false);
            Columns::commandIdentity($table, 'inventory_lots');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('inventory_lots')
            // The keys a draw, a candidate and an entry cite their lot through (§4.7).
            ->unique('inventory_lots_c28_source_key', ['id', 'source_company_id'])
            ->unique('inventory_lots_product_key', ['id', 'product_id'])
            ->foreign('inventory_lots_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('inventory_lots_c08_receiving_line_foreign', ['receiving_line_id', 'source_company_id'], 'receiving_lines', ['id', 'company_id'])
            ->foreign('inventory_lots_c08_import_row_foreign', ['import_row_id', 'source_company_id'], 'import_rows', ['id', 'company_id'])
            ->foreign('inventory_lots_c08_finding_foreign', ['stock_count_finding_id', 'source_company_id'], 'stock_count_findings', ['id', 'attributed_company_id'])
            ->codes('inventory_lots_acquisition_kind_code', 'acquisition_kind', ['RECEIPT', 'OPENING', 'DROPSHIP_RETURN', 'SURPLUS'])
            ->check('inventory_lots_c08_source_by_kind', <<<'SQL'
                num_nonnulls(receiving_line_id, import_row_id, restoration_id, stock_count_finding_id) = 1
                and (acquisition_kind = 'RECEIPT') = (receiving_line_id is not null)
                and (acquisition_kind = 'OPENING') = (import_row_id is not null)
                and (acquisition_kind = 'DROPSHIP_RETURN') = (restoration_id is not null)
                and (acquisition_kind = 'SURPLUS') = (stock_count_finding_id is not null)
                SQL)
            ->check('inventory_lots_c09_conversion', 'ratio_to_base >= 1 and acquired_qty_base > 0 and acquired_qty_base = entered_qty * ratio_to_base')
            ->check('inventory_lots_c09_scale', 'acquired_qty_base = round(acquired_qty_base, quantity_scale)')
            // §20: the oldest-first picking aid (§5.4).
            ->index('inventory_lots_product_business_date', 'product_id, business_date')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('inventory_lots');
    }
};
