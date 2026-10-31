<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentVoiding;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Support\Facades\DB;

/**
 * FIN's `DocumentChangeParticipant` part (ARCHITECTURE §3; CM-26): a pre-payment Kuitansi revised or voided on PG-036 ends its links in
 * DOC's own transaction — relinked per (payment, invoice) after its reissue —; voiding it never touches a payment (WF-FIN-08). A Kuitansi's
 * Lampiran follows its revisions and voids (WF-DOC-01; TASK-051).
 */
final class InvoiceDocumentChanges implements DocumentChangeParticipant
{
    public function __construct(
        private readonly PrepaymentKuitansi $kuitansi,
        private readonly DocumentChain $chain,
        private readonly DocumentVoiding $voiding,
        private readonly DocumentIssuing $issuing,
    ) {}

    public function locks(DocumentChange $change): LockSet
    {
        $invoice = self::prepaymentInvoice($change);
        $set = $invoice === null ? new LockSet : (new LockSet)->add('invoices', ['id' => $invoice]);
        $attachment = self::attachment($change);

        if ($attachment === null) {
            return $set;
        }

        $document = $this->chain->document($attachment);
        $issued = $this->chain->issuedOf($attachment);

        return $set->union($this->chain->locks($document, $issued === null ? [] : [$issued->id]));
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        if (! in_array($change->kind, [DocumentChange::DOCUMENT_VOIDED, DocumentChange::DOCUMENT_REVISED], true)) {
            return;
        }

        if (self::prepaymentInvoice($change) !== null) {
            $this->kuitansi->unlink($context, (int) $change->documentId, 'invoices.unlink.document');
        }

        // Its Lampiran follows the Kuitansi (WF-DOC-01): voided with it, or waiting on PG-036 as the revision's draft.
        $attachment = self::attachment($change);

        if ($attachment === null) {
            return;
        }

        $document = $this->chain->document($attachment);
        $issued = $this->chain->issuedOf($attachment);

        if ($issued !== null && $change->kind === DocumentChange::DOCUMENT_VOIDED) {
            $this->voiding->void($context, $document, $issued, 'Kuitansi induknya dibatalkan.');
        } elseif ($issued !== null && $this->chain->draftOf($attachment) === null) {
            $this->issuing->revisionDraft($context, $document, $issued);
        }
    }

    /** The Lampiran of a changed Kuitansi, of either mode. */
    private static function attachment(DocumentChange $change): ?int
    {
        if ($change->documentId === null) {
            return null;
        }

        $id = DB::table('documents as d')->join('documents as k', 'k.id', '=', 'd.parent_document_id')->where('k.id', $change->documentId)->where('k.document_type_code', 'DOC-05')->where('d.document_type_code', 'DOC-07')->value('d.id');

        return is_numeric($id) ? (int) $id : null;
    }

    private static function prepaymentInvoice(DocumentChange $change): ?int
    {
        if ($change->documentId === null) {
            return null;
        }

        $invoice = DB::table('documents')->where('id', $change->documentId)->where('document_type_code', 'DOC-05')->where('mode', 'PRE_PAYMENT')->value('invoice_id');

        return is_numeric($invoice) ? (int) $invoice : null;
    }
}
