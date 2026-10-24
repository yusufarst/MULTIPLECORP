<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoices` (SR) and §11.2: the single sales-value record rendered as DOC-06 or DOC-04 (L-26),
// a project invoice's client its project's (C-32), an opening receivable of origin OPENING (BR-FIN-14). Its
// state moves forward only; a void states its typed basis with exactly that basis's reference, each a row of
// its company or of its project (CM-24; C-56): the return's case, the re-confirmation, the cancellation or
// the CANCEL transition. An entry-error void commits only together with the replacement that names it, an
// unbilled one only while it has no billing act (CM-23; L-30). `line_scope_cancellations` gains its company
// key (§1) and `project_state_transitions` its project key, which a void basis cites.
return new class extends Migration
{
    private const BASES = ['RETURN', 'RECONFIRMATION', 'CANCELLATION', 'ENTRY_ERROR_REPLACED', 'UNBILLED_ENTRY_ERROR'];

    public function up(): void
    {
        TableRules::for('line_scope_cancellations')->unique('line_scope_cancellations_c32_company_key', ['company_id', 'id']);
        TableRules::for('project_state_transitions')->unique('project_state_transitions_project_key', ['project_id', 'id']);

        Schema::create('invoices', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('project_id')->nullable();
            Columns::reference($table, 'client_organization_id', 'client_organizations');
            $table->text('origin')->default('LIVE');
            $table->bigInteger('import_row_id')->nullable()->unique();
            $table->text('aging_basis')->default('DUE_DATE');
            $table->text('state')->default('DRAFT');
            $table->bigInteger('current_version_id')->nullable();
            $table->bigInteger('replaces_invoice_id')->nullable()->unique();
            Columns::user($table, 'voided_by')->nullable();
            Columns::timestamp($table, 'voided_at')->nullable();
            $table->text('voided_reason')->nullable();
            $table->text('void_basis')->nullable();
            $table->bigInteger('void_correction_case_id')->nullable();
            $table->bigInteger('void_confirmation_id')->nullable();
            $table->bigInteger('void_cancellation_id')->nullable();
            $table->bigInteger('void_project_transition_id')->nullable();
            Columns::commandIdentity($table, 'invoices');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('invoices')
            ->unique('invoices_c32_company_key', ['company_id', 'id'])
            ->unique('invoices_c14_client_key', ['company_id', 'client_organization_id', 'id'])
            // The key an inherited billing act cites the invoice it replaces through (C-17; L-30).
            ->unique('invoices_c17_replacement_key', ['id', 'replaces_invoice_id'])
            ->foreign('invoices_c32_project_client_foreign', ['company_id', 'project_id', 'client_organization_id'], 'projects', ['company_id', 'id', 'client_organization_id'])
            ->foreign('invoices_c47_import_row_foreign', ['import_row_id', 'company_id'], 'import_rows', ['id', 'company_id'])
            ->foreign('invoices_c17_replaces_foreign', ['company_id', 'client_organization_id', 'replaces_invoice_id'], 'invoices', ['company_id', 'client_organization_id', 'id'])
            ->foreign('invoices_c56_void_case_foreign', ['company_id', 'void_correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->foreign('invoices_c56_void_confirmation_foreign', ['project_id', 'void_confirmation_id'], 'commercial_confirmations', ['project_id', 'id'])
            ->foreign('invoices_c56_void_cancellation_foreign', ['company_id', 'void_cancellation_id'], 'line_scope_cancellations', ['company_id', 'id'])
            ->foreign('invoices_c56_void_transition_foreign', ['project_id', 'void_project_transition_id'], 'project_state_transitions', ['project_id', 'id'])
            ->codes('invoices_origin_code', 'origin', ['LIVE', 'OPENING'])
            ->codes('invoices_aging_basis_code', 'aging_basis', ['DUE_DATE', 'MIGRASI'])
            ->codes('invoices_state_code', 'state', ['DRAFT', 'ISSUED', 'VOIDED'])
            ->codes('invoices_void_basis_code', 'void_basis', self::BASES, nullable: true)
            ->check('invoices_project_unless_opening', "origin = 'OPENING' or project_id is not null")
            ->check('invoices_c47_opening_import_row', "(origin = 'OPENING') = (import_row_id is not null)")
            ->check('invoices_void_columns', "num_nonnulls(voided_by, voided_at, voided_reason) in (0, 3) and (state = 'VOIDED') = (voided_at is not null)")
            ->check('invoices_c56_void_basis', "(state = 'VOIDED') = (void_basis is not null)")
            // A confirmation, cancellation or transition basis is of the invoice's project, which an opening invoice may lack (§15).
            ->check('invoices_c56_project_basis', 'project_id is not null or num_nonnulls(void_confirmation_id, void_cancellation_id, void_project_transition_id) = 0')
            ->check('invoices_c56_void_basis_reference', <<<'SQL'
                case void_basis
                    when 'RETURN' then void_correction_case_id is not null and num_nonnulls(void_confirmation_id, void_cancellation_id, void_project_transition_id) = 0
                    when 'RECONFIRMATION' then void_confirmation_id is not null and num_nonnulls(void_correction_case_id, void_cancellation_id, void_project_transition_id) = 0
                    when 'CANCELLATION' then num_nonnulls(void_cancellation_id, void_project_transition_id) = 1 and num_nonnulls(void_correction_case_id, void_confirmation_id) = 0
                    else num_nonnulls(void_correction_case_id, void_confirmation_id, void_cancellation_id, void_project_transition_id) = 0
                end
                SQL)
            ->index('invoices_project', 'project_id', 'project_id is not null')
            ->index('invoices_client', 'company_id, client_organization_id')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'state', 'current_version_id', 'voided_by', 'voided_at', 'voided_reason', 'void_basis', 'void_correction_case_id',
                'void_confirmation_id', 'void_cancellation_id', 'void_project_transition_id', 'lock_version', 'updated_at',
            ]);

        // PL/pgSQL resolves the billing acts of the later migration when it runs.
        DB::unprepared(<<<'SQL'
            create or replace function invoices_forward_only() returns trigger
            language plpgsql as $$
            begin
                if tg_op = 'INSERT' then
                    if new.state = 'VOIDED' then
                        raise exception 'An invoice is voided only from an existing one (DATABASE §4.12; L-26).'
                            using errcode = 'check_violation', constraint = 'invoices_forward_only';
                    end if;

                    return new;
                end if;

                if array_position(array['DRAFT', 'ISSUED', 'VOIDED'], new.state) < array_position(array['DRAFT', 'ISSUED', 'VOIDED'], old.state)
                    or (old.state = 'VOIDED' and (to_jsonb(new) - array['lock_version', 'updated_at']) is distinct from (to_jsonb(old) - array['lock_version', 'updated_at'])) then
                    raise exception 'An invoice moves forward only and a void is final (DATABASE §4.12; L-26).'
                        using errcode = 'check_violation', constraint = 'invoices_forward_only';
                end if;

                return new;
            end
            $$;

            create trigger invoices_forward_only
                before insert or update on invoices
                for each row execute function invoices_forward_only();

            create or replace function invoices_c56_void_basis_exists() returns trigger
            language plpgsql as $$
            begin
                if new.state = 'VOIDED' and old.state <> 'VOIDED' and (
                    (new.void_basis = 'UNBILLED_ENTRY_ERROR' and exists (select from invoice_billing_acts a where a.invoice_id = new.id))
                    or (new.void_project_transition_id is not null
                        and not exists (select from project_state_transitions t where t.id = new.void_project_transition_id and t.transition_kind = 'CANCEL'))
                    or (new.void_cancellation_id is not null and not exists (
                        select from line_scope_cancellations c join project_lines l on l.id = c.project_line_id
                        where c.id = new.void_cancellation_id and l.project_id = new.project_id
                    ))
                ) then
                    raise exception 'An unbilled void needs an invoice never billed, a cancellation void its project''s cancellation or CANCEL transition (DATABASE §4.12; CM-23, CM-24; C-56).'
                        using errcode = 'check_violation', constraint = 'invoices_c56_void_basis_exists';
                end if;

                return new;
            end
            $$;

            create trigger invoices_c56_void_basis_exists
                before update on invoices
                for each row execute function invoices_c56_void_basis_exists();

            create or replace function invoices_c56_replaced_with_replacement() returns trigger
            language plpgsql as $$
            begin
                if not exists (select from invoices r where r.replaces_invoice_id = new.id) then
                    raise exception 'An entry-error void commits only with the replacement that names it (DATABASE §4.12, §11.2; C-56).'
                        using errcode = 'check_violation', constraint = 'invoices_c56_replaced_with_replacement';
                end if;

                return null;
            end
            $$;

            create constraint trigger invoices_c56_replaced_with_replacement
                after update on invoices
                deferrable initially deferred
                for each row when (new.void_basis = 'ENTRY_ERROR_REPLACED' and old.void_basis is null)
                execute function invoices_c56_replaced_with_replacement();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('invoices');
        DB::unprepared('drop function if exists invoices_forward_only(); drop function if exists invoices_c56_void_basis_exists(); drop function if exists invoices_c56_replaced_with_replacement()');
        DB::statement('alter table project_state_transitions drop constraint project_state_transitions_project_key');
        DB::statement('alter table line_scope_cancellations drop constraint line_scope_cancellations_c32_company_key');
    }
};
