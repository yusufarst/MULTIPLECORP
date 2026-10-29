<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\InventoryPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReleaseReservation` (ROUTE-185; INT-185; AX-02; J-INV-02) — "Kurangi Reservasi" by a quantity or "Lepas" of all that
 * remains, each with its reason: never above the remaining quantity at commit, so a release racing a dispatch of the same
 * reservation leaves the units consumed or released, never both (C-05). A residual reservation of a completed or cancelled
 * project is released here too (QS-16). `stock.reserve` of the reservation's company.
 */
final class ReleaseReservation
{
    public const TYPE = 'INV.ReleaseReservation';

    public const CAPABILITY = 'stock.reserve';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InventoryPosting $reservations,
    ) {}

    /** @param  ?string  $quantity  the reduction; null releases all that remains */
    public function run(string $commandId, int $actorUserId, int $companyId, int $reservationId, string $reservationHandle, ?string $quantity, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['reservation' => $reservationHandle, 'quantity' => $quantity ?? 'all', 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($reservationId, $quantity, $reason): CommandResult {
            $line = InventoryPosting::lineOf($reservationId);
            $context->lock(InventoryPosting::lineLocks($line));
            $this->reservations->reduce($context, $reservationId, $quantity, $reason);

            return new CommandResult('reservation', $reservationId);
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? InventoryPosting::reduceRefusal($reservationId, $outcome->code) : null);
    }
}
