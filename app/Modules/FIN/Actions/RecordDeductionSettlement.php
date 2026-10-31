<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\FIN\Services\SettlementPosting;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FIN RecordDeductionSettlement` (ROUTE-255; INT-255; AX-21; SF-SETTLE; WF-FIN-04; BD-08; M-37) — "Catat Potongan" with its confirmation and reason (IP-08; IP-09): a verified fee or a tax withheld or collected, as an evidenced,
 * non-cash reduction of the invoice's receivable, anchored to its payment and evidence. A residual command: no reopening of a completed project (WF-PRJ-03; L-11). `settlement.record` of the invoice's company (CS-03).
 */
final class RecordDeductionSettlement
{
    public const TYPE = 'FIN.RecordDeductionSettlement';

    public const CAPABILITY = 'settlement.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SettlementPosting $posting,
        private readonly TaxConfiguration $tax,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, int $clientId, SettlementEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry, $companyId, $clientId): CommandResult {
            $context->lock(SettlementPosting::locks($entry->invoiceId, $entry->paymentId, $entry->evidenceId));
            $treatment = null;

            if ($entry->class === SettlementEntry::TAX) {
                $treatment = $this->tax->treatmentRule($companyId, $entry->direction === SettlementEntry::COLLECTED ? 'PAYMENT_COLLECTED' : 'PAYMENT_WITHHELD', (int) $entry->taxTypeId, $entry->businessDate);
            }

            return new CommandResult('deduction_settlement', $this->posting->record($context, $entry, $companyId, $clientId, $treatment));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? SettlementPosting::replayState($outcome, $entry->invoiceId, $entry->paymentId, $entry->amount, $entry->evidenceId, $entry->class, $entry->direction) : null);
    }
}
