<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_cases` (SR + GB) and §5.8: a pending unexplained loss or condition change
// in one scope (DIR-026/027), recognized against the claims not already missing — recognized ≤ exposure −
// prior pending (C-36) —, its four counters the only columns the runtime updates, its generated pending
// quantity never negative, so over-resolution, double finding and double reversal are impossible (C-35);
// only a LOSS case is found. A serialized product is refused at recognition (trigger), no key tying the
// case to the product's current serial policy (L-35). An overlap group is named by its founding case; a
// converted case has one successor. `stock_movements.unattributed_case_id` is keyed here.
return new class extends Migration
{
    private const COUNTERS = ['found_qty_base', 'resolved_qty_base', 'reversed_qty_base', 'converted_qty_base'];

    public function up(): void
    {
        Schema::create('unattributed_loss_cases', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('case_kind');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            Columns::quantity($table, 'recognized_qty_base');
            Columns::quantity($table, 'exposure_qty_base');
            Columns::quantity($table, 'prior_pending_qty_base')->default(0);
            Columns::reference($table, 'exposure_group_case_id', 'unattributed_loss_cases')->nullable();

            foreach (self::COUNTERS as $counter) {
                Columns::quantity($table, $counter)->default(0);
            }

            Columns::quantity($table, 'pending_qty_base')->storedAs('recognized_qty_base - found_qty_base - resolved_qty_base - reversed_qty_base - converted_qty_base');
            Columns::reference($table, 'causal_project_id', 'projects')->nullable();
            Columns::reference($table, 'recognition_movement_id', 'stock_movements')->unique();
            Columns::reference($table, 'converted_from_case_id', 'unattributed_loss_cases')->nullable()->unique();
            Columns::businessDate($table);
            Columns::user($table, 'recognized_by');
            $table->text('reason');
            Columns::commandIdentity($table, 'unattributed_loss_cases');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('unattributed_loss_cases')
            // The keys a lot-less entry and a resolution line cite their case through (§1; §4.7).
            ->unique('unattributed_loss_cases_scope_key', ['id', 'product_id', 'location_id'])
            ->unique('unattributed_loss_cases_c09_scale_key', ['id', 'product_id', 'quantity_scale'])
            ->foreign('unattributed_loss_cases_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->codes('unattributed_loss_cases_kind_code', 'case_kind', ['LOSS', 'CONDITION_CHANGE'])
            ->codes('unattributed_loss_cases_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('unattributed_loss_cases_c09_scale', <<<'SQL'
                recognized_qty_base > 0 and recognized_qty_base = round(recognized_qty_base, quantity_scale)
                and exposure_qty_base = round(exposure_qty_base, quantity_scale) and prior_pending_qty_base = round(prior_pending_qty_base, quantity_scale)
                SQL)
            ->check('unattributed_loss_cases_c36_within_exposure', 'prior_pending_qty_base >= 0 and recognized_qty_base <= exposure_qty_base - prior_pending_qty_base')
            ->check('unattributed_loss_cases_c35_counters', implode(' and ', array_map(static fn (string $counter): string => "{$counter} >= 0", self::COUNTERS)))
            ->check('unattributed_loss_cases_c35_found_only_loss', "case_kind = 'LOSS' or found_qty_base = 0")
            ->check('unattributed_loss_cases_c35_pending', 'pending_qty_base >= 0')
            // IX-10 and QS-22: the pending cases of a scope.
            ->index('unattributed_loss_cases_ix10_pending', 'product_id, location_id, condition', 'pending_qty_base > 0')
            ->index('unattributed_loss_cases_group', 'exposure_group_case_id', 'exposure_group_case_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(self::COUNTERS);

        TableRules::for('stock_movements')
            ->foreign('stock_movements_unattributed_case_foreign', ['unattributed_case_id'], 'unattributed_loss_cases', ['id']);

        DB::unprepared(<<<'SQL'
            create or replace function unattributed_loss_cases_not_serialized() returns trigger
            language plpgsql as $$
            begin
                if exists (select from products p where p.id = new.product_id and p.is_serialized) then
                    raise exception 'A serialized product''s loss is never an unattributed case (DATABASE §4.7; L-35).'
                        using errcode = 'check_violation', constraint = 'unattributed_loss_cases_not_serialized';
                end if;

                return new;
            end
            $$;

            create trigger unattributed_loss_cases_not_serialized
                before insert on unattributed_loss_cases
                for each row execute function unattributed_loss_cases_not_serialized();
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_unattributed_case_foreign');
        Schema::drop('unattributed_loss_cases');
        DB::unprepared('drop function if exists unattributed_loss_cases_not_serialized()');
    }
};
