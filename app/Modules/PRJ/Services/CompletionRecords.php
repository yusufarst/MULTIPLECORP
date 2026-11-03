<?php

namespace App\Modules\PRJ\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The completion's write (AX-25; AX-26; SM:Project; DATABASE §4.5): ACTIVE → COMPLETED_NORMAL or COMPLETED_FORCED under the project's LK-01, with its
 * transition — the predicate snapshot, and for a Force Complete the unmet predicates, the residual obligations, the reason and the confirmed hash (C-38) —
 * and its business audit event. Nothing else is created: no stock, cash, settlement or document (FS-08; BR-PRJ-04).
 */
final class CompletionRecords
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** The project's state read under its lock: only an ACTIVE project completes (SM:Project; ST-02). */
    public static function active(int $projectId, int $companyId): object
    {
        $project = DB::table('projects')->where('id', $projectId)->where('company_id', $companyId)->first(['public_id', 'state']);

        if ($project === null) {
            throw Refused::rejected('C-14', ['reason' => 'project']);
        }

        if (Row::text($project, 'state') !== 'ACTIVE') {
            throw Refused::conflict('ST-02', ['state' => Row::text($project, 'state')]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $unmet
     * @param  array{reason: string, hash: string}|null  $forced
     */
    public function complete(CommandContext $context, int $projectId, int $companyId, string $publicId, array $snapshot, array $unmet, ?array $forced): void
    {
        $now = $context->now();
        $to = $forced === null ? 'COMPLETED_NORMAL' : 'COMPLETED_FORCED';
        DB::table('projects')->where('id', $projectId)->where('state', 'ACTIVE')->update(['state' => $to, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
        DB::table('project_state_transitions')->insert([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'from_state' => 'ACTIVE',
            'to_state' => $to,
            'transition_kind' => $forced === null ? 'COMPLETE_NORMAL' : 'FORCE_COMPLETE',
            'reason' => $forced['reason'] ?? null,
            // With the residual obligations beside it, the record gives back the snapshot its hash was taken of (ST-04).
            'predicate_snapshot' => json_encode(['format' => $snapshot['format'] ?? null, 'conditions' => $snapshot['conditions'] ?? []], JSON_THROW_ON_ERROR),
            'unmet_predicates' => $forced === null ? null : json_encode($unmet, JSON_THROW_ON_ERROR),
            'residual_obligations' => $forced === null ? null : json_encode($snapshot['obligations'] ?? [], JSON_THROW_ON_ERROR),
            'confirmed_snapshot_hash' => $forced['hash'] ?? null,
            'decided_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId,
            'command_line_no' => 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);

        $this->audit->record(
            action: $forced === null ? 'PRJ.ProjectCompleted' : 'PRJ.ProjectForceCompleted',
            authorityClass: $forced === null ? AuthorityClass::Adm : AuthorityClass::OwnerOnly,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: $publicId,
            reason: $forced['reason'] ?? null,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['state' => 'ACTIVE'], ['state' => $to]),
            metadata: $forced === null ? [] : ['unmet' => $unmet, 'snapshot_hash' => $forced['hash']],
        );
    }
}
