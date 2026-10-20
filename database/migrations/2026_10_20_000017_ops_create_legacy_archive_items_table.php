<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `legacy_archive_items` (AF) and §24 (DB24-08): identified legacy archive, never a
// current effect (BR-XD-06; D-06). `company_id` and `file_object_id` reference the tables TASK-006 creates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_archive_items', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('company_id');
            $table->text('archive_kind');
            $table->text('legacy_reference');
            $table->text('description')->nullable();
            $table->bigInteger('file_object_id')->nullable();
            $table->foreignId('import_batch_id')->nullable();
            Columns::commandIdentity($table, 'legacy_archive_items');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->restrictOnDelete();
            $table->index('import_batch_id');
        });

        TableRules::for('legacy_archive_items')
            ->unique('legacy_archive_items_c32_company_key', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('legacy_archive_items');
    }
};
