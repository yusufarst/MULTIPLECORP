<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_movement_entries` (AF) and §5.3: the immutable stock ledger (OB §5; BR-INV-02/08), its
// `id` the ledger order. Each entry copies its header's type through a composite key, so the sign rules are
// database rules: a CHECK for the types that only add or only subtract, and a deferred constraint trigger,
// once per movement at commit, for the net-zero groups, the lot-less signs by the case's kind and a
// MOVEMENT_REVERSAL's exact opposite of the reversed movement's net at each key (C-07, C-55). An entry names
// its lot, or — on the pending-case types and their reversals only — its case, keyed with its product and
// location; a serial moves by one, counted once per sign in a movement (§8; SF-SCAN). Its quantity holds the
// product's scale (§1; C-09). `serial_units.last_entry_id` is keyed here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_entries', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('stock_movement_id');
            $table->text('movement_type');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            $table->bigInteger('lot_id')->nullable();
            $table->bigInteger('unattributed_case_id')->nullable();
            Columns::reference($table, 'serial_unit_id', 'serial_units')->nullable();
            Columns::quantity($table, 'qty_delta_base');
            Columns::reference($table, 'stock_consumption_id', 'stock_consumptions')->nullable();
            Columns::reference($table, 'stock_restoration_id', 'stock_restorations')->nullable();
            Columns::commandIdentity($table, 'stock_movement_entries');
            Columns::recordedAt($table);
        });

        TableRules::for('stock_movement_entries')
            ->foreign('stock_movement_entries_c07_movement_type_foreign', ['stock_movement_id', 'movement_type'], 'stock_movements', ['id', 'movement_type'])
            ->foreign('stock_movement_entries_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->foreign('stock_movement_entries_lot_foreign', ['lot_id', 'product_id'], 'inventory_lots', ['id', 'product_id'])
            ->foreign('stock_movement_entries_case_foreign', ['unattributed_case_id', 'product_id', 'location_id'], 'unattributed_loss_cases', ['id', 'product_id', 'location_id'])
            ->codes('stock_movement_entries_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('stock_movement_entries_c09_quantity', 'qty_delta_base <> 0 and qty_delta_base = round(qty_delta_base, quantity_scale)')
            ->check('stock_movement_entries_c07_lot_or_case', '(lot_id is null) = (unattributed_case_id is not null)')
            ->check('stock_movement_entries_c07_lot_less_types', "lot_id is not null or movement_type in ('UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_RESOLUTION', 'UNATTRIBUTED_RESOLUTION_REVERSAL', 'UNATTRIBUTED_FOUND', 'MOVEMENT_REVERSAL')")
            ->check('stock_movement_entries_c02_serial_one', 'serial_unit_id is null or abs(qty_delta_base) = 1')
            ->check('stock_movement_entries_c07_adds', "movement_type not in ('RECEIPT', 'OPENING', 'DROPSHIP_RETURN_RECEIPT', 'SURPLUS_ATTRIBUTION', 'RESTORATION', 'UNATTRIBUTED_FOUND') or qty_delta_base > 0")
            ->check('stock_movement_entries_c07_subtracts', "movement_type not in ('DISPATCH', 'PURCHASE_RETURN', 'LOSS', 'ADJUSTMENT_DECREASE', 'COUNT_VARIANCE', 'RECEIPT_REVERSAL') or qty_delta_base < 0")
            // §8: a repeated scan in one command is not counted twice.
            ->uniqueIndex('stock_movement_entries_c02_serial_once', 'stock_movement_id, serial_unit_id, sign(qty_delta_base)', 'serial_unit_id is not null')
            // §20 and IX-16: a lot's, a product's, a serial's and a case's entries; a scope's first use.
            ->index('stock_movement_entries_lot', 'lot_id, id', 'lot_id is not null')
            ->index('stock_movement_entries_product', 'product_id, id')
            ->index('stock_movement_entries_serial', 'serial_unit_id, id', 'serial_unit_id is not null')
            ->index('stock_movement_entries_case', 'unattributed_case_id', 'unattributed_case_id is not null')
            ->index('stock_movement_entries_ix16_scope', 'product_id, location_id, condition')
            ->index('stock_movement_entries_movement', 'stock_movement_id')
            ->index('stock_movement_entries_consumption', 'stock_consumption_id', 'stock_consumption_id is not null')
            ->index('stock_movement_entries_restoration', 'stock_restoration_id', 'stock_restoration_id is not null')
            ->commandIdentity()
            ->grant('select, insert');

        TableRules::for('serial_units')
            ->foreign('serial_units_last_entry_foreign', ['last_entry_id'], 'stock_movement_entries', ['id']);

        DB::unprepared(<<<'SQL'
            create or replace function stock_movement_entries_c07_movement_balanced() returns trigger
            language plpgsql as $$
            declare
                movement stock_movements%rowtype;
                case_kind text;
                broken boolean := false;
            begin
                select * into movement from stock_movements where id = new.stock_movement_id;

                if movement.movement_type = 'LOCATION_MOVE' then
                    broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.lot_id, e.condition having sum(e.qty_delta_base) <> 0);
                elsif movement.movement_type = 'CONDITION_CHANGE' then
                    broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.lot_id, e.location_id having sum(e.qty_delta_base) <> 0);
                elsif movement.movement_type = 'IDENTITY_SUBSTITUTION' then
                    broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.product_id having sum(e.qty_delta_base) <> 0);
                elsif movement.movement_type in ('UNATTRIBUTED_RESOLUTION', 'UNATTRIBUTED_RESOLUTION_REVERSAL') then
                    broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.product_id, e.location_id, e.condition having sum(e.qty_delta_base) <> 0);
                elsif movement.movement_type = 'UNATTRIBUTED_RECOGNITION' then
                    select k.case_kind into case_kind from unattributed_loss_cases k where k.id = movement.unattributed_case_id;
                    if case_kind = 'CONDITION_CHANGE' then
                        broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.product_id, e.location_id having sum(e.qty_delta_base) <> 0);
                    else
                        broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id and e.qty_delta_base > 0);
                    end if;
                elsif movement.movement_type = 'UNATTRIBUTED_CONVERSION' then
                    broken := exists (
                        select from stock_movement_entries e where e.stock_movement_id = movement.id
                        group by e.product_id, e.location_id
                        having sum(e.qty_delta_base) filter (where e.condition = 'USABLE') is distinct from 0
                            or coalesce(sum(e.qty_delta_base) filter (where e.condition = 'UNUSABLE'), 0)
                                <> -coalesce(sum(e.qty_delta_base) filter (where e.condition = 'USABLE' and e.qty_delta_base > 0), 0)
                    );
                elsif movement.movement_type = 'MOVEMENT_REVERSAL' then
                    -- Each entry opposes the reversed movement's net at its key: lot, location and condition, or case, location and condition.
                    broken := exists (
                        select from stock_movement_entries e
                        where e.stock_movement_id = movement.id and coalesce(sign((
                            select sum(o.qty_delta_base) from stock_movement_entries o
                            where o.stock_movement_id = movement.reverses_movement_id and o.location_id = e.location_id and o.condition = e.condition
                                and o.lot_id is not distinct from e.lot_id and o.unattributed_case_id is not distinct from e.unattributed_case_id
                        )), 0) <> -sign(e.qty_delta_base)
                    );
                end if;

                -- The lot-less signs by the case's kind: its recognition − in the case's own condition and, for a
                -- condition change only, + in the other; a resolution, a finding and a conversion's entries under the
                -- old case clear them with the opposite sign, a resolution's reversal re-opens them (§5.3; §5.8).
                if movement.movement_type in ('UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_FOUND', 'UNATTRIBUTED_RESOLUTION', 'UNATTRIBUTED_RESOLUTION_REVERSAL') then
                    broken := broken or exists (
                        select from stock_movement_entries e join unattributed_loss_cases k on k.id = e.unattributed_case_id
                        where e.stock_movement_id = movement.id and sign(e.qty_delta_base) is distinct from
                            case when e.condition = k.condition then -1 when k.case_kind = 'CONDITION_CHANGE' then 1 end
                            * case when movement.movement_type in ('UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_RESOLUTION_REVERSAL')
                                or (movement.movement_type = 'UNATTRIBUTED_CONVERSION' and e.unattributed_case_id = movement.unattributed_case_id) then 1 else -1 end
                    );
                end if;

                if broken then
                    raise exception 'A movement''s entries keep its type''s balance and signs (DATABASE §5.3; C-07).'
                        using errcode = 'check_violation', constraint = 'stock_movement_entries_c07_movement_balanced';
                end if;

                return null;
            end
            $$;

            create constraint trigger stock_movement_entries_c07_movement_balanced
                after insert on stock_movement_entries
                deferrable initially deferred
                for each row execute function stock_movement_entries_c07_movement_balanced();
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table serial_units drop constraint serial_units_last_entry_foreign');
        Schema::drop('stock_movement_entries');
        DB::unprepared('drop function if exists stock_movement_entries_c07_movement_balanced()');
    }
};
