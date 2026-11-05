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
 * `INV ReverseOpeningMovement` (ROUTE-213; INT-213; NX-11; CM-33; IP-09) — "Koreksi Data Awal" on an opening stock fact with MSG-40's reason: the OPENING
 * movement reversed by its import identity, within the unconsumed quantity, reservations cut first when needed
 * (SF-RSV-CUT), the lot's opening basis with it; a wrong opening fact is never imported again. `stock.reverse` (ADM+);
 * a cut also `stock.reserve`.
 */
final class ReverseOpeningMovement
{
    public const TYPE = 'INV.ReverseOpeningMovement';

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

            return new CommandResult('stock_movement', $this->reversal->reverseOpening($context, $movementId, $cuts, $reason, $businessDate, $prefilled));
        });
    }
}
