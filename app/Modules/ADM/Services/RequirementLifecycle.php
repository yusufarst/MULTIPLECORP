<?php

namespace App\Modules\ADM\Services;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * ADM's part of `ProjectLifecycleParticipant` (ARCHITECTURE §3), inside PRJ's transaction under the project's LK-01:
 * a confirmation that gives the project commercial value adds the default invoice item, required and never removed
 * (L-27; `is_default_invoice_item`); a revalidation after a material correction reopens the project's standing waivers,
 * each kept as history and superseded by a row marked as reopened by that correction (SF-REVAL; BR-ADM-03) — what a
 * link satisfied is derived again on reading. A re-confirmation, a scope reduction and a cancellation change no item.
 */
final class RequirementLifecycle implements ProjectLifecycleParticipant
{
    /** The reopening row's reason, a system decision of the correction that triggered it. */
    public const REOPENED = 'Reopened by a material correction (SF-REVAL; BR-ADM-03).';

    public function __construct(
        private readonly Requirements $requirements,
        private readonly AuditRecorder $audit,
    ) {}

    public function confirmed(CommandContext $context, int $projectId): void
    {
        $value = DB::table('project_commercial_balances')->where('project_id', $projectId)->value('confirmed_sales_value');

        if (! is_numeric($value) || (float) $value <= 0.0 || DB::table('admin_requirements')->where('project_id', $projectId)->where('is_default_invoice_item', true)->exists()) {
            return;
        }

        $project = DB::table('projects')->where('id', $projectId)->first(['company_id', 'public_id']);

        if (! is_object($project)) {
            return;
        }

        $now = $context->now();
        $id = (int) DB::table('admin_requirements')->insertGetId([
            'project_id' => $projectId,
            'company_id' => (int) Row::text($project, 'company_id'),
            'requirement_kind' => 'FREE_TEXT',
            'description' => 'Invoice',
            'is_required' => true,
            'satisfaction_mode' => 'GENERATED',
            'is_default_invoice_item' => true,
            'command_id' => $context->request->commandId,
            'command_line_no' => DB::table('admin_requirements')->where('command_id', $context->request->commandId)->count() + 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $this->audit->record(
            action: 'ADM.DefaultInvoiceItemAdded',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: (int) Row::text($project, 'company_id'),
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: Row::nullable($project, 'public_id'),
            commandId: $context->request->commandId,
            change: AuditChange::created(['requirement' => (string) $id, 'default_invoice' => 'true']),
            metadata: ['requirement' => $id],
        );
    }

    public function reconfirmed(CommandContext $context, int $projectId): void {}

    public function scopeReduced(CommandContext $context, int $projectId, array $released): void {}

    public function cancelled(CommandContext $context, int $projectId): void {}

    public function revalidated(CommandContext $context, int $projectId): void
    {
        foreach (Readiness::of([$projectId])[$projectId] ?? [] as $item) {
            if ($item['status'] === Readiness::WAIVED) {
                $this->requirements->waive($context, $item, self::REOPENED, true, 'ADM.WaiverReopened', AuthorityClass::System);
            }
        }
    }
}
