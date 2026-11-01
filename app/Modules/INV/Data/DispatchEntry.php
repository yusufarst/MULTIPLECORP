<?php

namespace App\Modules\INV\Data;

use Brick\Math\BigDecimal;

/**
 * One line's dispatch as entered on PG-024 (ROUTE-186; AX-03): the quantity in the line's unit, the lots the operator confirmed with their racks and, for a serialized product, each unit. The bearer, the source company and the cost are never here: they are derived from the line and the lots (CS-04; DP-03).
 */
final class DispatchEntry
{
    /**
     * @param  list<array{lot: int, location: int, qty: string, serial: ?int}>  $picks  each in base units, a serial one unit
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function __construct(
        public readonly int $lineId,
        public readonly int $unitId,
        public readonly string $quantity,
        public readonly array $picks,
        public readonly ?string $allocationReason,
        public readonly array $acknowledged,
        public readonly ?string $duplicateReason,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly bool $serializedSeen,
        public readonly int $scaleSeen,
    ) {}

    /**
     * The line's own reservation first, the rest from AVAILABLE (BR-RSV-02; M-03): the reserved and unreserved parts, which sum to the quantity.
     *
     * @return array{BigDecimal, BigDecimal}
     */
    public static function parts(BigDecimal $base, BigDecimal $own): array
    {
        $reserved = BigDecimal::min($base, $own);

        return [$reserved, $base->minus($reserved)];
    }
}
