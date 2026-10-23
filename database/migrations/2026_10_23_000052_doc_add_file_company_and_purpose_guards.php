<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DATABASE §4.10, §22 and C-32 under DIR-089 (5): every file belongs to its owning record's company and to
// its purpose. The CHECK states `file_objects`' company by purpose: none for a product image, an import
// source and an export — their batch's `company_scope` and their request's `company_ids` name their
// companies, and access goes through that record (PJ-20; FL-09) —, a company's for a rendition, an
// identity asset and an archive file, either for an upload, none only for pool evidence. A trigger on each
// table that references `file_objects` refuses, at every insert and every update of the reference,
// another company's file, a file of no company outside those purposes and a file of another purpose. The
// triggers run after the row, so a foreign key still refuses first what it refuses. The runtime never
// updates a file or an owner's company (§28), so the check made at the reference's write stays true; a
// test finds every reference to `file_objects` guarded, so a later table cannot add one unchecked.
return new class extends Migration
{
    /** The tables whose references to `file_objects` are guarded, each by its trigger `<table>_c32_file`. */
    private const GUARDED = [
        'evidence_document_versions', 'document_renditions', 'product_images', 'companies', 'legacy_archive_items',
        'import_batches', 'export_requests',
    ];

    public function up(): void
    {
        TableRules::for('file_objects')->check(
            'file_objects_c32_company_by_purpose',
            "(purpose not in ('PRODUCT_IMAGE', 'IMPORT_SOURCE', 'EXPORT') or company_id is null)"
            ." and (purpose not in ('RENDITION', 'IDENTITY_ASSET', 'ARCHIVE') or company_id is not null)",
        );

        // One owner per import source, export and evidence file, so a file of no company — or of the same
        // company — cannot be taken over by another record of its table. The actor's right to a file stays
        // the commands' check (TASK-030, TASK-057, TASK-060).
        TableRules::for('import_batches')->unique('import_batches_c32_source_file_unique', ['source_file_id']);
        TableRules::for('export_requests')->unique('export_requests_c32_file_unique', ['file_object_id']);
        TableRules::for('evidence_document_versions')->unique('evidence_document_versions_c32_file_unique', ['file_object_id']);

        DB::unprepared(<<<'SQL'
            create or replace function file_objects_c32_holds(file_id bigint, owner_company_id bigint, file_purpose text) returns boolean
            language sql stable as $$
                select exists (
                    select from file_objects f
                    where f.id = file_id and f.purpose = file_purpose and f.company_id is not distinct from owner_company_id
                )
            $$;

            create or replace function evidence_document_versions_c32_file() returns trigger
            language plpgsql as $$
            begin
                if not file_objects_c32_holds(new.file_object_id,
                    (select e.company_id from evidence_documents e where e.id = new.evidence_document_id), 'UPLOAD') then
                    raise exception 'An evidence version holds an upload of its evidence''s company, or of no company for pool evidence (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'evidence_document_versions_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger evidence_document_versions_c32_file
                after insert or update of file_object_id, evidence_document_id on evidence_document_versions
                for each row execute function evidence_document_versions_c32_file();

            create or replace function document_renditions_c32_file() returns trigger
            language plpgsql as $$
            begin
                if new.file_object_id is not null and not file_objects_c32_holds(new.file_object_id,
                    (select v.company_id from document_versions v where v.id = new.document_version_id), 'RENDITION') then
                    raise exception 'A rendition holds a rendered file of its version''s company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'document_renditions_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger document_renditions_c32_file
                after insert or update of file_object_id, document_version_id on document_renditions
                for each row execute function document_renditions_c32_file();

            create or replace function product_images_c32_file() returns trigger
            language plpgsql as $$
            begin
                if not file_objects_c32_holds(new.file_object_id, null, 'PRODUCT_IMAGE') then
                    raise exception 'A product image holds a product-image file of no company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'product_images_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger product_images_c32_file
                after insert or update of file_object_id on product_images
                for each row execute function product_images_c32_file();

            create or replace function companies_c32_file() returns trigger
            language plpgsql as $$
            begin
                if (new.logo_file_id is not null and not file_objects_c32_holds(new.logo_file_id, new.id, 'IDENTITY_ASSET'))
                    or (new.stamp_file_id is not null and not file_objects_c32_holds(new.stamp_file_id, new.id, 'IDENTITY_ASSET'))
                    or (new.signature_file_id is not null and not file_objects_c32_holds(new.signature_file_id, new.id, 'IDENTITY_ASSET')) then
                    raise exception 'A company''s logo, stamp and signature are identity-asset files of that company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'companies_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger companies_c32_file
                after insert or update of logo_file_id, stamp_file_id, signature_file_id on companies
                for each row execute function companies_c32_file();

            create or replace function legacy_archive_items_c32_file() returns trigger
            language plpgsql as $$
            begin
                if new.file_object_id is not null and not file_objects_c32_holds(new.file_object_id, new.company_id, 'ARCHIVE') then
                    raise exception 'A legacy archive item holds an archive file of its company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'legacy_archive_items_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger legacy_archive_items_c32_file
                after insert or update of file_object_id, company_id on legacy_archive_items
                for each row execute function legacy_archive_items_c32_file();

            create or replace function import_batches_c32_file() returns trigger
            language plpgsql as $$
            begin
                if not file_objects_c32_holds(new.source_file_id, null, 'IMPORT_SOURCE') then
                    raise exception 'An import batch''s source is an import-source file of no company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'import_batches_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger import_batches_c32_file
                after insert or update of source_file_id on import_batches
                for each row execute function import_batches_c32_file();

            create or replace function export_requests_c32_file() returns trigger
            language plpgsql as $$
            begin
                if new.file_object_id is not null and not file_objects_c32_holds(new.file_object_id, null, 'EXPORT') then
                    raise exception 'An export request holds an export file of no company (DATABASE §22; C-32).'
                        using errcode = 'check_violation', constraint = 'export_requests_c32_file';
                end if;

                return null;
            end
            $$;

            create trigger export_requests_c32_file
                after insert or update of file_object_id on export_requests
                for each row execute function export_requests_c32_file();
            SQL);
    }

    public function down(): void
    {
        foreach (self::GUARDED as $table) {
            DB::unprepared("drop trigger {$table}_c32_file on {$table}; drop function {$table}_c32_file()");
        }

        DB::unprepared('drop function file_objects_c32_holds(bigint, bigint, text)');
        DB::statement('alter table evidence_document_versions drop constraint evidence_document_versions_c32_file_unique');
        DB::statement('alter table export_requests drop constraint export_requests_c32_file_unique');
        DB::statement('alter table import_batches drop constraint import_batches_c32_source_file_unique');
        DB::statement('alter table file_objects drop constraint file_objects_c32_company_by_purpose');
    }
};
