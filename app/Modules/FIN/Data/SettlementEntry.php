<?php

namespace App\Modules\FIN\Data;

/**
 * ROUTE-255's entry (AX-21; SF-SETTLE; WF-FIN-04): one deduction on one invoice, anchored to its payment and evidence (L-28). The specific type comes from the payment's remittance advice — a fee's type or a tax's with its direction —,
 * so one type is one key (BD-08). The form has no field for an unexplained short payment (J-FIN-04).
 */
final class SettlementEntry
{
    public const FEE = 'FEE';

    public const TAX = 'TAX';

    public const WITHHELD = 'WITHHELD';

    public const COLLECTED = 'COLLECTED';

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $paymentId,
        public readonly string $class,
        public readonly ?int $taxTypeId,
        public readonly ?string $feeType,
        public readonly ?string $direction,
        public readonly string $amount,
        public readonly int $evidenceId,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly string $reason,
    ) {}

    /** The key of BD-08's one ACTIVE settlement, as `deduction_settlements_c22_specific_type` states it. */
    public function specificType(): string
    {
        return $this->class === self::TAX ? "{$this->taxTypeId}:{$this->direction}" : (string) $this->feeType;
    }

    /** The citing role of C-58: a fee advice, or the proof of the tax's direction. */
    public function role(): string
    {
        return self::roleOf($this->class, $this->direction);
    }

    public static function roleOf(string $class, ?string $direction): string
    {
        return $class === self::FEE ? 'FEE_SETTLEMENT' : ($direction === self::COLLECTED ? 'TAX_SETTLEMENT_COLLECTED' : 'TAX_SETTLEMENT_WITHHELD');
    }

    /** The evidence type a citing settlement takes (C-58). */
    public static function evidenceType(string $class, ?string $direction): string
    {
        return $class === self::FEE ? 'FEE_ADVICE' : ($direction === self::COLLECTED ? 'TAX_COLLECTION_PROOF' : 'TAX_WITHHOLDING_PROOF');
    }
}
