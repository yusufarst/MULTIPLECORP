<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentSubjects;
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
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN IssuePrepaymentKuitansi` (ROUTE-249; INT-249; AX-31; WF-FIN-08; J-FIN-08) — "Terbitkan Kuitansi untuk Proses Pembayaran", from the draft
 * ROUTE-221 prepared with the amount entered, on PG-036 after the confirmation with its reason (`DIR-081` OB-02): Σ open pre-payment Kuitansi on
 * the invoice including this one ≤ its outstanding, under the invoice's guard (C-43). It creates no Cash-In, payment, settlement or paid state
 * and leaves the position block unchanged (GAP-029; FS-04). `documents.issue` with `finance.view` of the invoice's company.
 */
final class IssuePrepaymentKuitansi
{
    public const TYPE = 'FIN.IssuePrepaymentKuitansi';

    public const CAPABILITY = 'documents.issue';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentIssuing $issuing,
        private readonly DocumentChain $chain,
        private readonly InvoiceRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, Document $document, int $lockVersion, string $draftId, string $businessDate, bool $prefilled, ?string $factsDigest, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['document' => $document->public_id, 'draft' => $draftId, 'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'facts' => $factsDigest, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $document, $lockVersion, $draftId, $businessDate, $prefilled, $factsDigest, $reason): CommandResult {
            $subject = DocumentSubject::of($document);
            $provider = $this->subjects->of($subject);
            $context->lock($this->issuing->locks($document, $subject, $provider->locks($subject)));
            $draft = $this->chain->draftOf($document->id);

            if ($draft !== null && $draft->kuitansi_amount !== null) {
                $balance = DB::table('invoice_balances')->where('invoice_id', $invoice->id)->value('outstanding');
                $outstanding = is_scalar($balance) ? (string) $balance : '0.00';
                $open = PrepaymentKuitansi::openTotal($invoice->id, $document->id);

                // C-43 under LK-25: both issue modes hold the invoice's guard, so different numbering periods cannot race past it.
                if (Money::of($open)->plus(Money::of($draft->kuitansi_amount))->compareTo(Money::of($outstanding)) > 0) {
                    throw Refused::rejected('C-43', ['reason' => 'above_outstanding', 'outstanding' => $outstanding, 'open' => $open, 'amount' => $draft->kuitansi_amount]);
                }
            }

            $version = $this->issuing->issue($context, $document, $subject, $provider, $lockVersion, $businessDate, $prefilled, null, $factsDigest, $draftId);
            IssueDocument::render($context, $version);
            $this->records->record($context, 'FIN.PrepaymentKuitansiIssued', AuthorityClass::Adm, $invoice->id, $reason, $businessDate,
                ['number' => $version->formatted_number, 'amount' => $version->kuitansi_amount], [], AuditLinkage::make()->link(AuditRelation::Affects, 'document_version', $version->public_id));

            return new CommandResult('document', $document->id);
        });
    }

    /** The Kuitansi's amount on its draft, read for its answer. */
    public static function amountOf(int $documentId): ?string
    {
        $row = DB::table('document_versions')->where('document_id', $documentId)->orderByDesc('id')->first(['kuitansi_amount']);

        return $row === null ? null : Row::nullable($row, 'kuitansi_amount');
    }
}
