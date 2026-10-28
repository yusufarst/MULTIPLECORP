<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\LineScope;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ SplitReplanLine` (ROUTE-160; INT-160; WF-FUL-01; L-21; AX-14) — "Ubah Cara Pemenuhan" with its reason: only a
 * confirmed line's unstarted remainder becomes a new line of another mode with the same commercial snapshot and its
 * pin, `split_from_line_id` naming its origin, the remainder's reservation released — without re-approval. A line
 * whose remainder is all started, a service line or the same mode is REJECTED. `projects.manage` of the project's
 * company; releasing a reservation also `stock.reserve` (W4-23), which INV's participant checks.
 */
final class SplitReplanLine
{
    public const TYPE = 'PRJ.SplitReplanLine';

    public const CAPABILITY = 'projects.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly LineScope $scope,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project, int $lineId, string $lineHandle, string $mode, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->company_id), ['project' => $project->public_id, 'line' => $lineHandle, 'mode' => $mode, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $lineId, $mode, $reason): CommandResult {
            $context->lock(LineScope::locks($project->id, [$lineId]));

            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            return new CommandResult('project_line', $this->scope->split($context, $project->id, $project->company_id, $lineId, $mode, $reason));
        });
    }
}
