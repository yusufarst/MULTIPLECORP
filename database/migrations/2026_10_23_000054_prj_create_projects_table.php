<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `projects` (SR) and §9: one project, one company (OB §4), its number drawn once from the
// company's PROJECT counter and never reused (C-10; NM-08) — the number columns are not updatable, and a
// discarded DRAFT leaves its number in `retired_numbers` (L-37). The client is a live reference,
// snapshotted on the outputs (§14); (`company_id`, `id`, `client_organization_id`) is the key an invoice
// takes its client through. The state changes only with its `project_state_transitions` row (SM:Project).
// A linked project of another company is an intentional cross-company link with its reason (§15). Only a
// DRAFT is deleted (§13, §19), its lines with it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('project_number');
            $table->text('period_key');
            $table->bigInteger('sequence_no');
            Columns::reference($table, 'client_organization_id', 'client_organizations');
            Columns::reference($table, 'client_unit_id', 'client_units')->nullable();
            Columns::reference($table, 'client_contact_id', 'client_contacts')->nullable();
            Columns::reference($table, 'client_address_id', 'client_addresses')->nullable();
            $table->text('channel');
            $table->text('state')->default('DRAFT');
            $table->text('title');
            // DIR-077 CA-10: the project's one governing deadline, if it has one.
            $table->date('deadline')->nullable();
            $table->text('origin')->default('LIVE');
            Columns::reference($table, 'import_row_id', 'import_rows')->nullable()->unique();
            Columns::reference($table, 'linked_project_id', 'projects')->nullable();
            $table->text('link_reason')->nullable();
            Columns::commandIdentity($table, 'projects');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('projects')
            ->unique('projects_c10_number', ['company_id', 'project_number'])
            ->unique('projects_c32_company_key', ['company_id', 'id'])
            // The key an invoice references, so its client is its project's client (§4.5; C-32).
            ->unique('projects_c32_client_key', ['company_id', 'id', 'client_organization_id'])
            ->codes('projects_channel_code', 'channel', ['REGULAR', 'SIPLAH'])
            ->codes('projects_state_code', 'state', ['DRAFT', 'ACTIVE', 'CANCELLED', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'])
            ->codes('projects_origin_code', 'origin', ['LIVE', 'OPENING'])
            ->check('projects_period_key_format', "period_key ~ '^[0-9]{4}(-(0[1-9]|1[0-2]))?$'")
            ->check('projects_linked_project_reason', 'num_nonnulls(linked_project_id, link_reason) in (0, 2) and (linked_project_id is null or linked_project_id <> id)')
            // IX-11 and IX-14: a company's projects by state, by client and its open projects by deadline (PF-42; QS-01, QS-06, QS-15, QS-16, QS-24).
            ->index('projects_ix11_company_state', 'company_id, state, id')
            ->index('projects_ix11_company_client', 'company_id, client_organization_id, id')
            ->index('projects_ix11_open_deadline', 'company_id, deadline, id', "state in ('DRAFT', 'ACTIVE')")
            // IX-16: the first-use proof of a new PROJECT numbering period (FU-).
            ->index('projects_ix16_company_period', 'company_id, period_key')
            // IX-17: a project's title by equality and prefix, led by its company (PF-21).
            ->index('projects_ix17_title_prefix', 'company_id, lower(title) text_pattern_ops')
            ->trigramIndex('projects_title_trigram', 'title')
            ->index('projects_linked_project', 'linked_project_id', 'linked_project_id is not null')
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate([
                'client_organization_id', 'client_unit_id', 'client_contact_id', 'client_address_id', 'channel', 'state', 'title',
                'deadline', 'linked_project_id', 'link_reason', 'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function projects_draft_delete_only() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'DRAFT' then
                    raise exception 'Only a DRAFT project is deleted (DATABASE §13, §19; L-37).'
                        using errcode = 'check_violation', constraint = 'projects_draft_delete_only';
                end if;

                return old;
            end
            $$;

            create trigger projects_draft_delete_only
                before delete on projects
                for each row execute function projects_draft_delete_only();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('projects');
        DB::unprepared('drop function if exists projects_draft_delete_only()');
    }
};
