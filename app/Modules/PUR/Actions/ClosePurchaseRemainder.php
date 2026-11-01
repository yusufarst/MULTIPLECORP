<?php

namespace App\Modules\PUR\Actions;

use App\Modules\CST\Services\CostPosting;
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
 * `PUR ClosePurchaseRemainder` (ROUTE-174; INT-174; AX-32; BR-PUR-04; L-47; PX-04; M-36) — "Tutup Sisa Pembelian" with MSG-40's reason (IP-09): the open remainder of one or several lines closed, one closure per line per command, each line's pending charge shares re-spread in the same action. `purchase.close_remainder` (ADM+).
 */
final class ClosePurchaseRemainder
{
    public const TYPE = 'PUR.ClosePurchaseRemainder';

    public const CAPABILITY = 'purchase.close_remainder';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseCorrections $corrections,
    ) {}

    /** @param  array<int, string>  $closures  line id => quantity in base units */
    public function run(string $commandId, int $actorUserId, Purchase $purchase, array $closures, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), ['purchase' => $purchase->public_id, 'closures' => $closures, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $closures, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(CostPosting::purchaseLocks($purchase->id));
            $this->corrections->close($context, $purchase, $closures, $reason, $businessDate, $prefilled);

            return new CommandResult('purchase', $purchase->id);
        });
    }
}
