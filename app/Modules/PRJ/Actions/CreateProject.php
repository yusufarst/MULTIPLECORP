<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PRJ CreateProject` (ROUTE-151; INT-151; NX-03; WF-PRJ-01; J-PRJ-01): "Buat Proyek" in the company the form chose,
 * which the actor's `projects.manage` scope must include (CS-03) and which must be active (BR-ACC-03; C-54). Locks:
 * the command (LK-00), a linked project (LK-01), the actor (LK-03), the company (LK-06), the counter last (LK-31) —
 * the number drawn at creation and never reused, a period whose numbering has not started REJECTED (NM-08; C-10;
 * UXS-28). A prefilled business date that went stale is CONFLICT (ST-08).
 */
final class CreateProject
{
    public const TYPE = 'PRJ.CreateProject';

    public const CAPABILITY = 'projects.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, ProjectInput $input, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $input->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, self::text($input->payload['perusahaan'] ?? null), [...$input->payload, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($input, $businessDate, $prefilled): CommandResult {
            $context->lock(ProjectRecords::locks($input->linkedProjectId === null ? [] : [$input->linkedProjectId], $input->companyId));
            BusinessDateRule::check($businessDate, $prefilled);
            $project = $this->records->create($context, $input, $businessDate);

            return new CommandResult('project', $project->id);
        });
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
