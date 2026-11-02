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
 * `FUL CorrectDelivery` (ROUTE-204; INT-204; AX-09; CM-08) — "Koreksi Catatan Pengiriman" for an entry error: the exact
 * reversal of the delivery's line, then the correct quantity on the same delivery, with its reason; stock untouched.
 * `fulfillment.correct` (ADM_PLUS) of the project's company; one `command_id` replayed corrects once (CI-05).
 */
final class CorrectDelivery
{
    public const TYPE = 'FUL.CorrectDelivery';

    public const CAPABILITY = 'fulfillment.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DeliveryPosting $deliveries,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     * @param  list<string>  $acknowledged  the likely duplicates of the correct line the actor confirms (L-49)
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $deliveryId, int $lineId, ?string $quantity, string $reason, string $businessDate, bool $prefilled, array $payload, array $acknowledged = [], ?string $duplicateReason = null): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId, 'target' => $lineId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $deliveryId, $lineId, $quantity, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason): CommandResult {
            $context->lock(DeliveryPosting::correctionLocks($project->id, $lineId));

            return new CommandResult('delivery', $this->deliveries->correct($context, $project, $deliveryId, $lineId, $quantity, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason));
        });
    }
}
