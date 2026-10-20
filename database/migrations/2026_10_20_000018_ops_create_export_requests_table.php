<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The `export_requests` record of CONCURRENCY_IDEMPOTENCY H6-04: an operational record outside the 125
// business tables, holding the context PERMISSIONS_MATRIX DP-07 requires of each export, so the export
// is re-authorized when it starts and at delivery. Never projected raw: only its requester sees its
// status. Its key is a UUIDv7, the request's public identifier on its routes (ROUTE_CONTRACTS RC-01;
// DIR-088), never enumerable; TASK-060 looks it up only within its requester's own requests. Admission,
// the job's claims and the cleanup are TASK-060's; `file_object_id` references `file_objects`, whose
// foreign key TASK-006 adds (DIR-088).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_requests', function (Blueprint $table) {
            $table->uuid('id')->default(new Expression('uuidv7()'))->primary();
            Columns::user($table, 'requested_by');
            $table->text('report_code');
            // The whitelisted filters, as requested.
            $table->jsonb('filters')->default('{}');
            // The company set, view capabilities and masking context it was generated under.
            $table->rawColumn('company_ids', 'bigint[]');
            $table->rawColumn('capability_codes', 'text[]');
            $table->rawColumn('masking_context', 'text[]');
            $table->text('state')->default('PENDING');
            Columns::timestamp($table, 'claimed_at')->nullable();
            $table->smallInteger('claim_count')->default(0);
            $table->bigInteger('file_object_id')->nullable();
            Columns::timestamp($table, 'expires_at')->nullable();
            Columns::recordedAt($table);
        });

        TableRules::for('export_requests')
            ->codes('export_requests_state_code', 'state', ['PENDING', 'RUNNING', 'READY', 'FAILED', 'WITHHELD'])
            // IX-05: admission by requester, the access changes of RV-08, the sweep JB-09 and cleanup.
            ->index('export_requests_ix05_requester_state', 'requested_by, state')
            ->index('export_requests_ix05_state_claimed', 'state, claimed_at')
            ->index('export_requests_ix05_state_recorded', 'state, recorded_at')
            ->grant('select, insert, delete')
            ->grantUpdate(['state', 'claimed_at', 'claim_count', 'file_object_id', 'expires_at']);
    }

    public function down(): void
    {
        Schema::drop('export_requests');
    }
};
