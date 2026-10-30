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
 * `FUL RecordHandover` (ROUTE-208; INT-208; AX-09; WF-FUL-04; J-FUL-04) — "Catat Serah Terima" on PG-033: per service
 * line its scope performed and quantity, with the business date, the recipient and the evidence uploaded first (IP-16).
 * No stock event, ever (BR-INV-11; FS-05). `handover.record` of the project's company (CS-03); its one `command_id`
 * replayed hands over once (CI-05).
 */
final class RecordHandover
{
    public const TYPE = 'FUL.RecordHandover';

    public const CAPABILITY = 'handover.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly HandoverPosting $handovers,
    ) {}

    /**
     * @param  list<array{line: int, quantity: string, scope: string}>  $lines
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, string $businessDate, bool $prefilled, string $recipient, array $lines, ?int $evidenceId, array $acknowledged, ?string $reason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $businessDate, $prefilled, $recipient, $lines, $evidenceId, $acknowledged, $reason): CommandResult {
            $context->lock(LineScope::locks($project->id, array_column($lines, 'line'), $evidenceId));

            return new CommandResult('service_handover', $this->handovers->record($context, $project, $businessDate, $prefilled, $recipient, $lines, $evidenceId, $acknowledged, $reason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? HandoverPosting::refusalState($outcome->code, $project->id, $lines, $businessDate) : null);
    }
}
