<?php

namespace App\Modules\INV\Data;

/**
 * One purchase line's receipt as ROUTE-181 sends it, every reference resolved through the scope (CS-04; WS-02): quantities in the entered unit, serials with their condition, the policy the form was read under (ST-09). The lot, its source company, cost and state are derived, never sent.
 */
final class ReceiptEntry
{
    public const ORIGINAL = 'ORIGINAL';

    public const SUPPLEMENT = 'SUPPLEMENT';

    public const RE_RECEIPT = 'RE_RECEIPT';

    public const REPLACEMENT = 'REPLACEMENT';

    public const KINDS = [self::ORIGINAL, self::SUPPLEMENT, self::RE_RECEIPT, self::REPLACEMENT];

    /**
     * @param  list<array{serial: string, damaged: bool}>  $serials
     * @param  list<int>  $evidence
     */
    public function __construct(
        public readonly int $purchaseId,
        public readonly int $lineId,
        public readonly string $kind,
        public readonly int $unitId,
        public readonly string $usable,
        public readonly string $damaged,
        public readonly string $rejected,
        public readonly ?int $locationId,
        public readonly array $serials,
        public readonly ?string $deliveryReference,
        public readonly ?int $correctsReceivingLineId,
        public readonly ?int $caseId,
        public readonly ?string $reserve,
        public readonly array $evidence,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly bool $serializedSeen,
        public readonly int $scaleSeen,
    ) {}
}
