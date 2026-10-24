<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoice_versions` (SR, immutable once issued) and §13's REVISION: a new version per change,
// a linear chain within its invoice, the invoice's current pointer moved with it (BR-CR-06). Sales value
// excludes output tax and gross is their sum (BR-FIN-02/17; CALC-08); the client is snapshotted as used
// (§14). A version whose gross is below its predecessor's states ENTRY_ERROR, RETURN, RECONFIRMATION or
// CANCELLATION with exactly that basis's reference, a row of its invoice's company or project (CM-24; C-56).
// A DRAFT is edited and deleted, its lines with it (§19); an issued version only becomes SUPERSEDED or
// VOIDED. `invoices.current_version_id` is keyed here, to a version of its own invoice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_versions', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('invoice_id');
            $table->bigInteger('company_id');
            $table->integer('version_no');
            $table->text('state')->default('DRAFT');
            $table->text('kind');
            Columns::businessDate($table);
            Columns::money($table, 'sales_value');
            Columns::money($table, 'output_tax_value');
            Columns::money($table, 'gross_value')->storedAs('sales_value + output_tax_value');
            // The organization, unit, address and PIC as used (§14; AC-02).
            $table->jsonb('client_snapshot');
            $table->text('revision_reason')->nullable();
            $table->text('revision_basis');
            $table->bigInteger('basis_correction_case_id')->nullable();
            $table->bigInteger('basis_confirmation_id')->nullable();
            $table->bigInteger('basis_cancellation_id')->nullable();
            Columns::reference($table, 'basis_project_transition_id', 'project_state_transitions')->nullable();
            $table->bigInteger('supersedes_version_id')->nullable()->unique();
            Columns::commandIdentity($table, 'invoice_versions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('invoice_versions')
            ->unique('invoice_versions_c32_company_key', ['company_id', 'id'])
            ->unique('invoice_versions_chain_key', ['invoice_id', 'id'])
            ->unique('invoice_versions_number', ['invoice_id', 'version_no'])
            ->foreign('invoice_versions_c32_invoice_foreign', ['company_id', 'invoice_id'], 'invoices', ['company_id', 'id'])
            ->foreign('invoice_versions_linear_chain_foreign', ['invoice_id', 'supersedes_version_id'], 'invoice_versions', ['invoice_id', 'id'])
            ->foreign('invoice_versions_c56_case_foreign', ['company_id', 'basis_correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->foreign('invoice_versions_c56_confirmation_foreign', ['company_id', 'basis_confirmation_id'], 'commercial_confirmations', ['company_id', 'id'])
            ->foreign('invoice_versions_c56_cancellation_foreign', ['company_id', 'basis_cancellation_id'], 'line_scope_cancellations', ['company_id', 'id'])
            ->codes('invoice_versions_state_code', 'state', ['DRAFT', 'ISSUED', 'SUPERSEDED', 'VOIDED'])
            ->codes('invoice_versions_kind_code', 'kind', ['ITEMIZED', 'VALUE_ONLY'])
            ->codes('invoice_versions_revision_basis_code', 'revision_basis', ['INITIAL', 'UPWARD', 'ENTRY_ERROR', 'RETURN', 'RECONFIRMATION', 'CANCELLATION'])
            ->check('invoice_versions_values', 'version_no >= 1 and sales_value >= 0 and output_tax_value >= 0')
            ->check('invoice_versions_client_snapshot_object', "jsonb_typeof(client_snapshot) = 'object'")
            ->check('invoice_versions_initial_first', "(revision_basis = 'INITIAL') = (supersedes_version_id is null)")
            ->check('invoice_versions_c56_basis_reference', <<<'SQL'
                case revision_basis
                    when 'RETURN' then basis_correction_case_id is not null and num_nonnulls(basis_confirmation_id, basis_cancellation_id, basis_project_transition_id) = 0
                    when 'RECONFIRMATION' then basis_confirmation_id is not null and num_nonnulls(basis_correction_case_id, basis_cancellation_id, basis_project_transition_id) = 0
                    when 'CANCELLATION' then num_nonnulls(basis_cancellation_id, basis_project_transition_id) = 1 and num_nonnulls(basis_correction_case_id, basis_confirmation_id) = 0
                    else num_nonnulls(basis_correction_case_id, basis_confirmation_id, basis_cancellation_id, basis_project_transition_id) = 0
                end
                SQL)
            // §20: company and date reporting.
            ->index('invoice_versions_company_date', 'company_id, business_date')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate([
                'state', 'kind', 'business_date', 'sales_value', 'output_tax_value', 'client_snapshot', 'revision_reason', 'revision_basis',
                'basis_correction_case_id', 'basis_confirmation_id', 'basis_cancellation_id', 'basis_project_transition_id', 'lock_version', 'updated_at',
            ]);

        TableRules::for('invoices')
            ->foreign('invoices_current_version_foreign', ['id', 'current_version_id'], 'invoice_versions', ['invoice_id', 'id']);

        DB::unprepared(<<<'SQL'
            create or replace function invoice_versions_c56_downward_basis() returns trigger
            language plpgsql as $$
            declare
                project bigint;
            begin
                select i.project_id into project from invoices i where i.id = new.invoice_id;

                if (new.basis_confirmation_id is not null and not exists (select from commercial_confirmations c where c.id = new.basis_confirmation_id and c.project_id = project))
                    or (new.basis_cancellation_id is not null and not exists (
                        select from line_scope_cancellations c join project_lines l on l.id = c.project_line_id where c.id = new.basis_cancellation_id and l.project_id = project
                    ))
                    or (new.basis_project_transition_id is not null
                        and not exists (select from project_state_transitions t where t.id = new.basis_project_transition_id and t.project_id = project and t.transition_kind = 'CANCEL'))
                    or (new.revision_basis not in ('ENTRY_ERROR', 'RETURN', 'RECONFIRMATION', 'CANCELLATION')
                        and exists (select from invoice_versions p where p.id = new.supersedes_version_id and p.gross_value > new.sales_value + new.output_tax_value)) then
                    raise exception 'A downward revision states its typed basis, a confirmation or CANCEL transition of its own project (DATABASE §4.12; CM-24; C-56).'
                        using errcode = 'check_violation', constraint = 'invoice_versions_c56_downward_basis';
                end if;

                return new;
            end
            $$;

            create trigger invoice_versions_c56_downward_basis
                before insert or update on invoice_versions
                for each row execute function invoice_versions_c56_downward_basis();

            create or replace function invoice_versions_draft_delete_only() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'DRAFT' then
                    raise exception 'Only a DRAFT invoice version is deleted (DATABASE §19; L-37).'
                        using errcode = 'check_violation', constraint = 'invoice_versions_draft_delete_only';
                end if;

                return old;
            end
            $$;

            create trigger invoice_versions_draft_delete_only
                before delete on invoice_versions
                for each row execute function invoice_versions_draft_delete_only();

            create or replace function invoice_versions_immutable_once_issued() returns trigger
            language plpgsql as $$
            begin
                -- A BEFORE trigger sees the generated gross before PostgreSQL computes it; the stale-edit columns move with any update.
                if (old.state = 'DRAFT' and new.state not in ('DRAFT', 'ISSUED'))
                    or (old.state = 'ISSUED' and new.state not in ('ISSUED', 'SUPERSEDED', 'VOIDED'))
                    or (old.state in ('SUPERSEDED', 'VOIDED') and new.state <> old.state)
                    or (old.state <> 'DRAFT' and (to_jsonb(new) - array['state', 'gross_value', 'lock_version', 'updated_at']) is distinct from (to_jsonb(old) - array['state', 'gross_value', 'lock_version', 'updated_at'])) then
                    raise exception 'An issued invoice version is immutable but for its supersession or void (DATABASE §4.12, §13; BR-CR-06).'
                        using errcode = 'check_violation', constraint = 'invoice_versions_immutable_once_issued';
                end if;

                return new;
            end
            $$;

            create trigger invoice_versions_immutable_once_issued
                before update on invoice_versions
                for each row execute function invoice_versions_immutable_once_issued();
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table invoices drop constraint invoices_current_version_foreign');
        Schema::drop('invoice_versions');
        DB::unprepared('drop function if exists invoice_versions_c56_downward_basis(); drop function if exists invoice_versions_draft_delete_only(); drop function if exists invoice_versions_immutable_once_issued()');
    }
};
