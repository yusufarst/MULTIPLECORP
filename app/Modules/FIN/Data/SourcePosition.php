<?php

namespace App\Modules\FIN\Data;

use App\Support\Money;

/**
 * Where an application source stands (DATABASE §4.12, §11.3; BR-FIN-03; CALC-06; L-46): its real money, what is applied and refund-backed, its capacity — the client's credit from this source — and what the source guard's CHECKs say of it,
 * the same rules in words so a refusal can name them before the row would. A refund-backed application leaves only by its typed exits.
 */
final class SourcePosition
{
    /** The only ways a REFUND-target application leaves ACTIVE (L-46; C-44). */
    public const REFUND_EXITS = ['RE_RECORDED', 'SOURCE_CONTRA', 'REFUND_CONTRA'];

    public function __construct(
        public readonly Money $gross,
        public readonly Money $contra,
        public readonly Money $invoiceApplied,
        public readonly Money $refundBacked,
        public readonly Money $shortfall,
        public readonly Money $settled,
    ) {}

    public function net(): Money
    {
        return $this->gross->minus($this->contra);
    }

    public function capacity(): Money
    {
        return $this->net()->minus($this->invoiceApplied)->minus($this->refundBacked);
    }

    /**
     * The rule the position would break, with its reason and figures, or null where its CHECKs hold: capacity never below zero (C-13); a source with an open refund shortfall keeps no invoice application and no free capacity (C-44); a fully
     * contra'd payment keeps no active settlement (C-45).
     *
     * @return array{rule: string, state: array<string, string>}|null
     */
    public function breach(): ?array
    {
        if ($this->capacity()->isNegative()) {
            return ['rule' => 'C-13', 'state' => ['reason' => 'undisposed', 'short' => $this->capacity()->negated()->toString()]];
        }

        if ($this->shortfall->isPositive() && (! $this->invoiceApplied->isZero() || ! $this->capacity()->isZero())) {
            return ['rule' => 'C-44', 'state' => ['reason' => 'shortfall_with_money', 'applied' => $this->invoiceApplied->toString(), 'capacity' => $this->capacity()->toString()]];
        }

        if ($this->net()->isZero() && ! $this->settled->isZero()) {
            return ['rule' => 'C-45', 'state' => ['reason' => 'settlements', 'settled' => $this->settled->toString()]];
        }

        return null;
    }
}
