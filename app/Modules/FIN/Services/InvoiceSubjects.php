<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\PaperFormat;
use App\Modules\FIN\Data\InvoiceDocuments;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * FIN's `DocumentSubjectProvider` for the invoice's two layouts and its pre-payment Kuitansi (L-26; BR-DOC-04; WF-FIN-01; WF-FIN-08):
 * DOC-06 *Invoice* and DOC-04 *Nota* render one record — value, billing and receivable identical —, its current version's snapshot,
 * the amounts read from the record and never from a payload (DATABASE §10); DOC-05 in mode PRE_PAYMENT renders an issued, non-void
 * invoice with the amount its draft holds. A document changes no stock, cash or obligation (BR-XD-04). The receipt-mode DOC-05 is
 * TASK-051's, which adds its mode here.
 */
final class InvoiceSubjects implements DocumentSubjectProvider
{
    public const INVOICE = InvoiceDocuments::INVOICE;

    public const NOTA = InvoiceDocuments::NOTA;

    public const KUITANSI = InvoiceDocuments::KUITANSI;

    public const LAYOUTS = InvoiceDocuments::LAYOUTS;

    public const REQUEST = InvoiceDocuments::REQUEST;

    public function types(): array
    {
        return [self::INVOICE => ['STANDARD'], self::NOTA => ['STANDARD'], self::KUITANSI => ['PRE_PAYMENT'], self::REQUEST => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['invoice_id'] ?? null;
        $invoice = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 && count($records) === 1
            ? DB::table('invoices')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($invoice === null || ! $viewer->allows('finance.view', (int) Row::text($invoice, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($invoice, 'company_id'), ['invoice_id' => (int) Row::text($invoice, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $invoice = self::invoice($subject);
        $project = DB::table('invoices')->where('id', $invoice)->value('project_id');
        $set = (new LockSet)->add('invoices', ['id' => $invoice]);

        return is_numeric($project) ? $set->add('projects', ['id' => (int) $project]) : $set;
    }

    /** The invoice issued and not void: a layout renders a sales record that exists; the Kuitansi's own cap is AX-31's (C-43). */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (DB::table('invoices')->where('id', self::invoice($subject))->value('state') !== 'ISSUED') {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'invoice_not_issued']);
        }

        // A payment request asks for what is still owed.
        if ($subject->typeCode === self::REQUEST && ! Money::of(PrepaymentKuitansi::outstanding(self::invoice($subject)))->isPositive()) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'nothing_outstanding']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $invoice = self::invoice($subject);
        $version = DB::table('invoices as i')->where('i.id', $invoice)->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->first(['v.id', 'v.sales_value', 'v.gross_value', 'v.client_snapshot', 'p.project_number', 'p.title']);

        if ($version === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $client = json_decode(Row::text($version, 'client_snapshot'), true);
        $client = is_array($client) ? $client : [];
        $recipient = ['name' => is_string($client['organization'] ?? null) ? $client['organization'] : '', 'lines' => array_values(array_filter([
            $client['unit'] ?? null,
            is_string($client['contact'] ?? null) ? 'u.p. '.$client['contact'] : null,
            $client['address'] ?? null,
            $client['city'] ?? null,
        ], fn (mixed $line): bool => is_string($line) && $line !== ''))];
        $references = Row::nullable($version, 'project_number') === null ? [] : [['label' => 'No. Proyek', 'value' => Row::text($version, 'project_number')]];

        if ($subject->typeCode === self::KUITANSI) {
            $number = self::number($invoice);

            return new DocumentFacts(
                recipient: $recipient,
                references: [...$references, ['label' => 'No. Invoice', 'value' => $number ?? '—']],
                subject: 'Pembayaran invoice '.($number ?? '').(Row::nullable($version, 'title') === null ? '' : ' — '.Row::text($version, 'title')),
                parentNumber: $number,
            );
        }

        if ($subject->typeCode === self::REQUEST) {
            return self::requestFacts($invoice, $recipient, $references, Row::nullable($version, 'title'));
        }

        $lines = DB::table('invoice_version_lines as l')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')->where('l.invoice_version_id', (int) Row::text($version, 'id'))->orderBy('l.id')
            ->get(['l.description', 'l.entered_qty', 'u.code', 'l.unit_price', 'l.line_sales_value'])->all();
        $taxes = DB::table('invoice_tax_components as t')->join('tax_types as y', 'y.id', '=', 't.tax_type_id')->where('t.invoice_version_id', (int) Row::text($version, 'id'))->orderBy('y.code')
            ->get(['y.code', 't.rate_percent', 't.amount'])->all();

        return new DocumentFacts(
            recipient: $recipient,
            references: $references,
            subject: Row::nullable($version, 'title'),
            lines: array_values(array_map(fn (object $line): array => [
                'description' => Row::text($line, 'description'),
                'quantity' => Row::nullable($line, 'entered_qty'),
                'unit' => Row::nullable($line, 'code') ?? '',
                'unit_price' => Row::nullable($line, 'unit_price'),
                'amount' => Row::text($line, 'line_sales_value'),
                'note' => null,
            ], $lines)),
            totals: [
                ['label' => 'Subtotal', 'amount' => Row::text($version, 'sales_value'), 'grand' => false],
                ...array_values(array_map(fn (object $tax): array => ['label' => Row::text($tax, 'code').' '.self::percent(Row::text($tax, 'rate_percent')), 'amount' => Row::text($tax, 'amount'), 'grand' => false], $taxes)),
                ['label' => 'Total', 'amount' => Row::text($version, 'gross_value'), 'grand' => true],
            ],
            renders: ['invoice_version_id' => (int) Row::text($version, 'id')],
        );
    }

    /**
     * DOC-13 from the invoice and its billing data, read from the record and never a payload (DATABASE §10; BR-DOC-04): what was billed, what is already reduced, what is asked.
     *
     * @param  array{name: string, lines: list<string>}  $recipient
     * @param  list<array{label: string, value: string}>  $references
     */
    private static function requestFacts(int $invoice, array $recipient, array $references, ?string $title): DocumentFacts
    {
        $number = self::number($invoice);
        $balance = ReceivablePosting::balance($invoice);
        $act = $balance === null || Row::nullable($balance, 'current_billing_act_id') === null ? null : DB::table('invoice_billing_acts')->where('id', (int) Row::text($balance, 'current_billing_act_id'))->first(['billed_at', 'due_date']);
        $gross = $balance === null ? '0.00' : Row::text($balance, 'gross_value');
        $outstanding = $balance === null ? '0.00' : Row::text($balance, 'outstanding');
        $reduced = Money::of($gross)->minus(Money::of($outstanding));
        $references = [...$references, ['label' => 'No. Invoice', 'value' => $number ?? '—']];

        if ($act !== null) {
            $references[] = ['label' => 'Tanggal Tagihan', 'value' => PaperFormat::date(Row::text($act, 'billed_at'))];
            $due = Row::nullable($act, 'due_date');

            if ($due !== null) {
                $references[] = ['label' => 'Jatuh Tempo', 'value' => PaperFormat::date($due)];
            }
        }

        return new DocumentFacts(
            recipient: $recipient,
            references: $references,
            subject: 'Pembayaran invoice '.($number ?? '').($title === null ? '' : ' — '.$title),
            totals: [
                ['label' => 'Nilai Tagihan', 'amount' => $gross, 'grand' => false],
                ...($reduced->isPositive() ? [['label' => 'Sudah Diterima atau Dikurangi', 'amount' => $reduced->toString(), 'grand' => false]] : []),
                ['label' => 'Sisa Tagihan', 'amount' => $outstanding, 'grand' => true],
            ],
            amount: $outstanding,
            parentNumber: $number,
        );
    }

    /** The invoice's number: its current issued layout's, or the last it carried once voided. */
    public static function number(int $invoiceId): ?string
    {
        $number = DB::table('documents as d')->where('d.invoice_id', $invoiceId)->whereIn('d.document_type_code', self::LAYOUTS)
            ->join('document_versions as v', 'v.document_id', '=', 'd.id')->whereNotNull('v.formatted_number')->orderByRaw("v.state = 'ISSUED' desc")->orderBy('d.id')->orderByDesc('v.id')->value('v.formatted_number');

        return is_string($number) ? $number : null;
    }

    private static function percent(string $rate): string
    {
        $text = rtrim(rtrim((string) BigDecimal::of($rate), '0'), '.');

        return str_replace('.', ',', $text).'%';
    }

    private static function invoice(DocumentSubject $subject): int
    {
        return $subject->records['invoice_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}
