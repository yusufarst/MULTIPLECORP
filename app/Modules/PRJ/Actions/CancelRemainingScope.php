<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\LineScope;
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
 * `PRJ CancelRemainingScope` (ROUTE-159; INT-159; SF-CANCEL-LINE; AX-14; J-PRJ-04) — "Batalkan Sisa" after its
 * confirmation with MSG-40's reason (IP-09): the unfilled remainder of one or several confirmed lines cancelled in
 * place, the remainder's reservation released in the same action (IP-20), never below what is shipped, confirmed to the
 * client or handed over (PX-08). Received goods stay free stock and paid money returns as a supplier refund (ADMIN_FLOW
 * §11); cancelling the last remainder completes nothing (L-12). `projects.cancel_scope` (ADM+) of the project's company.
 */
final class CancelRemainingScope
{
    public const TYPE = 'PRJ.CancelRemainingScope';

    public const CAPABILITY = 'projects.cancel_scope';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly LineScope $scope,
    ) {}

    /**
     * @param  array<int, string>  $quantities  base quantity by project line
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, Project $project, array $quantities, string $reason, string $businessDate, bool $prefilled, ?int $evidenceId, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->company_id), [...$payload, 'project' => $project->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $quantities, $reason, $businessDate, $prefilled, $evidenceId): CommandResult {
            $context->lock(LineScope::locks($project->id, array_keys($quantities), $evidenceId));

            // L-11: after completion or cancellation a scope change is not a residual command.
            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            BusinessDateRule::check($businessDate, $prefilled);
            $this->scope->cancel($context, $project->id, $project->company_id, $quantities, $reason, $businessDate, $evidenceId);

            return new CommandResult('project', $project->id);
        });
    }
}
