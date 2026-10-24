<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoice_tax_components` (SN once issued, draft-editable) and §12: the output-tax components
// of a version, kept apart from its sales value and never revenue (D-1; CALC-08) — the tax type, the rule
// resolved at the business date, its treatment, rate, base and amount as committed (§14; BR-SN-01). Added,
// edited and deleted while its version is a DRAFT, and deleted with it (§19); frozen once issued (§28).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_tax_components', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('invoice_version_id');
            Columns::reference($table, 'tax_type_id', 'tax_types');
            Columns::reference($table, 'treatment_rule_id', 'tax_treatment_rules');
            $table->text('treatment');
            $table->decimal('rate_percent', 9, 6);
            Columns::money($table, 'base_amount');
            Columns::money($table, 'amount');
            Columns::commandIdentity($table, 'invoice_tax_components');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('invoice_version_id');
        });

        TableRules::for('invoice_tax_components')
            ->foreign('invoice_tax_components_version_foreign', ['invoice_version_id'], 'invoice_versions', ['id'], cascadeDraftDelete: true)
            ->codes('invoice_tax_components_treatment_code', 'treatment', [
                'OUTPUT_EXCLUDED_FROM_REVENUE', 'INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST', 'SETTLEMENT_NO_EXPENSE',
                'SETTLEMENT_TO_EXPENSE', 'REMITTANCE_NOT_COST', 'REMITTANCE_TO_EXPENSE',
            ])
            ->check('invoice_tax_components_values', 'rate_percent >= 0 and base_amount >= 0 and amount >= 0')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate(['tax_type_id', 'treatment_rule_id', 'treatment', 'rate_percent', 'base_amount', 'amount']);

        DB::unprepared(<<<'SQL'
            create or replace function invoice_tax_components_frozen_once_issued() returns trigger
            language plpgsql as $$
            begin
                if exists (
                    select from invoice_versions v
                    where v.id = case when tg_op = 'INSERT' then new.invoice_version_id else old.invoice_version_id end and v.state <> 'DRAFT'
                ) then
                    raise exception 'An invoice''s tax is frozen once its version is issued (DATABASE §4.12, §28).'
                        using errcode = 'check_violation', constraint = 'invoice_tax_components_frozen_once_issued';
                end if;

                return case when tg_op = 'DELETE' then old else new end;
            end
            $$;

            create trigger invoice_tax_components_frozen_once_issued
                before insert or update or delete on invoice_tax_components
                for each row execute function invoice_tax_components_frozen_once_issued();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('invoice_tax_components');
        DB::unprepared('drop function if exists invoice_tax_components_frozen_once_issued()');
    }
};
