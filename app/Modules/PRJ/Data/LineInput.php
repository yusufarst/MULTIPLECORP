<?php

namespace App\Modules\PRJ\Data;

/**
 * One demand line as PG-013's line editor sends it (WF-PRJ-01; PT-10), its product, unit and conversion resolved:
 * an existing line of the project by its identifier, a new one without; its price null when the actor lacks
 * `finance.view`, which keeps an existing line's price as it is (PJ-12).
 */
final class LineInput
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $productId,
        public readonly string $kind,
        public readonly int $quantityScale,
        public readonly string $description,
        public readonly string $quantity,
        public readonly int $unitId,
        public readonly int $ratio,
        public readonly string $quantityBase,
        public readonly ?string $price,
        public readonly string $mode,
    ) {}
}
