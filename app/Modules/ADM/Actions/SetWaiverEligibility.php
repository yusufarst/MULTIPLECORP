<?php

namespace App\Modules\ADM\Actions;

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
 * `ADM SetWaiverEligibility` (ROUTE-167; INT-167; OD-05; BR-PRJ-03; NX-03): the Owner makes an item waiver-eligible or
 * not, at the version read (ST-01); every item is non-waivable until then. An OWNER_ONLY audit event that reaches the
 * Owner review queue (AZ-10). `requirements.set_waiver_eligibility` (OWNER_ONLY); the project at LK-01.
 */
final class SetWaiverEligibility
{
    public const TYPE = 'ADM.SetWaiverEligibility';

    public const CAPABILITY = 'requirements.set_waiver_eligibility';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
    ) {}

    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $requirementId, int $lockVersion, bool $eligible): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'requirement' => $requirementId, 'lock_version' => $lockVersion, 'eligible' => $eligible]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $requirementId, $lockVersion, $eligible): CommandResult {
            $context->lock(Requirements::locks($project->id));
            Requirements::open($project->id);
            $current = Requirements::find($project->id, $requirementId) ?? throw Refused::conflict('ST-02', ['state' => 'REMOVED']);

            if ($current['default_invoice']) {
                throw Refused::rejected('WF-ADM-01', ['reason' => 'invoice']);
            }

            $this->requirements->eligibility($context, $current, $lockVersion, $eligible);

            return new CommandResult('admin_requirement', $requirementId);
        });
    }
}
