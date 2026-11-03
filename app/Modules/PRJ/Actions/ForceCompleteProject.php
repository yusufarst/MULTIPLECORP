<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Queries\ProjectCompletion;
use App\Modules\PRJ\Services\CompletionRecords;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;

/**
 * `PRJ ForceCompleteProject` (ROUTE-162; INT-162; AX-26; UXS-37; BR-PRJ-04; C-38; OD-12) — "Selesaikan Paksa", the Owner's only: the snapshot of the unmet
 * conditions and residual obligations recomputed under the project's LK-01 and compared with the hash the Owner confirmed — a changed snapshot is CONFLICT
 * with the current one to confirm again (ST-04; M-27) —; then COMPLETED_FORCED with its FORCE_COMPLETE transition holding the snapshot, the unmet
 * predicates, the residual obligations, the reason and the confirmed hash. Nothing is fabricated: every obligation stays.
 */
final class ForceCompleteProject
{
    public const TYPE = 'PRJ.ForceCompleteProject';

    public const CAPABILITY = 'projects.force_complete';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectCompletion $completion,
        private readonly CompletionRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, int $projectId, int $companyId, string $projectPublicId, string $reason, string $hash): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['project' => $projectPublicId, 'reason' => $reason, 'hash' => $hash]));

        return $this->envelope->run($request, function (CommandContext $context) use ($projectId, $companyId, $reason, $hash): CommandResult {
            $context->lock(Revalidation::locks($projectId));
            $project = CompletionRecords::active($projectId, $companyId);
            $read = $this->completion->read($projectId);

            // ST-04: the Owner confirmed what was shown; anything that changed since is shown again (UXS-37).
            if (! hash_equals($read['hash'], $hash)) {
                throw Refused::conflict('ST-04', ['hash' => $read['hash'], 'unmet' => $read['unmet']]);
            }

            $this->records->complete($context, $projectId, $companyId, Row::text($project, 'public_id'), $read['snapshot'], $read['unmet'], ['reason' => $reason, 'hash' => $hash]);

            return new CommandResult('project', $projectId);
        });
    }
}
