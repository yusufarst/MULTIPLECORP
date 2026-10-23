<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `reservations` (SR) and §5.5: a project line's claim on usable supply (SM:Reservation;
// BR-RSV-01–04), its line's company, product and scale (§1; C-09, C-32): never negative, one ACTIVE per line,
// an ACTIVE one never empty (C-05). A RELEASED or CONSUMED reservation is never reactivated (L-07; the
// trigger); its remaining quantity is the sum of its events (§3.1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_line_id');
            $table->bigInteger('company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->text('state')->default('ACTIVE');
            Columns::quantity($table, 'remaining_qty_base');
            Columns::commandIdentity($table, 'reservations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('reservations')
            // The key a dispatch line consumes its own line's reservation through (§4.7).
            ->unique('reservations_line_key', ['id', 'project_line_id'])
            ->foreign('reservations_c09_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->codes('reservations_state_code', 'state', ['ACTIVE', 'RELEASED', 'CONSUMED'])
            ->check('reservations_c05_remaining', 'remaining_qty_base >= 0 and remaining_qty_base = round(remaining_qty_base, quantity_scale)')
            ->check('reservations_c05_active_not_empty', "state <> 'ACTIVE' or remaining_qty_base > 0")
            ->uniqueIndex('reservations_c05_one_active_per_line', 'project_line_id', "state = 'ACTIVE'")
            // §20: a product's active reservations.
            ->index('reservations_active_product', 'product_id', "state = 'ACTIVE'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'remaining_qty_base', 'lock_version', 'updated_at']);

        DB::unprepared(<<<'SQL'
            create or replace function reservations_c05_ended_once() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'ACTIVE' and (new.state <> old.state or new.remaining_qty_base <> old.remaining_qty_base) then
                    raise exception 'A released or consumed reservation is never reactivated nor changed (DATABASE §4.7; L-07).'
                        using errcode = 'check_violation', constraint = 'reservations_c05_ended_once';
                end if;

                return new;
            end
            $$;

            create trigger reservations_c05_ended_once
                before update on reservations
                for each row execute function reservations_c05_ended_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('reservations');
        DB::unprepared('drop function if exists reservations_c05_ended_once()');
    }
};
