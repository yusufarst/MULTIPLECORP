<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.11 `admin_requirement_links` (SR, unlink set once): what satisfies a requirement — a document
// version or an evidence of the requirement's company, an exclusive arc (D-DB-10; §15; C-32). A requirement
// for a client original accepts only an evidence link, never a company draft or rendition (§10; FS-12),
// which the trigger checks after the keys, and a requirement linked to a document version does not
// become one until that link is unlinked. A link is unlinked once, with reason and actor, and changes
// nothing else (§19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_requirement_links', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('admin_requirement_id');
            $table->bigInteger('company_id');
            $table->bigInteger('document_version_id')->nullable();
            $table->bigInteger('evidence_document_id')->nullable();
            Columns::user($table, 'linked_by');
            Columns::timestamp($table, 'unlinked_at')->nullable();
            Columns::user($table, 'unlinked_by')->nullable();
            $table->text('unlink_reason')->nullable();
            Columns::commandIdentity($table, 'admin_requirement_links');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('admin_requirement_id');
        });

        TableRules::for('admin_requirement_links')
            ->foreign('admin_requirement_links_c32_requirement_foreign', ['company_id', 'admin_requirement_id'], 'admin_requirements', ['company_id', 'id'])
            ->foreign('admin_requirement_links_c32_document_version_foreign', ['company_id', 'document_version_id'], 'document_versions', ['company_id', 'id'])
            ->foreign('admin_requirement_links_c32_evidence_foreign', ['company_id', 'evidence_document_id'], 'evidence_documents', ['company_id', 'id'])
            ->check('admin_requirement_links_one_target', 'num_nonnulls(document_version_id, evidence_document_id) = 1')
            ->check('admin_requirement_links_unlink_columns', 'num_nonnulls(unlinked_at, unlinked_by, unlink_reason) in (0, 3)')
            ->index('admin_requirement_links_document_version', 'document_version_id', 'document_version_id is not null')
            ->index('admin_requirement_links_evidence', 'evidence_document_id', 'evidence_document_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['unlinked_at', 'unlinked_by', 'unlink_reason']);

        DB::unprepared(<<<'SQL'
            create or replace function admin_requirement_links_unlink_once() returns trigger
            language plpgsql as $$
            begin
                if old.unlinked_at is not null or old.unlinked_by is not null or old.unlink_reason is not null
                    or (to_jsonb(new) - array['unlinked_at', 'unlinked_by', 'unlink_reason'])
                        is distinct from (to_jsonb(old) - array['unlinked_at', 'unlinked_by', 'unlink_reason']) then
                    raise exception 'A requirement link is unlinked once and changes no other column (DATABASE §4.11, §19).'
                        using errcode = 'check_violation', constraint = 'admin_requirement_links_unlink_once';
                end if;

                return new;
            end
            $$;

            create trigger admin_requirement_links_unlink_once
                before update on admin_requirement_links
                for each row execute function admin_requirement_links_unlink_once();

            create or replace function admin_requirement_links_client_original_evidence() returns trigger
            language plpgsql as $$
            begin
                if new.document_version_id is not null and exists (
                    select from admin_requirements r where r.id = new.admin_requirement_id and r.requires_client_original
                ) then
                    raise exception 'A client original is satisfied only by an evidence link, never a company draft or rendition (DATABASE §10; FS-12).'
                        using errcode = 'check_violation', constraint = 'admin_requirement_links_client_original_evidence';
                end if;

                return null;
            end
            $$;

            create constraint trigger admin_requirement_links_client_original_evidence
                after insert on admin_requirement_links
                for each row execute function admin_requirement_links_client_original_evidence();

            -- Nor does a requirement become one for a client original while a document version still satisfies it.
            create or replace function admin_requirements_client_original_evidence() returns trigger
            language plpgsql as $$
            begin
                if new.requires_client_original and not old.requires_client_original and exists (
                    select from admin_requirement_links l
                    where l.admin_requirement_id = new.id and l.document_version_id is not null and l.unlinked_at is null
                ) then
                    raise exception 'A client original is satisfied only by an evidence link: unlink its document versions first (DATABASE §10; FS-12).'
                        using errcode = 'check_violation', constraint = 'admin_requirements_client_original_evidence';
                end if;

                return new;
            end
            $$;

            create trigger admin_requirements_client_original_evidence
                before update on admin_requirements
                for each row execute function admin_requirements_client_original_evidence();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger admin_requirements_client_original_evidence on admin_requirements');
        DB::unprepared('drop function if exists admin_requirements_client_original_evidence()');
        Schema::drop('admin_requirement_links');
        DB::unprepared('drop function if exists admin_requirement_links_unlink_once()');
        DB::unprepared('drop function if exists admin_requirement_links_client_original_evidence()');
    }
};
