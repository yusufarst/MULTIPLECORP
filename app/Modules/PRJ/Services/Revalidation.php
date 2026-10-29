<?php

namespace App\Modules\PRJ\Services;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * SF-REVAL's transition (BR-CR-05; BR-PRJ-04; DATABASE §6.3 step 5): a material correction of a completed project's
 * facts returns it to ACTIVE in the correcting command's transaction, under its LK-01 lock — one REVALIDATE transition
 * per project and command (`project_state_transitions_one_per_project_per_command`), its reference the correction —,
 * and the lifecycle participants reopen what they hold (ADM's waivers, BR-ADM-03). A project not completed is left as
 * it is: its predicates are read live. The completion that follows is TASK-055's (AX-25).
 */
final class Revalidation
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly Container $app,
    ) {}

    public static function locks(int $projectId): LockSet
    {
        return (new LockSet)->add('projects', ['id' => $projectId]);
    }

    /** Returns whether the project was revalidated: true for a completed project, false for any other. */
    public function revalidate(CommandContext $context, int $projectId, string $correctionReference): bool
    {
        $context->require(self::locks($projectId));
        $project = DB::table('projects')->where('id', $projectId)->first(['state', 'company_id', 'public_id']);

        if (! is_object($project) || ! in_array($project->state, ['COMPLETED_NORMAL', 'COMPLETED_FORCED'], true)) {
            return false;
        }

        $commandId = $context->request->commandId;

        if (DB::table('project_state_transitions')->where('command_id', $commandId)->where('project_id', $projectId)->exists()) {
            return false;
        }

        $now = $context->now();
        $from = (string) $project->state;
        $companyId = is_numeric($project->company_id) ? (int) $project->company_id : 0;
        DB::table('projects')->where('id', $projectId)->update(['state' => 'ACTIVE', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
        DB::table('project_state_transitions')->insert([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'from_state' => $from,
            'to_state' => 'ACTIVE',
            'transition_kind' => 'REVALIDATE',
            'correction_reference' => $correctionReference,
            'decided_by' => $context->request->actorUserId,
            'command_id' => $commandId,
            'command_line_no' => DB::table('project_state_transitions')->where('command_id', $commandId)->count() + 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);

        foreach ($this->app->tagged(ProjectLifecycleParticipant::TAG) as $participant) {
            if ($participant instanceof ProjectLifecycleParticipant) {
                $participant->revalidated($context, $projectId);
            }
        }

        $this->audit->record(
            action: 'PRJ.ProjectRevalidated',
            authorityClass: AuthorityClass::System,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: is_string($project->public_id) ? $project->public_id : null,
            reason: $correctionReference,
            commandId: $commandId,
            change: AuditChange::updated(['state' => $from], ['state' => 'ACTIVE']),
        );

        return true;
    }
}
