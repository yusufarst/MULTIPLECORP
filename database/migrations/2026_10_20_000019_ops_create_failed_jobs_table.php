<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The framework's failed-job table, for its `database-uuids` driver: an infrastructure table outside the
// 125 business tables, never projected raw, whose payloads hold identifiers only (SECURITY H9-04, LG-04;
// PERMISSIONS_MATRIX DP-09); its retention is TASK-022's. The queue, cache, lock and limiter stores are
// Valkey (INFRASTRUCTURE §2): no table of theirs exists, nor a job-batch table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            Columns::id($table);
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            Columns::timestamp($table, 'failed_at')->useCurrent();
        });

        TableRules::for('failed_jobs')->grant('select, insert, delete');
    }

    public function down(): void
    {
        Schema::drop('failed_jobs');
    }
};
