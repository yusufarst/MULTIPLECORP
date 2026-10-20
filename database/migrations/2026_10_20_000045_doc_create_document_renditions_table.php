<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `document_renditions` (OP) and §10: rendering is derived work, never an issue — a
// rendition references its version and has no number or payload of its own, so a retry adds an attempt
// and never reissues or renumbers (BR-DOC-03; GAP-007; C-12). The PENDING row committed with the issue is
// the durable record of work owed; the sweeper JB-02 re-queues PENDING, RENDERING and FAILED attempts on
// IX-07, which also serves QS-08. `error_summary` holds no secret. The rendered file is a plain key, this
// table having no company column for §15's company-scoped form (TASK-006's discovery (5)).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_renditions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'document_version_id', 'document_versions');
            $table->integer('attempt_no');
            $table->text('state')->default('PENDING');
            Columns::reference($table, 'file_object_id', 'file_objects')->nullable();
            $table->text('renderer_version')->nullable();
            $table->text('error_summary')->nullable();
            Columns::recordedAt($table);
            Columns::timestamp($table, 'updated_at')->nullable();
            $table->index('file_object_id');
        });

        TableRules::for('document_renditions')
            ->unique('document_renditions_attempt_unique', ['document_version_id', 'attempt_no'])
            ->codes('document_renditions_state_code', 'state', ['PENDING', 'RENDERING', 'READY', 'FAILED'])
            ->uniqueIndex('document_renditions_one_ready', 'document_version_id', "state = 'READY'")
            // IX-07: the attempts still owed (JB-02; QS-08).
            ->index('document_renditions_ix07_owed', 'state, recorded_at', "state in ('PENDING', 'RENDERING', 'FAILED')")
            ->grant('select, insert')
            ->grantUpdate(['state', 'file_object_id', 'renderer_version', 'error_summary', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('document_renditions');
    }
};
