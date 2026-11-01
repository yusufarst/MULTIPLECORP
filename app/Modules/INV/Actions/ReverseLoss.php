<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\LossReversal;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseLoss` (ROUTE-196; INT-196; CM-34; AX-30's reversal) — a loss's exact reversal from its record, with MSG-40's reason: the loss contra'd, its allocation reversed, its units back only through capped LOSS_REVERSAL restorations. `stock.reverse` (ADM+); the bearer company in scope, otherwise refused and routed (AZ-08).
 */
final class ReverseLoss
{
    public const TYPE = 'INV.ReverseLoss';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly LossReversal $reversal,
    ) {}

    public function run(string $commandId, int $actorUserId, ?int $companyId, int $movementId, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['movement' => $movementId, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($movementId, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock($this->reversal->locks($movementId));

            return new CommandResult('stock_movement', $this->reversal->reverse($context, $movementId, $reason, $businessDate, $prefilled));
        });
    }
}
