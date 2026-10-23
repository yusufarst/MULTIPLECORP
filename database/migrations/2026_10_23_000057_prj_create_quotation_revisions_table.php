<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `quotation_revisions` (SR) and §9: one revision chain per project (SM:Quotation), linear by
// the UNIQUE `supersedes_revision_id`; APPROVED and REJECTED are decisions with their actor and time,
// EXPIRED is derived (SENT past validity, L-25), and a revision is never voided (BR-CR-06): it never
// returns to DRAFT, which would unfreeze its lines, and its decision is final (the trigger). DOC-01
// versions render a revision (§10), keyed by 2026_10_23_000082. Only a DRAFT is deleted, its lines with
// it (§13, §19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_revisions', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->integer('revision_no');
            $table->text('state')->default('DRAFT');
            $table->date('valid_until')->nullable();
            Columns::user($table, 'decided_by')->nullable();
            Columns::timestamp($table, 'decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->bigInteger('supersedes_revision_id')->nullable();
            Columns::commandIdentity($table, 'quotation_revisions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('quotation_revisions')
            ->unique('quotation_revisions_revision_no', ['project_id', 'revision_no'])
            ->unique('quotation_revisions_c32_company_key', ['company_id', 'id'])
            ->unique('quotation_revisions_project_key', ['project_id', 'id'])
            ->unique('quotation_revisions_linear_chain', ['supersedes_revision_id'])
            ->foreign('quotation_revisions_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->foreign('quotation_revisions_supersedes_foreign', ['project_id', 'supersedes_revision_id'], 'quotation_revisions', ['project_id', 'id'])
            ->codes('quotation_revisions_state_code', 'state', ['DRAFT', 'SENT', 'APPROVED', 'REJECTED'])
            ->check('quotation_revisions_decision_columns', "num_nonnulls(decided_by, decided_at) in (0, 2) and (state in ('APPROVED', 'REJECTED')) = (decided_at is not null)")
            ->commandIdentity()
            ->grant('select, insert, delete')
            ->grantUpdate(['state', 'valid_until', 'decided_by', 'decided_at', 'decision_reason', 'lock_version', 'updated_at']);

        DB::unprepared(<<<'SQL'
            create or replace function quotation_revisions_draft_delete_only() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'DRAFT' then
                    raise exception 'Only a DRAFT quotation revision is deleted (DATABASE §13, §19).'
                        using errcode = 'check_violation', constraint = 'quotation_revisions_draft_delete_only';
                end if;

                return old;
            end
            $$;

            create trigger quotation_revisions_draft_delete_only
                before delete on quotation_revisions
                for each row execute function quotation_revisions_draft_delete_only();

            -- A revision never returns to DRAFT, which would unfreeze its lines, and a decision is final (SM:Quotation; BR-CR-06).
            create or replace function quotation_revisions_state_forward_only() returns trigger
            language plpgsql as $$
            begin
                if (old.state <> 'DRAFT' and new.state = 'DRAFT')
                    or (old.state in ('APPROVED', 'REJECTED') and new.state <> old.state) then
                    raise exception 'A quotation revision never returns to DRAFT, and its decision is final (DATABASE §4.5; SM:Quotation).'
                        using errcode = 'check_violation', constraint = 'quotation_revisions_state_forward_only';
                end if;

                return new;
            end
            $$;

            create trigger quotation_revisions_state_forward_only
                before update on quotation_revisions
                for each row execute function quotation_revisions_state_forward_only();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('quotation_revisions');
        DB::unprepared('drop function if exists quotation_revisions_draft_delete_only()');
        DB::unprepared('drop function if exists quotation_revisions_state_forward_only()');
    }
};
