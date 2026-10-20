<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The foreign keys of TASK-005's columns that reference `companies` and `file_objects`, added expand-only
// now that both tables exist (DIR-088 (1)): each company column to its company and the legacy archive
// item's file in the company-scoped form of §15, so an archive item can only hold its own company's file
// (C-32). The import source and the export file belong to a multi-company batch or request and are plain
// keys. The file references are indexed for the restricted delete of a never-linked upload (§19–§20).
return new class extends Migration
{
    private const COMPANY_KEYS = ['user_company_grants', 'correction_cases', 'import_rows', 'legacy_archive_items', 'audit_events'];

    private const FILE_INDEXES = [
        'import_batches_source_file_id_index' => ['import_batches', 'source_file_id'],
        'export_requests_file_object_id_index' => ['export_requests', 'file_object_id'],
        'legacy_archive_items_file_object_id_index' => ['legacy_archive_items', 'file_object_id'],
    ];

    public function up(): void
    {
        foreach (self::COMPANY_KEYS as $table) {
            TableRules::for($table)->foreign("{$table}_company_id_foreign", ['company_id'], 'companies', ['id']);
        }

        TableRules::for('legacy_archive_items')
            ->foreign('legacy_archive_items_c32_file_object_foreign', ['company_id', 'file_object_id'], 'file_objects', ['company_id', 'id']);
        TableRules::for('import_batches')
            ->foreign('import_batches_source_file_id_foreign', ['source_file_id'], 'file_objects', ['id']);
        TableRules::for('export_requests')
            ->foreign('export_requests_file_object_id_foreign', ['file_object_id'], 'file_objects', ['id']);

        foreach (self::FILE_INDEXES as $name => [$table, $column]) {
            TableRules::for($table)->index($name, $column, "{$column} is not null");
        }
    }

    public function down(): void
    {
        foreach (self::FILE_INDEXES as $name => [$table]) {
            DB::statement("drop index {$name}");
        }

        DB::statement('alter table export_requests drop constraint export_requests_file_object_id_foreign');
        DB::statement('alter table import_batches drop constraint import_batches_source_file_id_foreign');
        DB::statement('alter table legacy_archive_items drop constraint legacy_archive_items_c32_file_object_foreign');

        foreach (self::COMPANY_KEYS as $table) {
            DB::statement("alter table {$table} drop constraint {$table}_company_id_foreign");
        }
    }
};
