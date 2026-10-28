<?php

namespace App\Modules\PRJ\Data;

/**
 * A project as PG-013 sends it (WF-PRJ-01; ROUTE-151; ROUTE-152), every reference resolved through the scope
 * resolver before the command runs (CS-04; AZ-11): the company, the client with its unit, address and PIC, the
 * channel with its SIPLAH fields — null for a REGULAR project —, the title and deadline, a linked project of another
 * company with its reason (XL-4), the demand lines. `money` says whether the actor holds `finance.view`, without which
 * no price or SIPLAH amount is taken (PJ-12). `payload` is the validated request, the command's fingerprint (CI-01).
 */
final class ProjectInput
{
    public const SIPLAH_TEXTS = ['external_reference', 'program_code', 'account_code', 'notes'];

    public const SIPLAH_AMOUNTS = ['siplah_value', 'real_purchase_value', 'fee_amount', 'va_fee_amount', 'cashback_amount', 'refund_amount'];

    /**
     * @param  list<LineInput>  $lines
     * @param  array<string, ?string>|null  $siplah
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $clientId,
        public readonly ?int $unitId,
        public readonly ?int $addressId,
        public readonly ?int $contactId,
        public readonly string $channel,
        public readonly string $title,
        public readonly ?string $deadline,
        public readonly ?int $linkedProjectId,
        public readonly ?string $linkReason,
        public readonly array $lines,
        public readonly ?array $siplah,
        public readonly bool $money,
        public readonly array $payload,
    ) {}
}
