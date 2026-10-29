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
 * `ADM RelaxRequirement · RemoveRequirement` (ROUTE-168; INT-168; OD-06; DIR-024 D-5; NX-03): the Owner makes a required
 * item optional or looser, clears its client-original flag, or removes it — each with its reason (IP-09) at the version
 * read (ST-01), an OWNER_ONLY audit event reaching the Owner review queue (AZ-10). The default invoice item stays
 * (L-27). `requirements.relax` (OWNER_ONLY); the project at LK-01.
 */
final class RelaxRequirement
{
    public const RELAX = 'ADM.RelaxRequirement';

    public const REMOVE = 'ADM.RemoveRequirement';

    public const CAPABILITY = 'requirements.relax';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
    ) {}

    /** @param  ?array{required: bool, mode: string, client_original: bool}  $change  the relaxed item, or null to remove it */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $requirementId, int $lockVersion, ?array $change, string $reason): Outcome
    {
        $type = $change === null ? self::REMOVE : self::RELAX;
        $request = new CommandRequest($commandId, $type, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of($type, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'requirement' => $requirementId,
                'lock_version' => $lockVersion, 'change' => $change, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $requirementId, $lockVersion, $change, $reason): CommandResult {
            $context->lock(Requirements::locks($project->id));
            Requirements::open($project->id);
            $current = Requirements::find($project->id, $requirementId) ?? throw Refused::conflict('ST-02', ['state' => 'REMOVED']);

            if ($current['default_invoice']) {
                throw Refused::rejected('WF-ADM-01', ['reason' => 'invoice']);
            }

            if ($change === null) {
                if ($current['required'] && $current['waiver_eligible']) {
                    // WF-ADM-01: a required waiver-eligible item is handled by Tidak Berlaku, never by removal.
                    throw Refused::rejected('WF-ADM-01', ['reason' => 'waivable']);
                }

                $this->requirements->remove($context, $current, $lockVersion, $reason);
            } else {
                $this->requirements->change($context, $current, $lockVersion, $change, true, $reason);
            }

            return new CommandResult('admin_requirement', $requirementId);
        });
    }
}
