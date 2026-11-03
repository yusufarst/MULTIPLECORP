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
 * `PRJ CompleteProject` (ROUTE-161; INT-161; AX-25; WF-PRJ-03; L-12; C-39) — "Selesaikan Proyek": the explicit decision of the Owner or an Admin with
 * `projects.complete`, never automatic. Under the project's LK-01 the ten predicates are re-read; COMPLETED_NORMAL with its COMPLETE_NORMAL transition
 * only when all ten hold at commit, otherwise REJECTED with the unmet predicates and nothing written.
 */
final class CompleteProject
{
    public const TYPE = 'PRJ.CompleteProject';

    public const CAPABILITY = 'projects.complete';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectCompletion $completion,
        private readonly CompletionRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, int $projectId, int $companyId, string $projectPublicId): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['project' => $projectPublicId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($projectId, $companyId): CommandResult {
            $context->lock(Revalidation::locks($projectId));
            $project = CompletionRecords::active($projectId, $companyId);
            // AX-25: the predicates as they stand once no other command on the project can change them.
            $read = $this->completion->read($projectId);

            if ($read['unmet'] !== []) {
                throw Refused::rejected('C-39', ['unmet' => $read['unmet']]);
            }

            $this->records->complete($context, $projectId, $companyId, Row::text($project, 'public_id'), $read['snapshot'], [], null);

            return new CommandResult('project', $projectId);
        });
    }
}
