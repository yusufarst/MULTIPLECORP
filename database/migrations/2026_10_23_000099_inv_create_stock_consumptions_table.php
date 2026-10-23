<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_consumptions` (AF), §5.3 and §6.5: every draw of lot quantity and the inter-company
// allocation overlay (D-DB-14; BR-INV-05; L-44). The source is the lot's company and the bearer is keyed to
// its cause — a dispatch's line and project, an unattributed loss's resolution line (its bearer project by
// the trigger), a purchase return's own source with no project —, so neither economic root can contradict
// what caused it (C-28); a bearer other than the source needs its reason. A case is the source's or the
// bearer's (§15). The quantity is its lot's product's, at its scale (§1; C-09).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_consumptions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'stock_movement_id', 'stock_movements');
            $table->bigInteger('lot_id');
            $table->bigInteger('source_company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'qty_base');
            $table->text('kind');
            $table->bigInteger('dispatch_line_id')->nullable();
            $table->bigInteger('unattributed_resolution_line_id')->nullable();
            Columns::reference($table, 'bearer_company_id', 'companies');
            $table->bigInteger('bearer_project_id')->nullable();
            $table->text('allocation_reason')->nullable();
            Columns::user($table, 'allocation_authorized_by')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            $table->bigInteger('case_company_id')->nullable();
            Columns::commandIdentity($table, 'stock_consumptions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('stock_consumptions')
            // The keys a cost row (C-28) and a restoration cite their consumption through (§4.7).
            ->unique('stock_consumptions_c28_lot_key', ['id', 'lot_id', 'source_company_id'])
            ->unique('stock_consumptions_dispatch_key', ['id', 'dispatch_line_id'])
            ->unique('stock_consumptions_source_key', ['id', 'source_company_id'])
            ->foreign('stock_consumptions_c28_lot_foreign', ['lot_id', 'source_company_id'], 'inventory_lots', ['id', 'source_company_id'])
            ->foreign('stock_consumptions_lot_product_foreign', ['lot_id', 'product_id'], 'inventory_lots', ['id', 'product_id'])
            ->foreign('stock_consumptions_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('stock_consumptions_c32_bearer_project_foreign', ['bearer_company_id', 'bearer_project_id'], 'projects', ['company_id', 'id'])
            ->foreign('stock_consumptions_c28_dispatch_foreign', ['dispatch_line_id', 'bearer_company_id', 'bearer_project_id'], 'dispatch_lines', ['id', 'company_id', 'project_id'])
            ->foreign('stock_consumptions_c28_resolution_line_foreign', ['unattributed_resolution_line_id', 'bearer_company_id'], 'unattributed_loss_resolution_lines', ['id', 'assigned_company_id'])
            ->foreign('stock_consumptions_c32_correction_case_foreign', ['case_company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('stock_consumptions_kind_code', 'kind', ['DISPATCH', 'LOSS', 'PURCHASE_RETURN', 'UNATTRIBUTED_LOSS'])
            ->check('stock_consumptions_c09_quantity', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->check('stock_consumptions_c28_dispatch_bearer', "kind <> 'DISPATCH' or (dispatch_line_id is not null and bearer_project_id is not null)")
            ->check('stock_consumptions_c28_purchase_return_bearer', "kind <> 'PURCHASE_RETURN' or (bearer_company_id = source_company_id and bearer_project_id is null)")
            ->check('stock_consumptions_c28_cause_by_kind', "(kind = 'DISPATCH') = (dispatch_line_id is not null) and (kind = 'UNATTRIBUTED_LOSS') = (unattributed_resolution_line_id is not null)")
            ->check('stock_consumptions_c28_allocation_reason', 'source_company_id = bearer_company_id or allocation_reason is not null')
            ->check('stock_consumptions_case_pair', 'num_nonnulls(correction_case_id, case_company_id) in (0, 2)')
            ->check('stock_consumptions_case_side', 'case_company_id is null or case_company_id = source_company_id or case_company_id = bearer_company_id')
            // IX-08: a lot's consumptions; their causes and bearers.
            ->index('stock_consumptions_ix08_lot', 'lot_id')
            ->index('stock_consumptions_dispatch_line', 'dispatch_line_id', 'dispatch_line_id is not null')
            ->index('stock_consumptions_resolution_line', 'unattributed_resolution_line_id', 'unattributed_resolution_line_id is not null')
            ->index('stock_consumptions_movement', 'stock_movement_id')
            ->commandIdentity()
            ->grant('select, insert');

        DB::unprepared(<<<'SQL'
            create or replace function stock_consumptions_c28_resolution_bearer_project() returns trigger
            language plpgsql as $$
            begin
                if not exists (
                    select from unattributed_loss_resolution_lines l
                    where l.id = new.unattributed_resolution_line_id and l.bearer_project_id is not distinct from new.bearer_project_id
                ) then
                    raise exception 'An unattributed loss is borne by its resolution line''s project (DATABASE §4.7; C-28).'
                        using errcode = 'check_violation', constraint = 'stock_consumptions_c28_resolution_bearer_project';
                end if;

                return null;
            end
            $$;

            create constraint trigger stock_consumptions_c28_resolution_bearer_project
                after insert on stock_consumptions
                for each row when (new.kind = 'UNATTRIBUTED_LOSS')
                execute function stock_consumptions_c28_resolution_bearer_project();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('stock_consumptions');
        DB::unprepared('drop function if exists stock_consumptions_c28_resolution_bearer_project()');
    }
};
