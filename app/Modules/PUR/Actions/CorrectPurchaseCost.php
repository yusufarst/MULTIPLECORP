<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PUR CorrectPurchaseCost` (ROUTE-175, INT-175, "Koreksi Harga, Pajak atau Biaya"; ROUTE-179, INT-179, "Koreksi Dasar Alokasi"; AX-34; CM-17; CM-35; M-07) with MSG-40's reason (IP-09): the adjustment, the corrected value and CST's cascade in one transaction. `purchase.correct_cost` (ADM+).
 */
final class CorrectPurchaseCost
{
    public const TYPE = 'PUR.CorrectPurchaseCost';

    public const CAPABILITY = 'purchase.correct_cost';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseCorrections $corrections,
    ) {}

    /** CM-17 on a line: `kind` PRICE_CORRECTION or TAX_CORRECTION, `value` its corrected goods value or non-creditable tax. */
    public function line(string $commandId, int $actorUserId, Purchase $purchase, int $lineId, string $kind, string $value, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        return $this->envelope->run($this->request($commandId, $actorUserId, $purchase, ['line' => $lineId, 'kind' => $kind, 'value' => $value, 'reason' => $reason, 'date' => $businessDate]), function (CommandContext $context) use ($purchase, $lineId, $kind, $value, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(PurchaseRecords::amendLocks($purchase->id));
            $this->corrections->correctLine($context, $purchase, $lineId, $kind, $value, $reason, $businessDate, $prefilled);

            return new CommandResult('purchase', $purchase->id);
        });
    }

    /**
     * CM-17's charge amount or CM-35's basis: a new version of its shares and the cascade.
     *
     * @param  list<?string>  $values  a weight or an explicit split, in line order
     */
    public function charge(string $commandId, int $actorUserId, Purchase $purchase, int $chargeId, ?string $amount, ?string $basis, array $values, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        return $this->envelope->run($this->request($commandId, $actorUserId, $purchase, ['charge' => $chargeId, 'amount' => $amount, 'basis' => $basis, 'values' => $values, 'reason' => $reason, 'date' => $businessDate]), function (CommandContext $context) use ($purchase, $chargeId, $amount, $basis, $values, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(PurchaseRecords::amendLocks($purchase->id));
            $this->corrections->correctCharge($context, $purchase, $chargeId, $amount, $basis, $values, $reason, $businessDate, $prefilled);

            return new CommandResult('purchase', $purchase->id);
        });
    }

    /** @param  array<string, mixed>  $payload */
    private function request(string $commandId, int $actorUserId, Purchase $purchase, array $payload): CommandRequest
    {
        return new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), [...$payload, 'purchase' => $purchase->public_id]));
    }
}
