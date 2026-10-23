<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `project_state_transitions` (AD) and §9: the activation, cancellation, completion, Force
// Complete and revalidation history of a project (BR-PRJ-02–04; BR-CR-05; AX-25–27), written with the
// project's state in one transaction. One transition per project per command, so one correction
// revalidates every completed project it touches (§1). A Force Complete carries its reason, its predicate
// snapshot and the hash of the blocker snapshot the Owner confirmed (C-38).
return new class extends Migration
{
    private const STATES = ['DRAFT', 'ACTIVE', 'CANCELLED', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'];

    public function up(): void
    {
        Schema::create('project_state_transitions', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->text('from_state');
            $table->text('to_state');
            $table->text('transition_kind');
            $table->text('reason')->nullable();
            $table->jsonb('predicate_snapshot')->nullable();
            $table->jsonb('unmet_predicates')->nullable();
            $table->jsonb('residual_obligations')->nullable();
            $table->text('confirmed_snapshot_hash')->nullable();
            $table->text('correction_reference')->nullable();
            Columns::user($table, 'decided_by');
            Columns::commandIdentity($table, 'project_state_transitions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('project_id');
        });

        TableRules::for('project_state_transitions')
            ->unique('project_state_transitions_one_per_project_per_command', ['command_id', 'project_id'])
            ->foreign('project_state_transitions_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->codes('project_state_transitions_from_state_code', 'from_state', self::STATES)
            ->codes('project_state_transitions_to_state_code', 'to_state', self::STATES)
            ->codes('project_state_transitions_kind_code', 'transition_kind', ['ACTIVATE', 'CANCEL', 'COMPLETE_NORMAL', 'FORCE_COMPLETE', 'REVALIDATE'])
            ->check('project_state_transitions_c38_force_complete', "transition_kind <> 'FORCE_COMPLETE' or (reason is not null and predicate_snapshot is not null and confirmed_snapshot_hash is not null)")
            ->check('project_state_transitions_hash_hex', "confirmed_snapshot_hash is null or confirmed_snapshot_hash ~ '^[0-9a-f]{64}$'")
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('project_state_transitions');
    }
};
