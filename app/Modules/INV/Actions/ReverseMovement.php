<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\MovementReversal;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseMovement` (ROUTE-212; INT-212; NX-11; CM-28; SF-LOSS; IP-20) — "Balikkan" on PG-020 with MSG-40's reason (IP-09): the linked reversal of a wrong adjustment, condition change or count application, equal and opposite, never above the original net of earlier reversals. `stock.reverse` (ADM+); a cut also `stock.reserve`.
 */
final class ReverseMovement
{
    public const TYPE = 'INV.ReverseMovement';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly MovementReversal $reversal,
    ) {}

    /** @param  array<int, string>  $cuts  reservation => quantity */
    public function run(string $commandId, int $actorUserId, ?int $companyId, int $movementId, array $cuts, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, $companyId === null ? null : SaveQuotationDraft::companyKey($companyId), ['movement' => $movementId, 'cuts' => $cuts, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($movementId, $cuts, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock($this->reversal->locks($movementId, $cuts));

            return new CommandResult('stock_movement', $this->reversal->reverse($context, $movementId, $cuts, $reason, $businessDate, $prefilled));
        });
    }
}
