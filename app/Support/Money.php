<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Rupiah as an exact decimal of two places (ARCHITECTURE §6; DATABASE §17 D-DB-03), never a PHP float: a
 * `numeric(18,2)` value travels in as its string and out as one. A share of an amount — its cost over a quantity, a
 * charge over a basis — is rounded once to the Rp1 increment, half away from zero exactly as PostgreSQL's
 * `round(numeric)` rounds, and every split leaves its residual to its caller, who gives it to the share that zeroes
 * the whole (BR-FIN-13; GAP-004; DATABASE §6.2). The decimal library is brick/math (DEP-07; TOOLCHAIN), held behind
 * this class.
 */
final class Money
{
    public const SCALE = 2;

    private function __construct(private readonly BigDecimal $value) {}

    /** An amount from its decimal string or integer rupiah; more than two places is refused, never rounded silently. */
    public static function of(string|int $amount): self
    {
        $value = BigDecimal::of($amount);

        if ($value->getScale() > self::SCALE && ! $value->isEqualTo($value->toScale(self::SCALE, RoundingMode::Down))) {
            throw new InvalidArgumentException("An amount has at most two decimal places: {$amount}.");
        }

        return new self($value->toScale(self::SCALE));
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    /** Σ of the amounts, zero for none. */
    public static function sum(self ...$amounts): self
    {
        $total = self::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function plus(self $that): self
    {
        return new self($this->value->plus($that->value));
    }

    public function minus(self $that): self
    {
        return new self($this->value->minus($that->value));
    }

    public function negated(): self
    {
        return new self($this->value->negated());
    }

    /**
     * The amount × numerator ÷ denominator, rounded once to the Rp1 increment, half away from zero — a quantity's
     * share of a cost, a basis's share of a charge. The ratio is taken exactly: 100,000 × 5 ÷ 12 = 41,666.67 → 41,667.
     */
    public function share(string|int $numerator, string|int $denominator): self
    {
        $denominator = BigDecimal::of($denominator);

        if ($denominator->isZero()) {
            throw new InvalidArgumentException('A share is taken over a non-zero whole.');
        }

        return new self($this->value->multipliedBy(BigDecimal::of($numerator))->dividedBy($denominator, 0, RoundingMode::HalfUp)->toScale(self::SCALE));
    }

    public function compareTo(self $that): int
    {
        return $this->value->compareTo($that->value);
    }

    public function isEqualTo(self $that): bool
    {
        return $this->value->isEqualTo($that->value);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    public static function min(self $a, self $b): self
    {
        return $a->compareTo($b) <= 0 ? $a : $b;
    }

    public static function max(self $a, self $b): self
    {
        return $a->compareTo($b) >= 0 ? $a : $b;
    }

    /** The `numeric(18,2)` string the database stores. */
    public function toString(): string
    {
        return (string) $this->value->toScale(self::SCALE);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** Whether the decimal string is a quantity above zero — the guard of every share's whole. */
    public static function positive(string $quantity): bool
    {
        return BigNumber::of($quantity)->isPositive();
    }
}
