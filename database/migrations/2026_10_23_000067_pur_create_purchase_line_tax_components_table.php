<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_line_tax_components` (SN, draft-editable with its line): the D-1 treatment
// recorded on a purchase line — the tax type, the rule applied, its treatment, rate, base and amount as
// committed (§14). Non-creditable tax enters lot cost through SF-CHARGE only (L-43). Added, edited, deleted
// and re-entered only before the line's first effect (§19; L-23), the trigger reading the line's facts as
// 2026_10_23_000066's does.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_line_tax_components', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'purchase_line_id', 'purchase_lines');
            Columns::reference($table, 'tax_type_id', 'tax_types');
            Columns::reference($table, 'treatment_rule_id', 'tax_treatment_rules');
            $table->text('treatment');
            $table->decimal('rate_percent', 9, 6);
            Columns::money($table, 'base_amount');
            Columns::money($table, 'amount');
            Columns::commandIdentity($table, 'purchase_line_tax_components');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('purchase_line_id');
        });

        TableRules::for('purchase_line_tax_components')
            ->codes('purchase_line_tax_components_treatment_code', 'treatment', [
                'OUTPUT_EXCLUDED_FROM_REVENUE', 'INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST', 'SETTLEMENT_NO_EXPENSE',
                'SETTLEMENT_TO_EXPENSE', 'REMITTANCE_NOT_COST', 'REMITTANCE_TO_EXPENSE',
            ])
            ->check('purchase_line_tax_components_values', 'rate_percent >= 0 and base_amount >= 0 and amount >= 0')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate(['tax_type_id', 'treatment_rule_id', 'treatment', 'rate_percent', 'base_amount', 'amount']);

        DB::unprepared(<<<'SQL'
            create or replace function purchase_line_tax_components_draft_until_first_effect() returns trigger
            language plpgsql as $$
            begin
                if purchase_line_has_effect(case when tg_op = 'INSERT' then new.purchase_line_id else old.purchase_line_id end) then
                    raise exception 'A purchase line''s tax is edited only before the line''s first effect (DATABASE §4.6, §19; L-23).'
                        using errcode = 'check_violation', constraint = 'purchase_line_tax_components_draft_until_first_effect';
                end if;

                return case when tg_op = 'DELETE' then old else new end;
            end
            $$;

            create trigger purchase_line_tax_components_draft_until_first_effect
                before insert or update or delete on purchase_line_tax_components
                for each row execute function purchase_line_tax_components_draft_until_first_effect();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('purchase_line_tax_components');
        DB::unprepared('drop function if exists purchase_line_tax_components_draft_until_first_effect()');
    }
};
