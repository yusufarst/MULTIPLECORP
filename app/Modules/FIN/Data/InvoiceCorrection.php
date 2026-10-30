<?php

namespace App\Modules\FIN\Data;

/**
 * A correction's request as AX-22 takes it (CM-23; CM-24; L-30): the typed basis with its row, the reason, the entry-error evidence, the
 * new content — the revision's, or the replacement named in the same action —, and what becomes of every reduction on the invoice.
 */
final class InvoiceCorrection
{
    public const RELEASE = 'RELEASE';

    public const RETARGET = 'RETARGET';

    public const KEEP = 'KEEP';

    public const RERECORD = 'RERECORD';

    public const RESIDUAL = 'RESIDUAL';

    public const SUPERSEDE = 'SUPERSEDE';

    /**
     * @param  array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}|null  $content
     * @param  array<int, string>  $applications  application => RELEASE, RETARGET or KEEP
     * @param  array<int, string>  $settlements  settlement => RERECORD, RESIDUAL or KEEP
     * @param  array<int, string>  $writeoffs  write-off => SUPERSEDE, RERECORD
     */
    public function __construct(
        public readonly string $basis,
        public readonly string $reason,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly ?int $caseId = null,
        public readonly ?int $confirmationId = null,
        public readonly ?int $cancellationId = null,
        public readonly ?int $transitionId = null,
        public readonly ?int $evidenceId = null,
        public readonly ?array $content = null,
        public readonly array $applications = [],
        public readonly array $settlements = [],
        public readonly array $writeoffs = [],
    ) {}
}
