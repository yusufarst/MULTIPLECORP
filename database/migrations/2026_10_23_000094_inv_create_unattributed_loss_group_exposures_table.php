<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_group_exposures` (GB) and §5.8: one bound per company across the cases
// pending in one scope at the same time, raised at each member's recognition and never lowered (the
// trigger), so overlapping cases never reuse the same exposure (C-37); later movements never change it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_loss_group_exposures', function (Blueprint $table) {
            Columns::reference($table, 'exposure_group_case_id', 'unattributed_loss_cases');
            Columns::reference($table, 'company_id', 'companies');
            Columns::quantity($table, 'bound_qty_base');
            Columns::quantity($table, 'assigned_net_qty_base')->default(0);
            Columns::recordedAt($table);
            $table->primary(['exposure_group_case_id', 'company_id']);
        });

        TableRules::for('unattributed_loss_group_exposures')
            ->check('unattributed_loss_group_exposures_c37_bound', 'assigned_net_qty_base >= 0 and assigned_net_qty_base <= bound_qty_base')
            ->grant('select, insert')
            ->grantUpdate(['bound_qty_base', 'assigned_net_qty_base']);

        DB::unprepared(<<<'SQL'
            create or replace function unattributed_loss_group_exposures_c37_bound_never_lowered() returns trigger
            language plpgsql as $$
            begin
                if new.bound_qty_base < old.bound_qty_base then
                    raise exception 'An overlap group''s bound is raised, never lowered (DATABASE §4.7, §5.8).'
                        using errcode = 'check_violation', constraint = 'unattributed_loss_group_exposures_c37_bound_never_lowered';
                end if;

                return new;
            end
            $$;

            create trigger unattributed_loss_group_exposures_c37_bound_never_lowered
                before update on unattributed_loss_group_exposures
                for each row execute function unattributed_loss_group_exposures_c37_bound_never_lowered();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('unattributed_loss_group_exposures');
        DB::unprepared('drop function if exists unattributed_loss_group_exposures_c37_bound_never_lowered()');
    }
};
