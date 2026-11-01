<?php

namespace App\Modules\INV\Data;

/**
 * What one warehouse command of PG-027 carries (AX-06; AX-30; NX-05): the units it names, each lot, rack, condition and quantity, a serial where the product has them, and the actor's confirmation of an unidentified lot (L-42); the cut (IP-20), the reason (IP-09), the evidence (IP-16), a likely duplicate's reasoned confirmation (L-49) and the policy the page read (ST-09).
 */
final class StockPicks
{
    /**
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  array<int, string>  $cuts  reservation => quantity
     * @param  list<int>  $evidence
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $picks,
        public readonly array $cuts,
        public readonly string $reason,
        public readonly array $evidence,
        public readonly bool $unidentified,
        public readonly array $acknowledged,
        public readonly ?string $duplicateReason,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly bool $serializedSeen,
        public readonly int $scaleSeen,
    ) {}
}
