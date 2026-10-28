<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ DiscardDraftProject` (ROUTE-153; INT-153; NX-03; L-37): "Buang Draf" after its confirmation (IP-08; MSG-39) — a
 * DRAFT with no committed downstream record removed with its lines and SIPLAH details, its number kept as a
 * `retired_numbers` tombstone in the same transaction and never drawn again (C-10), its last content in the audit
 * event. Refused once the project is not a DRAFT or a committed record references it.
 */
final class DiscardDraftProject
{
    public const TYPE = 'PRJ.DiscardDraftProject';

    public const CAPABILITY = 'projects.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project): Outcome
    {
        $company = DB::table('companies')->where('id', $project->company_id)->value('public_id');
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, is_string($company) ? $company : null, ['project' => $project->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project): CommandResult {
            $context->lock(ProjectRecords::locks([$project->id]));
            $this->records->discard($context, $project, 'Draft discarded by its Admin (L-37).');

            return new CommandResult('project', $project->id);
        });
    }
}
