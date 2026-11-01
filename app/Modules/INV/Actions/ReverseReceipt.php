<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\ReceivingRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseReceipt` (ROUTE-183; INT-183; AX-05; CM-02; CM-03; SF-CHARGE; IP-20) — "Balikkan Penerimaan" with MSG-40's reason (IP-09): only the lot's unconsumed units, its charge share re-spread as never received, the serials NEVER_RECEIVED, the actor's cut where usable supply would fall below the reservations. `stock.reverse` (ADM+); a cut also `stock.reserve`.
 */
final class ReverseReceipt
{
    public const TYPE = 'INV.ReverseReceipt';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReceivingRecords $records,
    ) {}

    /**
     * @param  list<array{location: int, condition: string, qty: string, serial: ?int}>  $removals
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function run(string $commandId, int $actorUserId, int $companyId, int $lotId, array $removals, array $cuts, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['lot' => $lotId, 'removals' => $removals, 'cuts' => $cuts, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($lotId, $removals, $cuts, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock($this->records->reversalLocks($lotId, $cuts));

            return new CommandResult('stock_movement', $this->records->reverse($context, $lotId, $removals, $cuts, $reason, $businessDate, $prefilled));
        });
    }
}
