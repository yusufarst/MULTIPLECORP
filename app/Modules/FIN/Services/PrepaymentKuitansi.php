<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\InvoiceBalance;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The pre-payment Kuitansi's money rules (AX-31; C-43; WF-FIN-08; GAP-029), under the invoice's guard (LK-25): an open one is an issued
 * DOC-05 PRE_PAYMENT no payment is linked to; Σ open on an invoice stays within its outstanding. A link holds only while its Kuitansi's
 * amount equals the linked payments' applications to the invoice plus the invoice's settlements; whatever lowers that total unlinks it
 * in the same transaction, the Kuitansi *Menunggu Pembayaran* again with the cause. It creates no Cash-In, payment, settlement or paid
 * state, and is never proof of receipt (DIR-024 D-4).
 */
final class PrepaymentKuitansi
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** What the invoice's guard says is outstanding. */
    public static function outstanding(int $invoiceId): string
    {
        $balance = InvoiceBalance::query()->toBase()->where('invoice_id', $invoiceId)->value('outstanding');

        return is_scalar($balance) ? (string) $balance : '0.00';
    }

    /** Σ open pre-payment Kuitansi on the invoice, without the chain being issued. */
    public static function openTotal(int $invoiceId, ?int $exceptDocumentId = null): string
    {
        $total = DB::table('documents as d')->join('document_versions as v', 'v.document_id', '=', 'd.id')
            ->where('d.invoice_id', $invoiceId)->where('d.document_type_code', 'DOC-05')->where('d.mode', 'PRE_PAYMENT')->where('v.state', 'ISSUED')
            ->when($exceptDocumentId !== null, fn (Builder $query) => $query->where('d.id', '<>', $exceptDocumentId))
            ->whereRaw("not exists (select 1 from prepayment_kuitansi_links k where k.document_id = d.id and k.state = 'LINKED')")
            ->selectRaw('coalesce(sum(v.kuitansi_amount), 0) as total')->value('total');

        return Money::of(is_scalar($total) ? (string) $total : '0')->toString();
    }

    /**
     * The total a link compares: the payments' active applications to the invoice plus the invoice's active settlements.
     *
     * @param  list<int>  $paymentIds
     */
    public static function linkedTotal(int $invoiceId, array $paymentIds): string
    {
        $applied = DB::table('payment_applications')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->whereIn('payment_id', $paymentIds)->sum('amount');
        $settled = DB::table('deduction_settlements')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->sum('amount');

        return Money::of((string) $applied)->plus(Money::of((string) $settled))->toString();
    }

    /** Every Kuitansi of the invoice whose linked total fell below its amount unlinked, with its cause (AX-19/20/22/33; QS-19). */
    public function reconcile(CommandContext $context, int $invoiceId, string $cause): void
    {
        $linked = DB::table('prepayment_kuitansi_links as k')->where('k.invoice_id', $invoiceId)->where('k.state', 'LINKED')->get(['k.document_id', 'k.payment_id']);
        $byDocument = [];

        foreach ($linked as $row) {
            $byDocument[(int) Row::text($row, 'document_id')][] = (int) Row::text($row, 'payment_id');
        }

        foreach ($byDocument as $document => $payments) {
            $amount = DB::table('document_versions')->where('document_id', $document)->where('state', 'ISSUED')->value('kuitansi_amount');

            if (! is_scalar($amount) || Money::of(self::linkedTotal($invoiceId, $payments))->compareTo(Money::of((string) $amount)) < 0) {
                $this->unlink($context, $document, $cause);
            }
        }
    }

    /** The document's links ended with their cause, its Kuitansi *Menunggu Pembayaran* again (CM-26). */
    public function unlink(CommandContext $context, int $documentId, string $cause): void
    {
        $unlinked = DB::table('prepayment_kuitansi_links')->where('document_id', $documentId)->where('state', 'LINKED')->update(['state' => 'UNLINKED', 'unlink_reason' => $cause]);

        if ($unlinked > 0) {
            $document = DB::table('documents')->where('id', $documentId)->first(['company_id', 'public_id']);
            $this->audit->record(
                action: 'FIN.PrepaymentKuitansiUnlinked',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: is_object($document) ? (int) Row::text($document, 'company_id') : null,
                entityType: 'document',
                entityId: $documentId,
                entityPublicId: is_object($document) ? Row::text($document, 'public_id') : null,
                reason: $cause,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'LINKED'], ['state' => 'UNLINKED']),
            );
        }
    }
}
