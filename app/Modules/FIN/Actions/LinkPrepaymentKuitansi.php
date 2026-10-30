<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\PrepaymentKuitansi;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * `FIN LinkPrepaymentKuitansi` (ROUTE-250; INT-250; AX-31; CM-26; J-FIN-08) — "Tautkan Kuitansi": the Kuitansi's amount must equal the linked
 * payments' applications to its invoice plus the invoice's settlements, else REJECTED with both figures and the path — revise it, or void it and
 * issue another —; one link per (payment, invoice), money already linked receiving no second Kuitansi (C-43). `payment.record` of the invoice's
 * company.
 */
final class LinkPrepaymentKuitansi
{
    public const TYPE = 'FIN.LinkPrepaymentKuitansi';

    public const CAPABILITY = 'payment.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DocumentChain $chain,
        private readonly InvoiceRecords $records,
    ) {}

    /** @param  list<int>  $paymentIds */
    public function run(string $commandId, int $actorUserId, Invoice $invoice, Document $document, array $paymentIds, string $paymentsKey): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['document' => $document->public_id, 'payments' => $paymentsKey]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $document, $paymentIds): CommandResult {
            $issued = $this->chain->issuedOf($document->id);
            $set = (new LockSet)->add('invoices', ['id' => $invoice->id]);
            $set = $invoice->project_id === null ? $set : $set->add('projects', ['id' => $invoice->project_id]);
            $context->lock($set->union($this->chain->locks($document, $issued === null ? [] : [$issued->id])));
            $issued = $this->chain->issuedOf($document->id);

            if ($issued === null || $issued->kuitansi_amount === null) {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            $taken = DB::table('prepayment_kuitansi_links')->where('invoice_id', $invoice->id)->whereIn('payment_id', $paymentIds)->where('state', 'LINKED')->exists();

            if ($taken) {
                throw Refused::rejected('C-43', ['reason' => 'already_linked']);
            }

            $applied = DB::table('payment_applications')->where('invoice_id', $invoice->id)->where('state', 'ACTIVE')->whereIn('payment_id', $paymentIds)->distinct()->count('payment_id');

            if ($applied !== count($paymentIds)) {
                throw Refused::rejected('C-43', ['reason' => 'no_application']);
            }

            $total = PrepaymentKuitansi::linkedTotal($invoice->id, $paymentIds);

            if (! Money::of($total)->isEqualTo(Money::of($issued->kuitansi_amount))) {
                throw Refused::rejected('C-43', ['reason' => 'amount_differs', 'kuitansi' => $issued->kuitansi_amount, 'linked' => $total]);
            }

            $now = $context->now();

            foreach ($paymentIds as $number => $payment) {
                DB::table('prepayment_kuitansi_links')->insert(['company_id' => $invoice->company_id, 'document_id' => $document->id, 'invoice_id' => $invoice->id, 'payment_id' => $payment, 'state' => 'LINKED',
                    'command_id' => $context->request->commandId, 'command_line_no' => $number + 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
            }

            $this->records->record($context, 'FIN.PrepaymentKuitansiLinked', AuthorityClass::Adm, $invoice->id, null, null, ['number' => $issued->formatted_number, 'amount' => $total, 'payments' => count($paymentIds)], [],
                AuditLinkage::make()->link(AuditRelation::Affects, 'document_version', $issued->public_id));

            return new CommandResult('document', $document->id);
        });
    }
}
