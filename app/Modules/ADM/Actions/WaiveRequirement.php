<?php

namespace App\Modules\ADM\Actions;

use App\Modules\ADM\Services\Readiness;
use App\Modules\ADM\Services\Requirements;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `ADM WaiveRequirement` (ROUTE-166; INT-166; BR-ADM-02; NX-03) — "Tandai Tidak Berlaku" with MSG-40's reason (IP-09):
 * only on a waiver-eligible item, which the Owner made so (OD-05), refused on any other; a required waiver-eligible item
 * is handled so and never by removal (WF-ADM-01). An item already *Tidak Berlaku* answers CONFLICT. `requirements.waive`
 * of the project's company; the project at LK-01.
 */
final class WaiveRequirement
{
    public const TYPE = 'ADM.WaiveRequirement';

    public const CAPABILITY = 'requirements.waive';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
    ) {}

    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $requirementId, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'requirement' => $requirementId, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $requirementId, $reason): CommandResult {
            $context->lock(Requirements::locks($project->id));
            Requirements::open($project->id);
            $current = Requirements::find($project->id, $requirementId) ?? throw Refused::conflict('ST-02', ['state' => 'REMOVED']);

            if (! $current['waiver_eligible'] || $current['default_invoice']) {
                throw Refused::rejected('BR-ADM-02', ['reason' => 'ineligible']);
            }

            if ($current['status'] === Readiness::WAIVED) {
                throw Refused::conflict('ST-02', ['state' => Readiness::WAIVED]);
            }

            return new CommandResult('admin_requirement_waiver', $this->requirements->waive($context, $current, $reason));
        });
    }
}
