<?php

namespace App\Modules\PUR\Data;

/**
 * ROUTE-171's and ROUTE-172's entry (WF-PUR-01; WF-PUR-02; BR-PUR-01; DIR-024 D-1, D-2): the purchase's supplier and date, its lines at actual prices with their tax components, its charges with their source bills. The server computes every value; company, number and state are never in the payload (DP-03).
 */
final class PurchaseEntry
{
    public const GOODS = 'GOODS';

    public const NONSTOCK = 'NONSTOCK';

    public const WAREHOUSE = 'WAREHOUSE';

    public const DROPSHIP = 'DROPSHIP';

    public const ATTRIBUTABLE = 'ATTRIBUTABLE';

    public const NON_ATTRIBUTABLE = 'NON_ATTRIBUTABLE';

    public const BASES = ['LINE_VALUE', 'QUANTITY', 'WEIGHT', 'EXPLICIT'];

    /**
     * @param  list<array{handle: ?string, kind: string, product: int, line: ?int, mode: string, quantity: string, unit: int, price: string, description: string, taxes: list<array{type: int, rate: string, amount: string}>}>  $lines
     * @param  list<array{bill: int, classification: string, amount: string, basis: ?string, values: list<?string>}>  $charges
     */
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $projectId,
        public readonly int $supplierId,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly ?string $reference,
        public readonly array $lines,
        public readonly array $charges,
    ) {}
}
