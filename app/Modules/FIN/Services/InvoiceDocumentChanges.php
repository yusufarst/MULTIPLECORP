<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Support\Facades\DB;

/**
 * FIN's `DocumentChangeParticipant` part (ARCHITECTURE §3; CM-26): a pre-payment Kuitansi revised or voided on PG-036 ends its links in
 * DOC's own transaction — relinked per (payment, invoice) after its reissue —; voiding it never touches a payment (WF-FIN-08).
 */
final class InvoiceDocumentChanges implements DocumentChangeParticipant
{
    public function __construct(private readonly PrepaymentKuitansi $kuitansi) {}

    public function locks(DocumentChange $change): LockSet
    {
        $invoice = self::prepaymentInvoice($change);

        return $invoice === null ? new LockSet : (new LockSet)->add('invoices', ['id' => $invoice]);
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        if (self::prepaymentInvoice($change) !== null && in_array($change->kind, [DocumentChange::DOCUMENT_VOIDED, DocumentChange::DOCUMENT_REVISED], true)) {
            $this->kuitansi->unlink($context, (int) $change->documentId, 'invoices.unlink.document');
        }
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
