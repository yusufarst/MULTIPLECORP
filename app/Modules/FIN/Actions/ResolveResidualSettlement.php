<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Services\PaymentApplications;
use App\Modules\FIN\Services\SettlementPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN ResolveResidualSettlement` (ROUTE-263; INT-263; AX-33; DATABASE §11.4; C-60; CM-24; `DIR-100`) — "Selesaikan Sisa Potongan" with its confirmation and reason: a RESIDUAL settlement's exit, re-recorded on the invoice its
 * evidence covers or contra'd on the issuer's evidence, once, the case's residual falling by its amount. `settlement.record` (PERMISSIONS_MATRIX §4) of the settlement's company.
 */
final class ResolveResidualSettlement
{
    public const TYPE = 'FIN.ResolveResidualSettlement';

    public const CAPABILITY = 'settlement.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SettlementPosting $posting,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, int $settlementId, ?int $invoiceId, ?int $paymentId, ?int $issuerEvidenceId, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($settlementId, $invoiceId, $paymentId, $reason, $issuerEvidenceId, $businessDate, $prefilled): CommandResult {
            $locks = function () use ($settlementId, $invoiceId, $paymentId, $issuerEvidenceId): LockSet {
                $set = SettlementPosting::contraLocks($settlementId);
                $set = $issuerEvidenceId === null ? $set : $set->add('evidence_documents', ['id' => $issuerEvidenceId]);

                return $invoiceId === null || $paymentId === null ? $set : $set->union(PaymentApplications::invoiceLocks([$invoiceId]))->add('application_source_balances', ['payment_id' => $paymentId]);
            };
            $context->lock($locks());
            $context->require($locks());

            return new CommandResult('deduction_settlement', $this->posting->resolve($context, $settlementId, $invoiceId, $paymentId, $issuerEvidenceId, $reason, $businessDate, $prefilled));
        }, null, function (Outcome $outcome) use ($settlementId, $invoiceId, $paymentId): ?array {
            if ($outcome->kind !== Outcome::REJECTED) {
                return null;
            }

            if ($outcome->code === 'C-60') {
                return ['reason' => $invoiceId === null ? 'no_issuer_evidence' : 'not_residual'];
            }

            $row = DB::table('deduction_settlements')->where('id', $settlementId)->first(['amount', 'settlement_class', 'tax_direction']);

            return $row === null ? null : SettlementPosting::replayState($outcome, $invoiceId, $paymentId, Row::text($row, 'amount'), null, Row::text($row, 'settlement_class'), Row::nullable($row, 'tax_direction'));
        });
    }
}
