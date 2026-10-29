<?php

namespace App\Modules\ADM\Actions;

use App\Modules\ADM\Services\Requirements;
use App\Modules\IAM\Services\AccountAccess;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\CommercialConfirmation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `ADM SupersedeDecision` (ROUTE-169; INT-169; CM-27; L-32; OD-15) — "Koreksi Keputusan": a decision recorded in error
 * superseded by a new decision of the same authority with its reason (IP-09), both kept and visible, under the lock set
 * of the action that recorded it (CONCURRENCY_IDEMPOTENCY §7.3):
 *
 * - a waiver, at the head of its item's chain, under NX-03: the superseding row withdraws it — the item applies again,
 *   marked as reopened by this correction (BR-ADM-03) —, a waiver with the right reason then recorded by ROUTE-166;
 * - a quotation approval, the project's latest confirmation, under AX-13: TASK-034's confirmation step with the corrected
 *   business date, the pins following and the reservations moving only through the lifecycle participants (SF-RESERVE),
 *   never by reactivation. A later confirmation is a dependent fact corrected first (CONFLICT).
 *
 * `decisions.supersede` of the project's company; a decision an Owner account made only by an Owner (OD-15).
 */
final class SupersedeDecision
{
    public const TYPE = 'ADM.SupersedeDecision';

    public const CAPABILITY = 'decisions.supersede';

    public const WAIVER = 'waiver';

    public const APPROVAL = 'approval';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
        private readonly CommercialConfirmation $confirmation,
        private readonly AccountAccess $access,
    ) {}

    /**
     * @param  string  $kind  {@see self::WAIVER} or {@see self::APPROVAL}
     * @param  int  $decisionId  the waiver or the commercial confirmation, resolved by the caller among the project's
     * @param  ?string  $businessDate  an approval's corrected business date; null for a waiver
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, string $kind, int $decisionId, string $reason, ?string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'kind' => $kind,
                'decision' => $decisionId, 'reason' => $reason, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $kind, $decisionId, $reason, $businessDate, $prefilled): CommandResult {
            return $kind === self::WAIVER
                ? $this->waiver($context, $project, $decisionId, $reason)
                : $this->approval($context, $project, $decisionId, $reason, $businessDate ?? '', $prefilled);
        });
    }

    private function waiver(CommandContext $context, ProjectRef $project, int $waiverId, string $reason): CommandResult
    {
        $context->lock(Requirements::locks($project->id));
        Requirements::open($project->id);
        $requirementId = DB::table('admin_requirement_waivers as w')->join('admin_requirements as r', 'r.id', '=', 'w.admin_requirement_id')
            ->where('w.id', $waiverId)->where('r.project_id', $project->id)->value('r.id');
        $current = is_numeric($requirementId) ? Requirements::find($project->id, (int) $requirementId) : null;
        $head = $current === null ? null : Requirements::head($current['id']);

        if ($current === null || $head === null || $head['id'] !== $waiverId || $head['reopened']) {
            // A superseded or reopened waiver is no longer the decision in force: the head is what a correction supersedes.
            throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
        }

        $this->owner($context, $head['decided_by']);

        return new CommandResult('admin_requirement_waiver', $this->requirements->waive($context, $current, $reason, true, 'ADM.WaiverSuperseded', AuthorityClass::AdmPlus));
    }

    private function approval(CommandContext $context, ProjectRef $project, int $confirmationId, string $reason, string $businessDate, bool $prefilled): CommandResult
    {
        $context->lock(CommercialConfirmation::locks($project->id));
        $context->require(CommercialConfirmation::locks($project->id));
        $decision = DB::table('commercial_confirmations')->where('id', $confirmationId)->where('project_id', $project->id)
            ->first(['kind', 'quotation_revision_id', 'decided_by']);

        if ($decision === null || $decision->kind !== 'QUOTATION_APPROVAL' || ! is_numeric($decision->quotation_revision_id)) {
            throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
        }

        if (CommercialConfirmation::latest($project->id) !== $confirmationId) {
            // CM-27: a later confirmation depends on this one and is corrected first.
            throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
        }

        $this->owner($context, is_numeric($decision->decided_by) ? (int) $decision->decided_by : 0);
        BusinessDateRule::check($businessDate, $prefilled);

        return new CommandResult('commercial_confirmation', $this->confirmation->confirm($context, $project->id, $project->companyId, 'QUOTATION_APPROVAL',
            (int) $decision->quotation_revision_id, $businessDate, $reason, null, $confirmationId));
    }

    /** OD-15: a decision an Owner account made is superseded only by an Owner. */
    private function owner(CommandContext $context, int $decidedBy): void
    {
        if ($this->access->holdsOwnerRole($decidedBy) && ! $this->access->holdsOwnerRole($context->request->actorUserId)) {
            throw Refused::rejected('OD-15', ['reason' => 'owner']);
        }
    }
}
