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
 * `INV Reserve` (ROUTE-184; INT-184; AX-01; SF-RESERVE) — "Reservasi" on a confirmed warehouse line of an ACTIVE project,
 * from PG-025 or *Item dan pemenuhan* on PG-011: the line's ACTIVE reservation created or increased by the quantity, at most
 * AVAILABLE and the line's remainder, re-read under AX-01's locks — the loser of a last-unit race REJECTED with the current
 * availability, never CONFLICT (ST-06; M-02). `stock.reserve` of the line's project's company (CS-03).
 */
final class Reserve
{
    public const TYPE = 'INV.Reserve';

    public const CAPABILITY = 'stock.reserve';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InventoryPosting $reservations,
    ) {}

    public function run(string $commandId, int $actorUserId, int $companyId, string $projectPublicId, int $lineId, string $lineHandle, string $quantity): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['project' => $projectPublicId, 'line' => $lineHandle, 'quantity' => $quantity]));

        return $this->envelope->run($request, function (CommandContext $context) use ($lineId, $quantity): CommandResult {
            $context->lock(InventoryPosting::lineLocks($lineId));

            return new CommandResult('reservation', $this->reservations->reserve($context, $lineId, $quantity));
        });
    }
}
