<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `evidence_document_versions` (AF): a replacement is a new version over a new immutable
// file, keeping the history (L-41; §22). An evidence's current version is one of its own versions, keyed
// here once both tables exist. The file is a plain key, this table having no company column for §15's
// company-scoped form (TASK-006's discovery (5)).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_document_versions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'evidence_document_id', 'evidence_documents');
            $table->integer('version_no');
            Columns::reference($table, 'file_object_id', 'file_objects');
            $table->text('reason')->nullable();
            Columns::user($table, 'uploaded_by');
            Columns::commandIdentity($table, 'evidence_document_versions');
            Columns::recordedAt($table);
            $table->index('file_object_id');
        });

        TableRules::for('evidence_document_versions')
            ->unique('evidence_document_versions_number_unique', ['evidence_document_id', 'version_no'])
            ->unique('evidence_document_versions_document_key', ['evidence_document_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');

        TableRules::for('evidence_documents')
            ->foreign('evidence_documents_current_version_foreign', ['id', 'current_version_id'], 'evidence_document_versions', ['evidence_document_id', 'id']);
    }

    public function down(): void
    {
        DB::statement('alter table evidence_documents drop constraint evidence_documents_current_version_foreign');
        Schema::drop('evidence_document_versions');
    }
};
