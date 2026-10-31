<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's `DocumentSubjectProvider` for the receipt-mode DOC-05 *Kuitansi* and its DOC-07 *Lampiran* (BR-DOC-04; C-43; WF-DOC-01; L-29): the Kuitansi states the money this payment has applied to this invoice, read from
 * the record, never a payload; it is refused while a pre-payment Kuitansi is open on the invoice, and money already linked to one receives no second. Both issue modes hold the invoice's guard (LK-25). The Lampiran
 * details an issued Kuitansi and follows its revisions and voids. Neither is ever proof that money was received (L-29), nor changes any money (BR-XD-04).
 */
final class ReceiptSubjects implements DocumentSubjectProvider
{
    public const KUITANSI = 'DOC-05';

    public const LAMPIRAN = 'DOC-07';

    public function types(): array
    {
        return [self::KUITANSI => ['RECEIPT'], self::LAMPIRAN => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        return $typeCode === self::KUITANSI ? self::receipt($viewer, $mode, $records) : self::attachment($viewer, $mode, $records);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $invoice = self::invoiceOf($subject);
        $set = $invoice === null ? new LockSet : PaymentApplications::invoiceLocks([$invoice]);
        $payment = $subject->records['payment_id'] ?? null;
        $parent = $subject->records['parent_document_id'] ?? null;

        if ($payment !== null) {
            $set = $set->add('application_source_balances', ['payment_id' => $payment]);
        }

        return $parent === null ? $set : $set->add('documents', ['id' => $parent]);
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if ($subject->typeCode === self::LAMPIRAN) {
            if (DB::table('document_versions')->where('document_id', $subject->records['parent_document_id'] ?? 0)->where('state', 'ISSUED')->doesntExist()) {
                throw Refused::rejected('WF-DOC-01', ['reason' => 'parent_not_issued']);
            }

            return;
        }

        $invoice = self::invoiceOf($subject) ?? 0;
        $payment = $subject->records['payment_id'] ?? 0;

        if (DB::table('invoices')->where('id', $invoice)->value('state') !== 'ISSUED') {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'invoice_not_issued']);
        }

        if (Money::of(PrepaymentKuitansi::openTotal($invoice))->isPositive()) {
            throw Refused::rejected('C-43', ['reason' => 'prepayment_open']);
        }

        if (DB::table('prepayment_kuitansi_links')->where('invoice_id', $invoice)->where('payment_id', $payment)->where('state', 'LINKED')->exists()) {
            throw Refused::rejected('C-43', ['reason' => 'already_linked']);
        }

        if (! Money::of(self::applied($payment, $invoice))->isPositive()) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_payment']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $invoice = self::invoiceOf($subject) ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);

