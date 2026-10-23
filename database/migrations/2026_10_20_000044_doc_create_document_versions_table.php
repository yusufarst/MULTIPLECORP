<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `document_versions` (SR, immutable once issued) and §10: one issued and one draft version
// per document; issuing commits the number drawn from the counter, the immutable payload and its hash and
// the business version it renders (SF-ISSUE; AX-15). Once ISSUED, the trigger refuses any change but the
// supersession (by a new version whose `supersedes_version_id` is UNIQUE, so the chain is linear) and the
// void with its reason and actor, which keeps the row and its number (BR-DOC-01/02; C-11); a deferred
// constraint trigger lets a supersession commit only with the new version of its document that
// supersedes it, issued in the same transaction, so a document never loses its issued version silently.
// Only a draft is deleted (§13, §19). The UNIQUE keys on the number backstop the allocator (C-10; NM-09). The bank
// account is the company's own (§14–§15; C-32) and the DOC-05 amount is typed, never read from the
// payload. The rendered quotation revision and invoice version are the tables of TASK-007 and TASK-009,
// which add their keys (DIR-089 (1)).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->bigInteger('document_id');
            // Denormalized from the document for the keys below.
            $table->bigInteger('company_id');
            $table->text('document_type_code');
            $table->integer('version_no');
            $table->text('state')->default('DRAFT');
            Columns::businessDate($table)->nullable();
            $table->bigInteger('numbering_scheme_id')->nullable();
            $table->text('period_key')->nullable();
            $table->bigInteger('sequence_no')->nullable();
            $table->integer('revision_suffix')->default(0);
            $table->text('formatted_number')->nullable();
            $table->bigInteger('quotation_revision_id')->nullable();
            $table->bigInteger('invoice_version_id')->nullable();
            Columns::money($table, 'kuitansi_amount')->nullable();
            $table->bigInteger('company_bank_account_id')->nullable();
            $table->jsonb('payload')->nullable();
            $table->text('payload_sha256')->nullable();
            Columns::user($table, 'issued_by')->nullable();
            $table->uuid('issue_command_id')->nullable();
            $table->bigInteger('supersedes_version_id')->nullable();
            Columns::user($table, 'voided_by')->nullable();
            Columns::timestamp($table, 'voided_at')->nullable();
            $table->text('voided_reason')->nullable();
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('document_versions')
            ->unique('document_versions_chain_key', ['document_id', 'id'])
            ->unique('document_versions_c32_company_key', ['company_id', 'id'])
            ->unique('document_versions_c11_linear_chain', ['supersedes_version_id'])
            // One command may issue several documents, each once (§1; C-11).
            ->unique('document_versions_c11_one_issue_per_command', ['issue_command_id', 'document_id'])
            ->unique('document_versions_c10_number', ['company_id', 'document_type_code', 'period_key', 'sequence_no', 'revision_suffix'])
            ->unique('document_versions_c10_formatted_number', ['company_id', 'document_type_code', 'formatted_number'])
            ->uniqueIndex('document_versions_c11_one_issued', 'document_id', "state = 'ISSUED'")
            ->uniqueIndex('document_versions_c11_one_draft', 'document_id', "state = 'DRAFT'")
            ->foreign('document_versions_document_foreign', ['company_id', 'document_id', 'document_type_code'], 'documents', ['company_id', 'id', 'document_type_code'])
            ->foreign('document_versions_c32_numbering_scheme_foreign', ['company_id', 'numbering_scheme_id'], 'numbering_schemes', ['company_id', 'id'])
            ->foreign('document_versions_c32_bank_account_foreign', ['company_id', 'company_bank_account_id'], 'company_bank_accounts', ['company_id', 'id'])
            ->foreign('document_versions_c11_supersedes_foreign', ['document_id', 'supersedes_version_id'], 'document_versions', ['document_id', 'id'])
            ->codes('document_versions_state_code', 'state', ['DRAFT', 'ISSUED', 'SUPERSEDED', 'VOIDED'])
            ->check('document_versions_period_key_format', "period_key is null or period_key ~ '^[0-9]{4}(-(0[1-9]|1[0-2]))?$'")
            ->check('document_versions_payload_sha256_hex', "payload_sha256 is null or payload_sha256 ~ '^[0-9a-f]{64}$'")
            ->check('document_versions_c11_issued_content', "state = 'DRAFT' or (formatted_number is not null and payload is not null)")
            ->check('document_versions_issued_business_date', "state = 'DRAFT' or business_date is not null")
            ->check('document_versions_kuitansi_amount', "document_type_code <> 'DOC-05' or (kuitansi_amount is not null and kuitansi_amount > 0)")
            ->check('document_versions_void_columns', "num_nonnulls(voided_by, voided_at, voided_reason) in (0, 3) and (state = 'VOIDED') = (voided_at is not null)")
            // A project's documents and their versions (§20).
            ->index('document_versions_document_version', 'document_id, version_no')
            // The bank account trigger's lookup of a referenced account (2026_10_20_000023).
            ->index('document_versions_bank_account', 'company_bank_account_id', 'company_bank_account_id is not null')
            ->grant('select, insert, delete')
            ->grantUpdate([
                'state', 'business_date', 'numbering_scheme_id', 'period_key', 'sequence_no', 'revision_suffix', 'formatted_number',
                'quotation_revision_id', 'invoice_version_id', 'kuitansi_amount', 'company_bank_account_id', 'payload', 'payload_sha256',
                'issued_by', 'issue_command_id', 'voided_by', 'voided_at', 'voided_reason', 'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function document_versions_c11_issued_immutable() returns trigger
            language plpgsql as $$
            begin
                if old.state = 'DRAFT' then
                    if new.state not in ('DRAFT', 'ISSUED') then
                        raise exception 'A draft version is issued or deleted, never superseded or voided (DATABASE §10; C-11).'
                            using errcode = 'check_violation', constraint = 'document_versions_c11_issued_immutable';
                    end if;

                    return new;
                end if;

                if old.state <> 'ISSUED' or new.state not in ('SUPERSEDED', 'VOIDED')
                    or (to_jsonb(new) - array['state', 'voided_by', 'voided_at', 'voided_reason', 'lock_version', 'updated_at'])
                        is distinct from
                        (to_jsonb(old) - array['state', 'voided_by', 'voided_at', 'voided_reason', 'lock_version', 'updated_at']) then
                    raise exception 'An issued version changes only by its supersession or its void (DATABASE §10; C-11).'
                        using errcode = 'check_violation', constraint = 'document_versions_c11_issued_immutable';
                end if;

                return new;
            end
            $$;

            create trigger document_versions_c11_issued_immutable
                before update on document_versions
                for each row execute function document_versions_c11_issued_immutable();

            create or replace function document_versions_draft_delete_only() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'DRAFT' then
                    raise exception 'Only a draft version is deleted (DATABASE §13, §19).'
                        using errcode = 'check_violation', constraint = 'document_versions_draft_delete_only';
                end if;

                return old;
            end
            $$;

            create trigger document_versions_draft_delete_only
                before delete on document_versions
                for each row execute function document_versions_draft_delete_only();

            create or replace function document_versions_c11_superseded_by_successor() returns trigger
            language plpgsql as $$
            begin
                if not exists (
                    select from document_versions successor
                    where successor.document_id = new.document_id and successor.supersedes_version_id = new.id
                        and successor.state <> 'DRAFT'
                ) then
                    raise exception 'A version is superseded only by the new version of its document that supersedes it, issued with it (DATABASE §10; C-11).'
                        using errcode = 'check_violation', constraint = 'document_versions_c11_superseded_by_successor';
                end if;

                return null;
            end
            $$;

            create constraint trigger document_versions_c11_superseded_by_successor
                after insert or update on document_versions
                deferrable initially deferred
                for each row when (new.state = 'SUPERSEDED')
                execute function document_versions_c11_superseded_by_successor();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('document_versions');
        DB::unprepared('drop function if exists document_versions_c11_issued_immutable()');
        DB::unprepared('drop function if exists document_versions_draft_delete_only()');
        DB::unprepared('drop function if exists document_versions_c11_superseded_by_successor()');
    }
};
