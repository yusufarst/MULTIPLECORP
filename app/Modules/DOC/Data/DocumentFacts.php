<?php

namespace App\Modules\DOC\Data;

/**
 * What a layout prints of its record, as the issuing module supplies it (WF-DOC-01; DATABASE §10): the party it
 * addresses, the numbers and dates it cites, its lines and the record's own totals — decimal strings the record
 * computed, never DOC (no financial or stock computation reads a payload) — and, for a Kuitansi or a payment
 * request, the amount it states. Labels are Indonesian, the paper's language whatever the interface's
 * (LOCALIZATION §3.4). `renders` names the business version the version renders (`quotation_revision_id`,
 * `invoice_version_id`), `kuitansiAmount` DOC-05's typed amount.
 */
final class DocumentFacts
{
    /**
     * @param  array{name: string, lines: list<string>}|null  $recipient
     * @param  list<array{label: string, value: string}>  $references
     * @param  list<array{description: string, quantity: ?string, unit: ?string, unit_price: ?string, amount: ?string, note: ?string}>  $lines
     * @param  list<array{label: string, amount: string, grand: bool}>  $totals
     * @param  array<string, int>  $renders
     */
    public function __construct(
        public readonly ?array $recipient = null,
        public readonly array $references = [],
        public readonly ?string $subject = null,
        public readonly array $lines = [],
        public readonly array $totals = [],
        public readonly ?string $amount = null,
        public readonly ?string $parentNumber = null,
        public readonly array $renders = [],
        public readonly ?string $kuitansiAmount = null,
    ) {}
}
