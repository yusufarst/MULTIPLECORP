<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Services\CreditProjects;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
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
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN HoldCredit` (ROUTE-264; INT-264; NX-03; WF-FIN-06; `DIR-101`; `DIR-104`; APPR-028) — "Tahan Kredit" on PG-048: a project's credit held with its reason, the project's
 * `credit_held_amount` set to what has arisen for it so far, so completion predicate 9 counts it dispositioned until more arises (TASK-055). REJECTED when the project is not
 * ACTIVE, or has no credit left, or none arisen beyond what it already covers. The reason, the project, the amount and the sources on the audit event (LG-01). Applying and
 * refunding stay open whatever is held. `refund.record` (ADM_PLUS) of the project's company.
 */
final class HoldCredit
{
    public const TYPE = 'FIN.HoldCredit';

    public const CAPABILITY = 'refund.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $projectId, int $companyId, string $reason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), [...$payload, 'project' => $projectId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($projectId, $companyId, $reason): CommandResult {
            // LK-01: a release or an application on the project waits for the hold, so a later credit is never taken as covered.
            $context->lock((new LockSet)->add('projects', ['id' => $projectId]));
            $project = DB::table('projects')->where('id', $projectId)->where('company_id', $companyId)->first(['public_id', 'state', 'credit_held_amount']);

            if ($project === null || Row::text($project, 'state') !== 'ACTIVE') {
                throw Refused::rejected('C-14', ['reason' => 'not_active']);
            }

            $figures = CreditProjects::ofProjects([$projectId])[$projectId] ?? ['arisen' => '0.00', 'left' => '0.00'];
            $held = Money::of(Row::text($project, 'credit_held_amount'));

            if (! Money::of($figures['left'])->isPositive()) {
                throw Refused::rejected('C-14', ['reason' => 'nothing_left', 'left' => $figures['left']]);
            }

            if (Money::of($figures['arisen'])->compareTo($held) <= 0) {
                throw Refused::rejected('C-14', ['reason' => 'covered', 'held' => $held->toString()]);
            }

            $now = $context->now();
            $context->connection->update('update projects set credit_held_amount = ?::numeric, lock_version = lock_version + 1, updated_at = ? where id = ?', [Money::of($figures['arisen'])->toString(), $now, $projectId]);
            $sources = DB::table('payment_applications as a')->join('invoices as i', 'i.id', '=', 'a.invoice_id')->leftJoin('payments as p', 'p.id', '=', 'a.payment_id')->where('i.project_id', $projectId)
                ->distinct()->orderBy('p.public_id')->pluck('p.public_id')->filter()->values()->all();

            $this->audit->record(
                action: 'FIN.CreditHeld',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'project',
                entityId: $projectId,
                entityPublicId: Row::text($project, 'public_id'),
                reason: $reason,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['credit_held_amount' => $held->toString()], ['credit_held_amount' => Money::of($figures['arisen'])->toString()]),
                metadata: ['left' => $figures['left'], 'arisen' => $figures['arisen'], 'sources' => $sources],
            );

            return new CommandResult('project', $projectId);
        });
    }
}
