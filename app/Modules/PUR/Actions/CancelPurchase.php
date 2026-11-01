<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PUR CancelPurchase` (ROUTE-173; INT-173; AX-12; CM-18; `DIR-101`) — "Batalkan Pembelian" with MSG-40's reason (IP-09): only with nothing received or confirmed and no performed or paid non-stock line; its number kept, every line closed to its ordered quantity, its non-stock HPP contra'd, its charges expensed and its PO voided, in one transaction. `purchase.cancel` (ADM+).
 */
final class CancelPurchase
{
    public const TYPE = 'PUR.CancelPurchase';

    public const CAPABILITY = 'purchase.cancel';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseCorrections $corrections,
    ) {}

    public function run(string $commandId, int $actorUserId, Purchase $purchase, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), ['purchase' => $purchase->public_id, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock($this->corrections->cancelLocks($purchase));
            $this->corrections->cancel($context, $purchase, $reason, $businessDate, $prefilled);

            return new CommandResult('purchase', $purchase->id);
        });
    }
}
