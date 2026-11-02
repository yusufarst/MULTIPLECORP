<?php

namespace App\Modules\FUL\Actions;

use App\Modules\FUL\Services\DeliveryPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FUL RecordDelivery` (ROUTE-201; INT-201; AX-09; WF-FUL-02; J-FUL-02) — "Catat Pengiriman" on PG-031: per shipment line
 * the quantity that reached the client, with the business date, the recipient, the condition and the proof uploaded first
 * (IP-16), a difference or condition problem opening its discrepancy case. No stock event, ever (BR-INV-02; FS-03).
 * `delivery.record` of the project's company (CS-03); its one `command_id` replayed delivers once (CI-05; AC-07).
 */
final class RecordDelivery
{
    public const TYPE = 'FUL.RecordDelivery';

    public const CAPABILITY = 'delivery.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DeliveryPosting $deliveries,
    ) {}

    /**
     * @param  list<array{shipment: int, line: int, quantity: string}>  $lines  each shipment line with its project line
     * @param  array{inspector: string, result: string}|null  $inspection
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, string $businessDate, bool $prefilled, string $recipient, ?string $condition, ?string $discrepancy, ?array $inspection, array $lines, int $evidenceId, array $acknowledged, ?string $reason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId]));
        $entered = array_map(fn (array $line): array => ['shipment' => $line['shipment'], 'quantity' => $line['quantity']], $lines);

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $businessDate, $prefilled, $recipient, $condition, $discrepancy, $inspection, $lines, $entered, $evidenceId, $acknowledged, $reason): CommandResult {
            $context->lock(DeliveryPosting::locks($project->id, array_column($lines, 'line'), $evidenceId));

            return new CommandResult('delivery', $this->deliveries->record($context, $project, $businessDate, $prefilled, $recipient, $condition, $discrepancy, $inspection, $entered, $evidenceId, $acknowledged, $reason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? DeliveryPosting::refusalState($outcome->code, $entered, $businessDate) : null);
    }
}
