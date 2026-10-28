<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\DocumentFacts;

/**
 * The paper a layout's template reads (DESIGN_SYSTEM §14.3; DOCUMENT_PREVIEW_PRODUCER §3): one view of the record's
 * facts, the issuing company's identity, the entered date and bank destination and — once issued — the number, its
 * values already written as the paper writes them (PaperFormat), so the browser's composer and the renderer read one
 * template with one view and print the same. In draft mode the paper is marked, its number line says the number comes
 * at issue and its signature block is text (DC-2–DC-4). The issued paper is the payload's snapshot (BR-DOC-01).
 */
final class DocumentPaper
{
    /** The layouts that state the issuer's bank destination (DATABASE §10: financial documents ask for payment). */
    public const BANK_LAYOUTS = ['DOC-04', 'DOC-06', 'DOC-13'];

    /** WF-DOC-01's collection documents, issued for existing receivables of a deactivated company too (BR-ACC-03). */
    public const COLLECTION_LAYOUTS = ['DOC-05', 'DOC-07', 'DOC-13'];

    /**
     * @param  array{name: string, npwp: ?string, address: list<string>, city: ?string, phone: ?string, email: ?string, director: ?string}  $issuer
     * @param  array{bank_name: string, account_number: string, account_holder: string, branch: ?string}|null  $bank
     * @return array<string, mixed>
     */
    public static function compose(string $typeCode, string $mode, DocumentFacts $facts, array $issuer, ?array $bank, string $businessDate, ?string $number, bool $draft, ?string $logoSrc = null): array
    {
        $lines = [];

        foreach ($facts->lines as $index => $line) {
            $lines[] = [
                'no' => $index + 1,
                'description' => $line['description'],
                'quantity' => $line['quantity'] === null ? null : PaperFormat::quantity($line['quantity']),
                'unit' => $line['unit'],
                'unit_price' => $line['unit_price'] === null ? null : PaperFormat::rupiah($line['unit_price']),
                'amount' => $line['amount'] === null ? null : PaperFormat::rupiah($line['amount']),
                'note' => $line['note'],
            ];
        }

        $contact = implode(' · ', array_filter([$issuer['phone'] === null ? null : 'Telp. '.$issuer['phone'], $issuer['email']]));

        return [
            'layout' => $typeCode,
            'draft' => $draft,
            'number' => $draft ? null : $number,
            'date' => PaperFormat::date($businessDate),
            'place' => $issuer['city'],
            'issuer' => [
                'name' => $issuer['name'],
                'npwp' => $issuer['npwp'],
                'address' => $issuer['address'],
                'city' => $issuer['city'],
                'contact' => $contact === '' ? null : $contact,
                'logo_src' => $logoSrc,
            ],
            'recipient' => $facts->recipient,
            'references' => $facts->references,
            'subject' => $facts->subject,
            'lines' => $lines,
            'has_lines' => $lines !== [],
            'priced' => array_filter($lines, fn (array $line): bool => $line['amount'] !== null) !== [],
            'totals' => array_map(fn (array $total): array => ['label' => $total['label'], 'value' => PaperFormat::rupiah($total['amount']), 'grand' => $total['grand']], $facts->totals),
            'has_totals' => $facts->totals !== [],
            'amount' => $facts->amount === null ? null : PaperFormat::rupiah($facts->amount),
            'amount_words' => $facts->amount === null ? null : PaperFormat::words($facts->amount),
            'parent_number' => $facts->parentNumber,
            'bank' => in_array($typeCode, self::BANK_LAYOUTS, true) ? $bank : null,
            'signer' => ['name' => $issuer['director'], 'title' => 'Direktur'],
            'prepayment' => $typeCode === 'DOC-05' && $mode === 'PRE_PAYMENT',
            'provisional' => false,
        ];
    }
}
