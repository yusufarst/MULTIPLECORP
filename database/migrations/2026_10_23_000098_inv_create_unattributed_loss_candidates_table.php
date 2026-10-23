<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_candidates` (SN) and §5.8: the immutable recognition snapshot — each lot
// holding claims in the scope, its company and its net unit cost (DIR-027 items 3 and 8). A deferred
// constraint trigger, on the case, the candidates and the exposure rows, keeps the candidates' sum equal to the
// case's fixed exposure and each company's sum equal to its exposure row, so neither is inserted outside
// the recognition (or conversion) transaction that writes them together (C-36).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_loss_candidates', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'unattributed_loss_case_id', 'unattributed_loss_cases');
            Columns::reference($table, 'candidate_company_id', 'companies');
            $table->bigInteger('lot_id');
            Columns::quantity($table, 'qty_in_scope_at_recognition');
            Columns::money($table, 'net_unit_cost_at_recognition');
            Columns::recordedAt($table);
        });

        TableRules::for('unattributed_loss_candidates')
            ->unique('unattributed_loss_candidates_one_per_lot', ['unattributed_loss_case_id', 'lot_id'])
            ->foreign('unattributed_loss_candidates_c28_lot_foreign', ['lot_id', 'candidate_company_id'], 'inventory_lots', ['id', 'source_company_id'])
            ->check('unattributed_loss_candidates_values', 'qty_in_scope_at_recognition > 0 and net_unit_cost_at_recognition >= 0')
            ->index('unattributed_loss_candidates_lot', 'lot_id')
            ->grant('select, insert');

        DB::unprepared(<<<'SQL'
            create or replace function unattributed_loss_candidates_c36_snapshot_sums() returns trigger
            language plpgsql as $$
            declare
                case_id bigint;
            begin
                -- PL/pgSQL compiles the function once per table, so each branch reads only its own table's row.
                if tg_table_name = 'unattributed_loss_cases' then
                    case_id := new.id;
                else
                    case_id := new.unattributed_loss_case_id;
                end if;

                if (select coalesce(sum(c.qty_in_scope_at_recognition), 0) from unattributed_loss_candidates c where c.unattributed_loss_case_id = case_id)
                        is distinct from (select k.exposure_qty_base from unattributed_loss_cases k where k.id = case_id)
                    or exists (
                        select from (
                            select c.candidate_company_id, sum(c.qty_in_scope_at_recognition) as qty
                            from unattributed_loss_candidates c where c.unattributed_loss_case_id = case_id group by c.candidate_company_id
                        ) s
                        full join (select e.company_id, e.exposure_qty_base from unattributed_loss_exposures e where e.unattributed_loss_case_id = case_id) x
                            on x.company_id = s.candidate_company_id
                        where x.exposure_qty_base is distinct from s.qty
                    ) then
                    raise exception 'A case''s candidates sum to its exposure and each company''s to its exposure row, written together at recognition (DATABASE §4.7, §5.8; C-36).'
                        using errcode = 'check_violation', constraint = 'unattributed_loss_candidates_c36_snapshot_sums';
                end if;

                return null;
            end
            $$;

            create constraint trigger unattributed_loss_candidates_c36_snapshot_sums
                after insert on unattributed_loss_candidates
                deferrable initially deferred
                for each row execute function unattributed_loss_candidates_c36_snapshot_sums();

            create constraint trigger unattributed_loss_exposures_c36_snapshot_sums
                after insert on unattributed_loss_exposures
                deferrable initially deferred
                for each row execute function unattributed_loss_candidates_c36_snapshot_sums();

            -- A case recognized with no snapshot at all.
            create constraint trigger unattributed_loss_cases_c36_snapshot_sums
                after insert on unattributed_loss_cases
                deferrable initially deferred
                for each row execute function unattributed_loss_candidates_c36_snapshot_sums();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger unattributed_loss_cases_c36_snapshot_sums on unattributed_loss_cases');
        DB::unprepared('drop trigger unattributed_loss_exposures_c36_snapshot_sums on unattributed_loss_exposures');
        Schema::drop('unattributed_loss_candidates');
        DB::unprepared('drop function if exists unattributed_loss_candidates_c36_snapshot_sums()');
    }
};
