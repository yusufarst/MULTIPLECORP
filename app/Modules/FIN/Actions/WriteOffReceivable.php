<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
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
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN WriteOffReceivable` (ROUTE-247; INT-247; AX-24; WF-FIN-05; J-FIN-05) — "Hapuskan Piutang", the Owner's alone, with MSG-39 and MSG-40 (IP-08; IP-09): an
 * amount within the outstanding (C-15), its reason, before and after. The same client and company's unapplied credit is applied first or the reason recorded
 * (L-17). The amount leaves active receivable and collectible ageing and stays as disposed history, with no Cash-In and no profit (DIR-019; BR-FIN-08). A
 * residual command: accepted on a completed or cancelled project without reopening it (WF-PRJ-03; L-11). `receivables.write_off`, OWNER_ONLY (OD-11).
 */
final class WriteOffReceivable
{
    public const TYPE = 'FIN.WriteOffReceivable';

    public const CAPABILITY = 'receivables.write_off';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, string $amount, string $reason, ?string $creditReason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'amount' => $amount, 'reason' => $reason, 'credit_reason' => $creditReason, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $amount, $reason, $creditReason, $businessDate, $prefilled): CommandResult {
            $set = ReceivablePosting::anchors($invoice);

            foreach (MoneyPosting::creditSources($invoice->company_id, $invoice->client_organization_id) as $source) {
                $set = $set->add('application_source_balances', $source);
            }

            $context->lock($set);
            $balance = ReceivablePosting::ensureReceivable($invoice->id);
            BusinessDateRule::check($businessDate, $prefilled);
            $before = Money::of(Row::text($balance, 'outstanding'));
            $written = Money::of($amount);

            if (! $written->isPositive() || $written->compareTo($before) > 0) {
                throw Refused::rejected('C-15', ['reason' => 'above_outstanding', 'outstanding' => $before->toString()]);
            }

            $credit = MoneyPosting::availableCredit($invoice->company_id, $invoice->client_organization_id);

            if (Money::of($credit)->isPositive() && ($creditReason === null || trim($creditReason) === '')) {
                throw Refused::rejected('L-17', ['reason' => 'credit', 'credit' => $credit]);
            }

            $after = $before->minus($written)->toString();
            $id = DB::table('receivable_writeoffs')->insertGetId([
                'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'amount' => $written->toString(), 'reason' => $reason, 'outstanding_before' => $before->toString(), 'outstanding_after' => $after,
                'decided_by' => $context->request->actorUserId, 'business_date' => $businessDate, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->money->bearWriteoff($context, $id, $invoice->company_id, $written->toString());
            $this->receivable->move($context, $invoice->id, ['written_off_amount' => $written->toString()]);
            $this->records->record($context, 'FIN.ReceivableWrittenOff', AuthorityClass::OwnerOnly, $invoice->id, $reason, $businessDate,
                ['outstanding' => $after, 'written_off' => $written->toString(), ...(Money::of($credit)->isPositive() ? ['credit_available' => $credit, 'credit_not_applied_because' => $creditReason] : [])], ['outstanding' => $before->toString()]);

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
