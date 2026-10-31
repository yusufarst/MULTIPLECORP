<?php

namespace App\Modules\PUR\Actions;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `PUR RecordPurchaseCharge` (ROUTE-178; INT-178; AX-29; BD-17; SF-CHARGE; `DIR-100`) — "Catat Biaya Susulan": a charge with its source bill recorded on an existing purchase, each received lot's whole share, LATE_CHARGE draws for the units already consumed, a charge with nothing received and nothing left to receive expensed (DATABASE §6.1). `purchase.manage` of the purchase's company.
 */
final class RecordPurchaseCharge
{
    public const TYPE = 'PUR.RecordPurchaseCharge';

    public const CAPABILITY = 'purchase.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseRecords $records,
    ) {}

    /**
     * @param  array{bill: int, classification: string, amount: string, basis: ?string, values: list<?string>}  $charge
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, Purchase $purchase, array $charge, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), [...$payload, 'purchase' => $purchase->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $charge, $businessDate, $prefilled): CommandResult {
            $context->lock(PurchaseRecords::amendLocks($purchase->id)->union(EvidenceGuard::locks($charge['bill'])));
            // A receipt or charge committed while this command waited names rows it does not hold (§5 steps 3–4; LK-22; LK-23).
            $context->require(PurchaseRecords::amendLocks($purchase->id));
            BusinessDateRule::check($businessDate, $prefilled);
            $purchase->refresh();

            if ($purchase->state === 'CANCELLED') {
                throw Refused::conflict('ST-02', ['state' => $purchase->state]);
            }

            return new CommandResult('purchase_charge', $this->records->recordCharge($context, $purchase->id, $purchase->company_id, $charge, $businessDate, 1));
        });
    }
}
