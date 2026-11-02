<?php

namespace App\Modules\INV\Data;

/**
 * "Terapkan Selisih" as its command reads it (ROUTE-193; AX-06): how the actor splits a shortage — named lots, the pending quantity, conversions of pending condition-changed units — and which identified losses a surplus reverses.
 */
final class CountEntry
{
    /**
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  list<array{case: int, qty: string}>  $conversions
     * @param  list<array{consumption: int, qty: string}>  $restored
     * @param  array<int, string>  $cuts  reservation => quantity
     * @param  list<int>  $evidence
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function __construct(
        public readonly int $countId,
        public readonly array $picks,
        public readonly bool $unidentified,
        public readonly string $pending,
        public readonly array $conversions,
        public readonly array $restored,
        public readonly array $cuts,
        public readonly string $reason,
        public readonly array $evidence,
        public readonly ?string $allocationReason,
        public readonly array $acknowledged,
        public readonly ?string $duplicateReason,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly bool $serializedSeen,
        public readonly int $scaleSeen,
    ) {}
}
