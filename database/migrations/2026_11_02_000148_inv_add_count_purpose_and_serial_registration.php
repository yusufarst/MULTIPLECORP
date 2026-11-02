<?php

use App\Support\Database\DatabaseRole;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// `DIR-101` F2 (DATABASE §4.7, §5.3): the registration count. Expand-only: a purpose on the count, whose location and
// condition are NULL on a registration count alone; a lot on its lines, NULL except there; the movement type
// SERIAL_REGISTRATION in its CHECK and in the net-zero groups, per (lot, location, condition).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->text('purpose')->default('ORDINARY');
            $table->bigInteger('location_id')->nullable()->change();
            $table->text('condition')->nullable()->change();
        });

        DB::statement('alter table stock_counts drop constraint stock_counts_condition_code');

        TableRules::for('stock_counts')
            ->codes('stock_counts_condition_code', 'condition', ['USABLE', 'UNUSABLE'], true)
            ->codes('stock_counts_purpose_code', 'purpose', ['ORDINARY', 'SERIAL_REGISTRATION'])
            ->check('stock_counts_scope_by_purpose', "(purpose = 'ORDINARY' and location_id is not null and condition is not null) or (purpose = 'SERIAL_REGISTRATION' and location_id is null and condition is null and cardinality(product_ids) = 1)");

        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->bigInteger('lot_id')->nullable();
        });

        TableRules::for('stock_count_lines')
            ->foreign('stock_count_lines_lot_foreign', ['lot_id', 'product_id'], 'inventory_lots', ['id', 'product_id']);

        DB::statement('alter table stock_movements drop constraint stock_movements_type_code');

        TableRules::for('stock_movements')
            ->codes('stock_movements_type_code', 'movement_type', [
                'RECEIPT', 'RECEIPT_REVERSAL', 'DISPATCH', 'RESTORATION', 'IDENTITY_SUBSTITUTION', 'PURCHASE_RETURN', 'CONDITION_CHANGE',
                'LOCATION_MOVE', 'ADJUSTMENT_DECREASE', 'COUNT_VARIANCE', 'LOSS', 'OPENING', 'DROPSHIP_RETURN_RECEIPT', 'SURPLUS_ATTRIBUTION',
                'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_RESOLUTION', 'UNATTRIBUTED_RESOLUTION_REVERSAL',
                'UNATTRIBUTED_FOUND', 'MOVEMENT_REVERSAL', 'SERIAL_REGISTRATION',
            ]);

        // A case's recognition writes the header and the case in one statement, each naming the other's identity (§5.8; C-07).
        DB::statement('grant usage on sequence stock_movements_id_seq, unattributed_loss_cases_id_seq to '.DatabaseRole::RUNTIME);

        DB::unprepared(<<<'SQL'
            create or replace function stock_count_scopes_within_count() returns trigger
            language plpgsql as $$
            begin
                if not exists (
                    select from stock_counts c where c.id = new.stock_count_id
                        and (c.purpose = 'SERIAL_REGISTRATION' or (c.location_id = new.location_id and c.condition = new.condition))
                        and (coalesce(cardinality(c.product_ids), 0) = 0 or new.product_id = any (c.product_ids))
                ) then
                    raise exception 'A row is held to its parent''s product, scope and case side (DATABASE §4.7, §17; DIR-091).'
                        using errcode = 'check_violation', constraint = 'stock_count_scopes_within_count';
                end if;

                return null;
            end
            $$;

            create or replace function stock_count_lines_lot_on_registration() returns trigger
            language plpgsql as $$
            begin
                if (new.lot_id is not null) is distinct from exists (select from stock_counts c where c.id = new.stock_count_id and c.purpose = 'SERIAL_REGISTRATION') then
                    raise exception 'A line names its lot on a registration count, and only there (DATABASE §4.7; DIR-101).'
                        using errcode = 'check_violation', constraint = 'stock_count_lines_lot_on_registration';
                end if;

                return new;
            end
            $$;

            create trigger stock_count_lines_lot_on_registration
                before insert on stock_count_lines
                for each row execute function stock_count_lines_lot_on_registration();
            SQL);

        // The net-zero groups gain SERIAL_REGISTRATION per (lot, location, condition); the rest of the function is 000103's (`DIR-101`).
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
                elsif movement.movement_type = 'SERIAL_REGISTRATION' then
                    broken := exists (select from stock_movement_entries e where e.stock_movement_id = movement.id group by e.lot_id, e.location_id, e.condition having sum(e.qty_delta_base) <> 0);
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
            SQL);
    }

    public function down(): void
    {
        // Expand-only: the amended trigger and CHECKs stay valid without a registration count (DIR-101; PRODUCTION_DATA_SAFETY).
    }
};
