<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ CancelProject` (ROUTE-163; INT-163; AX-27; WF-PRJ-04; BR-PRJ-01; CM-29) — "Batalkan Proyek" on PG-011: an ACTIVE project, not already cancelled or completed, moves
 * to CANCELLED with its CANCEL transition and the reason; in the same action every active reservation of its lines is released through INV's part of
 * `ProjectLifecycleParticipant`, RESERVED falling and AVAILABLE restored. Nothing else is undone: returns, invoice voids or revisions, purchase cancellations and
 * the remaining scope are each their own command (IP-19). The facts that stay true stay — goods kept by the client, a retained DP, received goods as free stock,
 * open receivables active and collectable (L-10). No transition leaves CANCELLED (SM:Project). `projects.cancel` (ADM_PLUS) of the project's company.
 */
final class CancelProject
{
    public const TYPE = 'PRJ.CancelProject';

    public const CAPABILITY = 'projects.cancel';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Container $app,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * AX-27's set: the project (LK-01), and for each line holding an active reservation its product's guard (LK-13), the reservation (LK-18) and the line's guard
     * (LK-19), so the release posts without a restart (SQ-20).
     */
    public static function locks(int $projectId): LockSet
    {
        $set = Revalidation::locks($projectId);

        foreach (DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->where('l.project_id', $projectId)->where('r.state', 'ACTIVE')->orderBy('r.id')->get(['r.id', 'r.project_line_id', 'l.product_id']) as $row) {
            if (DB::table('stock_product_balances as b')->where('b.product_id', (int) Row::text($row, 'product_id'))->exists()) {
                $set = $set->add('stock_product_balances', ['product_id' => (int) Row::text($row, 'product_id')]);
            }

            $set = $set->add('reservations', ['id' => (int) Row::text($row, 'id')])->add('project_line_balances', ['project_line_id' => (int) Row::text($row, 'project_line_id')]);
        }

        return $set;
    }

    public function run(string $commandId, int $actorUserId, int $projectId, int $companyId, string $projectPublicId, int $lockVersion, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['project' => $projectPublicId, 'lock_version' => $lockVersion, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($projectId, $companyId, $lockVersion, $reason): CommandResult {
            $context->lock(self::locks($projectId));
            // DATA-DEPENDENT: a reservation made or consumed meanwhile changes what the release locks.
            $context->require(self::locks($projectId));
            $project = DB::table('projects')->where('id', $projectId)->where('company_id', $companyId)->first(['public_id', 'state', 'lock_version']);

            if ($project === null) {
                throw Refused::rejected('C-14', ['reason' => 'project']);
            }

            $state = Row::text($project, 'state');

            // AX-27: never a second cancellation, never a completed project's; a draft is discarded, not cancelled (L-37).
            if ($state !== 'ACTIVE') {
                throw Refused::rejected('AX-27', ['reason' => $state === 'DRAFT' ? 'draft' : 'closed', 'state' => $state]);
            }

            // The project changed since the page the reason was given on (ST-01).
            if ((int) Row::text($project, 'lock_version') !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            DB::table('projects')->where('id', $projectId)->where('state', 'ACTIVE')->update(['state' => 'CANCELLED', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
            DB::table('project_state_transitions')->insert([
                'project_id' => $projectId, 'company_id' => $companyId, 'from_state' => 'ACTIVE', 'to_state' => 'CANCELLED', 'transition_kind' => 'CANCEL', 'reason' => $reason,
                'decided_by' => $actor, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $released = DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->where('l.project_id', $projectId)->where('r.state', 'ACTIVE')->count();

            // CM-29: INV releases every active reservation of the project's lines in this transaction, RELEASE events and both guards.
            foreach ($this->app->tagged(ProjectLifecycleParticipant::TAG) as $participant) {
                if ($participant instanceof ProjectLifecycleParticipant) {
                    $participant->cancelled($context, $projectId);
                }
            }

            $this->audit->record(
                action: 'PRJ.ProjectCancelled',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $actor,
                companyId: $companyId,
                entityType: 'project',
                entityId: $projectId,
                entityPublicId: Row::text($project, 'public_id'),
                reason: $reason,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'ACTIVE'], ['state' => 'CANCELLED']),
                metadata: ['reservations_released' => $released],
            );

            return new CommandResult('project', $projectId);
        });
    }
}
