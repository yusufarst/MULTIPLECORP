<?php

namespace App\Modules\FUL\Actions;

use App\Modules\FUL\Services\DropshipPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FUL ConfirmDropShip` (ROUTE-205; INT-205; AX-08; WF-FUL-03; J-FUL-03) — "Konfirmasi Kirim Langsung" on PG-032: the
 * supplier's shipment of the project's direct-to-client purchase lines, its reference, quantities, serials and proof, the
 * actual cost attributed at once, and the delivery in the same command when the client's proof arrived with it (L-40).
 * `dropship.confirm` (ADM) of the project's company (CS-03); its one `command_id` replayed confirms once (CI-05).
 */
final class ConfirmDropShip
{
    public const TYPE = 'FUL.ConfirmDropShip';

    public const CAPABILITY = 'dropship.confirm';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DropshipPosting $dropship,
    ) {}

    /**
     * @param  list<array{line: int, quantity: string, serials: list<string>, serialized: bool, scale: int}>  $lines
     * @param  array{recipient: string, condition: ?string, proof: int}|null  $delivery
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $purchaseId, string $reference, string $businessDate, bool $prefilled, array $lines, int $evidenceId, ?int $caseId, ?array $delivery, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $purchaseId, $reference, $businessDate, $prefilled, $lines, $evidenceId, $caseId, $delivery): CommandResult {
            $serials = array_merge(...array_map(fn (array $line): array => $line['serials'], $lines));
            $context->lock(DropshipPosting::locks($project->id, $purchaseId, array_column($lines, 'line'), $serials, array_values(array_filter([$evidenceId, $delivery['proof'] ?? null])), $caseId));

            return new CommandResult('dropship_confirmation', $this->dropship->confirm($context, $project, $purchaseId, $reference, $businessDate, $prefilled, $lines, $evidenceId, $caseId, $delivery));
        });
    }
}
