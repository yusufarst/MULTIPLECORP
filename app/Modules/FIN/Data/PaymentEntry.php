<?php

namespace App\Modules\FIN\Data;

/**
 * A payment as AX-18 takes it (WF-FIN-03; J-FIN-03): the fact — date, amount, method, the company's account, the statement line it matches, reference, the
 * intermediary who paid on the client's behalf —, its proof or the reason it has none (L-29), the remittance advice's claimed deductions, its same-command
 * applications, and the links a recovery, a re-recording or a real transfer from another company carry. The second submission of a likely duplicate names the matches it acknowledges (IP-05).
 */
final class PaymentEntry
{
    public const METHODS = ['TRANSFER', 'CASH', 'VIRTUAL_ACCOUNT', 'OTHER'];

    /** What may stand as proof money was received: never a document the company issued itself (L-29). */
    public const PROOF = ['PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'];

    /**
     * @param  list<int>  $evidence  the proofs, each linked to the payment
     * @param  list<array{class: string, tax_type: ?int, fee_type: ?string, amount: string}>  $claimed
     * @param  array<int, string>  $applications  invoice => amount
     * @param  array<int, string>  $resolves  a SOURCE_CONTRA-ended refund application => what of its shortfall this money backs (L-46)
     * @param  list<string>  $acknowledged
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $clientId,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly string $amount,
        public readonly string $method,
        public readonly ?int $accountId,
        public readonly ?int $lineId,
        public readonly ?string $reference,
        public readonly ?string $intermediary,
        public readonly array $evidence,
        public readonly ?string $unevidencedReason,
        public readonly array $claimed,
        public readonly array $applications,
        public readonly array $resolves,
        public readonly ?int $recoveryFor,
        public readonly ?int $transferFrom,
        public readonly ?int $rerecords,
        public readonly array $acknowledged,
        public readonly ?string $overrideReason,
    ) {}
}
