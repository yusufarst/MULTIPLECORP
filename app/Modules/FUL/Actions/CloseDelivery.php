<?php

namespace App\Modules\FUL\Actions;

use App\Modules\FUL\Services\DeliveryClosures;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * `FUL CloseDelivery` (ROUTE-203; INT-203; NX-15; AX-30; CM-06; CM-07; CM-27) — "Tutup Pengiriman" for shipped quantity
 * that will not be delivered, with its reason, and "Catat Hilang di Perjalanan" for the quantity lost in transit with its
 * loss reclassification. `delivery.close` (ADM_PLUS) of the project's company; a loss in transit also `stock.adjust`
 * (ADM_PLUS) there, its bearer the shipment's project, the command's own company (AZ-08); one `command_id` replayed closes
 * once (CI-05).
 */
final class CloseDelivery
{
    public const TYPE = 'FUL.CloseDelivery';

    public const CAPABILITY = 'delivery.close';

    public const LOSS = 'stock.adjust';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DeliveryClosures $closures,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /**
     * @param  list<int>  $serials  a serialized shipment's lost serials
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $dispatchLineId, string $kind, string $quantity, array $serials, ?int $supersedes, string $reason, string $businessDate, bool $prefilled, array $payload, array $acknowledged = [], ?string $duplicateReason = null): Outcome
    {
        $lost = $kind === DeliveryClosures::LOST;

        // A loss is a cost effect on the shipment's project: the company's `stock.adjust` beside the closure's capability (RG-06), refused as the envelope refuses its own.
        if ($lost && ! $this->capabilities->for($actorUserId)->allows(self::LOSS, $project->companyId)) {
            throw new AuthorizationException;
        }

        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId, 'target' => $dispatchLineId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $dispatchLineId, $kind, $quantity, $serials, $supersedes, $reason, $businessDate, $prefilled, $lost, $acknowledged, $duplicateReason): CommandResult {
            $context->lock(DeliveryClosures::locks($dispatchLineId, $lost, $serials));

            return new CommandResult('delivery_closure', $this->closures->close($context, $project, $dispatchLineId, $kind, $quantity, $serials, $supersedes, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'L-49' ? ['matches' => DeliveryClosures::likelyDuplicates($dispatchLineId, $kind, $quantity, $businessDate)] : null);
    }
}