        return $subject->typeCode === self::KUITANSI ? self::receiptFacts($subject, $invoice) : self::attachmentFacts($subject, $invoice);
    }

    /** @param  array<string, string>  $records */
    private static function receipt(Authority $viewer, string $mode, array $records): ?DocumentSubject
    {
        $payment = self::row('payments', $records['payment_id'] ?? null);
        $invoice = self::row('invoices', $records['invoice_id'] ?? null);

        if ($mode !== 'RECEIPT' || count($records) !== 2 || $payment === null || $invoice === null) {
            return null;
        }

        $company = (int) Row::text($payment, 'company_id');

        if ($company !== (int) Row::text($invoice, 'company_id') || Row::text($payment, 'client_organization_id') !== Row::text($invoice, 'client_organization_id') || ! $viewer->allows('finance.view', $company)) {
            return null;
        }

        return new DocumentSubject(self::KUITANSI, 'RECEIPT', $company, ['payment_id' => (int) Row::text($payment, 'id'), 'invoice_id' => (int) Row::text($invoice, 'id')]);
    }

    /** @param  array<string, string>  $records */
    private static function attachment(Authority $viewer, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['parent_document_id'] ?? null;
        $parent = is_string($publicId) && $mode === 'STANDARD' && count($records) === 1 && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1
            ? DB::table('documents')->where('public_id', strtolower($publicId))->where('document_type_code', self::KUITANSI)->first(['id', 'company_id']) : null;

        if ($parent === null || ! $viewer->allows('finance.view', (int) Row::text($parent, 'company_id'))) {
            return null;
        }

        return new DocumentSubject(self::LAMPIRAN, 'STANDARD', (int) Row::text($parent, 'company_id'), ['parent_document_id' => (int) Row::text($parent, 'id')]);
    }

    private static function row(string $table, ?string $publicId): ?object
    {
        return is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table($table)->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'client_organization_id']) : null;
    }

    private static function invoiceOf(DocumentSubject $subject): ?int
    {
        $invoice = $subject->records['invoice_id'] ?? null;

        if ($invoice !== null) {
            return $invoice;
        }

        $parent = DB::table('documents')->where('id', $subject->records['parent_document_id'] ?? 0)->value('invoice_id');

        return is_numeric($parent) ? (int) $parent : null;
    }

    /** What this payment has applied to this invoice now. */
    private static function applied(int $paymentId, int $invoiceId): string
    {
        $sum = DB::table('payment_applications')->where('payment_id', $paymentId)->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->sum('amount');

        return Money::of((string) $sum)->toString();
    }

    private static function receiptFacts(DocumentSubject $subject, int $invoice): DocumentFacts
    {
        $party = self::party($invoice);
        $amount = self::applied($subject->records['payment_id'] ?? 0, $invoice);
        $payment = DB::table('payments')->where('id', $subject->records['payment_id'] ?? 0)->first(['reference']);
        $reference = $payment === null ? null : Row::nullable($payment, 'reference');
        $number = InvoiceSubjects::number($invoice);
        $references = [...$party['references'], ['label' => 'No. Invoice', 'value' => $number ?? '—']];

        return new DocumentFacts(
            recipient: $party['recipient'],
            references: $reference === null ? $references : [...$references, ['label' => 'Referensi Pembayaran', 'value' => $reference]],
            subject: 'Pembayaran invoice '.($number ?? '').($party['title'] === null ? '' : ' — '.$party['title']),
            amount: $amount,
            parentNumber: $number,
            kuitansiAmount: Money::of($amount)->isPositive() ? $amount : null,
        );
    }

    private static function attachmentFacts(DocumentSubject $subject, int $invoice): DocumentFacts
    {
        $party = self::party($invoice);
        $parent = DB::table('document_versions')->where('document_id', $subject->records['parent_document_id'] ?? 0)->where('state', 'ISSUED')->first(['formatted_number', 'kuitansi_amount']);
        $version = DB::table('invoices')->where('id', $invoice)->value('current_version_id');
        $lines = DB::table('invoice_version_lines as l')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')->where('l.invoice_version_id', is_numeric($version) ? (int) $version : 0)->orderBy('l.id')
            ->get(['l.description', 'l.entered_qty', 'u.code', 'l.unit_price', 'l.line_sales_value'])->all();
        $balance = ReceivablePosting::balance($invoice);
        $amount = $parent === null ? '0.00' : (Row::nullable($parent, 'kuitansi_amount') ?? '0.00');

        return new DocumentFacts(
            recipient: $party['recipient'],
            references: [...$party['references'], ['label' => 'No. Invoice', 'value' => InvoiceSubjects::number($invoice) ?? '—']],
            subject: 'Rincian Kuitansi'.($party['title'] === null ? '' : ' — '.$party['title']),
            lines: array_values(array_map(fn (object $line): array => [
                'description' => Row::text($line, 'description'),
                'quantity' => Row::nullable($line, 'entered_qty'),
                'unit' => Row::nullable($line, 'code') ?? '',
                'unit_price' => Row::nullable($line, 'unit_price'),
                'amount' => Row::text($line, 'line_sales_value'),
                'note' => null,
            ], $lines)),
            totals: [
                ['label' => 'Nilai Tagihan', 'amount' => $balance === null ? '0.00' : Row::text($balance, 'gross_value'), 'grand' => false],
                ['label' => 'Nilai Kuitansi', 'amount' => $amount, 'grand' => true],
            ],
            parentNumber: $parent === null ? null : Row::nullable($parent, 'formatted_number'),
        );
    }

    /** @return array{recipient: array{name: string, lines: list<string>}, references: list<array{label: string, value: string}>, title: ?string} */
    private static function party(int $invoice): array
    {
        $version = DB::table('invoices as i')->where('i.id', $invoice)->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')->leftJoin('projects as p', 'p.id', '=', 'i.project_id')->first(['v.client_snapshot', 'p.project_number', 'p.title']);

        if ($version === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $client = json_decode(Row::text($version, 'client_snapshot'), true);
        $client = is_array($client) ? $client : [];

        return [
            'recipient' => ['name' => is_string($client['organization'] ?? null) ? $client['organization'] : '', 'lines' => array_values(array_filter([
                $client['unit'] ?? null,
                is_string($client['contact'] ?? null) ? 'u.p. '.$client['contact'] : null,
                $client['address'] ?? null,
                $client['city'] ?? null,
            ], fn (mixed $line): bool => is_string($line) && $line !== ''))],
            'references' => Row::nullable($version, 'project_number') === null ? [] : [['label' => 'No. Proyek', 'value' => Row::text($version, 'project_number')]],
            'title' => Row::nullable($version, 'title'),
        ];
    }
}
