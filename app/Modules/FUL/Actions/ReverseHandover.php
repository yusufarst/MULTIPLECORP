<?php

namespace App\Modules\FUL\Actions;

use App\Modules\FUL\Services\HandoverPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\LineScope;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FUL ReverseHandover` (ROUTE-209; INT-209; AX-09; CM-27) — "Balikkan" on PG-033 with MSG-40's reason (IP-09): the
 * exact reversal line of a handover's line recorded in error, at most once, the record kept (C-30). A likely duplicate
 * cannot arise: a second reversal of the line is refused by its one-reversal key. `fulfillment.correct` (ADM+) of the
 * project's company, in the Owner's review (AZ-10; QS-14).
 */
final class ReverseHandover
{
    public const TYPE = 'FUL.ReverseHandover';

    public const CAPABILITY = 'fulfillment.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly HandoverPosting $handovers,
    ) {}

    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $handoverId, string $handoverPublicId, int $lineId, int $projectLineId, string $lineHandle, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'handover' => $handoverPublicId, 'line' => $lineHandle, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $handoverId, $handoverPublicId, $lineId, $projectLineId, $reason): CommandResult {
            $context->lock(LineScope::locks($project->id, [$projectLineId]));

            return new CommandResult('service_handover_line', $this->handovers->reverse($context, $project, $handoverId, $handoverPublicId, $lineId, $reason));
        });
    }
}
