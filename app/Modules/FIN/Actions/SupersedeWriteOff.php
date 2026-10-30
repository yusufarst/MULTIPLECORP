<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN SupersedeWriteOff` (ROUTE-248; INT-248; AX-24; L-17; WF-FIN-05) — "Terapkan dan Cabut Penghapusan", the Owner's alone, with MSG-40's reason (IP-09): money
 * recovered after a write-off — a real payment, its unapplied credit linked to the written-off invoice (QS-12; QS-14) — applied to the invoice in the same command
 * as the write-off's supersession by that amount: a `financial_contras` row on it (DATABASE §11.4), the original kept as disposed history. The outstanding does not
 * move: the written-off amount falls as the applied amount rises. A residual command: no reopening of a completed project (WF-PRJ-03; L-11). `writeoff.supersede`, OWNER_ONLY (OD-11).
 */
final class SupersedeWriteOff
{
    public const TYPE = 'FIN.SupersedeWriteOff';

    public const CAPABILITY = 'writeoff.supersede';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, int $writeoffId, int $paymentId, string $amount, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'writeoff' => $writeoffId, 'payment' => $paymentId, 'amount' => $amount, 'reason' => $reason, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $writeoffId, $paymentId, $amount, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(ReceivablePosting::anchors($invoice)
                ->add('application_source_balances', ['payment_id' => $paymentId])
                ->add('money_fact_balances', ['receivable_writeoff_id' => $writeoffId]));
            ReceivablePosting::ensureIssued($invoice->id);
            BusinessDateRule::check($businessDate, $prefilled);
            $payment = DB::table('payments')->where('id', $paymentId)->first(['public_id', 'company_id', 'client_organization_id', 'recovery_for_invoice_id']);

            // Credit linked to this invoice: money recovered for it, of its client and company (BR-FIN-07; C-14).
            $recoveredFor = $payment === null ? null : Row::nullable($payment, 'recovery_for_invoice_id');

            if ($recoveredFor === null || ! in_array((int) $recoveredFor, MoneyPosting::recoveryTargets($invoice->id), true)) {
                throw Refused::rejected('L-17', ['reason' => 'not_recovery']);
            }

            $moved = Money::of($amount);
            $standing = MoneyPosting::writeoffStanding($writeoffId);
            $capacity = MoneyPosting::paymentCapacity($paymentId);

            if (! $moved->isPositive() || $moved->compareTo(Money::of($standing)) > 0 || $moved->compareTo(Money::of($capacity)) > 0) {
                throw Refused::rejected('C-15', ['reason' => 'above_recovered', 'standing' => $standing, 'capacity' => $capacity]);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $commandId = $context->request->commandId;
            DB::table('financial_contras')->insert(['company_id' => $invoice->company_id, 'receivable_writeoff_id' => $writeoffId, 'amount' => $moved->toString(), 'reason' => $reason, 'business_date' => $businessDate,
                'recorded_by' => $actor, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now]);
            DB::table('payment_applications')->insert(['company_id' => $invoice->company_id, 'client_organization_id' => $invoice->client_organization_id, 'payment_id' => $paymentId,
                'invoice_id' => $invoice->id, 'target_kind' => 'INVOICE', 'amount' => $moved->toString(), 'business_date' => $businessDate, 'reason' => $reason,
                'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
            $this->money->contraWriteoff($context, $writeoffId, $moved->toString());
            $this->money->applyFromPayment($context, $paymentId, $moved->toString());
            $this->receivable->move($context, $invoice->id, ['written_off_amount' => $moved->negated()->toString(), 'applied_amount' => $moved->toString()]);
            $this->records->record($context, 'FIN.WriteOffSuperseded', AuthorityClass::OwnerOnly, $invoice->id, $reason, $businessDate,
                ['writeoff' => RowHandle::of('receivable_writeoffs', $writeoffId), 'written_off_standing' => Money::of($standing)->minus($moved)->toString(), 'applied' => $moved->toString()], ['writeoff' => RowHandle::of('receivable_writeoffs', $writeoffId), 'written_off_standing' => $standing],
                AuditLinkage::make()->link(AuditRelation::Affects, 'payment', Row::text($payment, 'public_id')));

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
