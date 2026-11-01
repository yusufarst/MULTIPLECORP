<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\DispatchRestoration;
use App\Modules\INV\Services\IdentitySubstitution;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV SubstituteDispatchIdentity` (ROUTE-189; INT-189; AX-35; CM-36) — "Koreksi Nomor Seri atau Lot" with its reason (IP-09) and evidence. `stock.reverse` (ADM+); onto another company's lot also `stock.allocate_intercompany` (XL-1).
 */
final class SubstituteDispatchIdentity
{
    public const TYPE = 'INV.SubstituteDispatchIdentity';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly IdentitySubstitution $substitution,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, int $consumptionId, string $quantity, ?int $recordedSerial, int $shippedLot, int $shippedLocation, ?int $shippedSerial, ?string $allocationReason, int $evidence, string $reason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($consumptionId, $quantity, $recordedSerial, $shippedLot, $shippedLocation, $shippedSerial, $allocationReason, $evidence, $reason, $businessDate, $prefilled, $serializedSeen, $scaleSeen): CommandResult {
            $context->lock($this->substitution->locks($consumptionId, $shippedLot, $shippedLocation, $recordedSerial, $shippedSerial, $evidence));

            return new CommandResult('stock_movement', $this->substitution->substitute($context, $consumptionId, $quantity, $recordedSerial, $shippedLot, $shippedLocation, $shippedSerial, $allocationReason, $evidence, $reason, $businessDate, $prefilled, $serializedSeen, $scaleSeen));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'C-27' ? ['open' => DispatchRestoration::openOf($consumptionId)] : null);
    }
}
