<?php

namespace App\Modules\INV\Data;

use Brick\Math\BigDecimal;

/**
 * A count's variance split, pure (WF-INV-05; AX-06; L-15; DATABASE §5.6, §5.8): a surplus reverses the scope's pending cases first, then the identified losses, and only the rest becomes a finding; a shortage takes back an open finding the basis still carried, and the rest must be named exactly. No rule here chooses a company: no FIFO, oldest-first or proportion (DIR-026; FS-09).
 */
final class CountVariance
{
    /**
     * @param  array<int, BigDecimal>  $pending  case => pending quantity, in the order they are found
     * @return array{found: array<int, BigDecimal>, restored: BigDecimal, finding: BigDecimal}|null null when more is identified than remains
     */
    public static function surplus(BigDecimal $surplus, array $pending, BigDecimal $identified): ?array
    {
        $left = $surplus;
        $found = [];

        foreach ($pending as $case => $qty) {
            $take = BigDecimal::min($left, $qty);

            if ($take->isPositive()) {
                $found[$case] = $take;
                $left = $left->minus($take);
            }
        }

        if ($identified->isGreaterThan($left)) {
            return null;
        }

        return ['found' => $found, 'restored' => $identified, 'finding' => $left->minus($identified)];
    }

    /**
     * The shortage after the open finding the basis included gives way; its remaining surplus stays a finding.
     *
     * @return array{shortage: BigDecimal, withdrawn: BigDecimal, remaining: BigDecimal}
     */
    public static function shortage(BigDecimal $shortage, BigDecimal $openFinding): array
    {
        $withdrawn = BigDecimal::min($shortage, $openFinding);

        return ['shortage' => $shortage->minus($withdrawn), 'withdrawn' => $withdrawn, 'remaining' => $openFinding->minus($withdrawn)];
    }

    /** Whether the actor's split covers the shortage exactly: named lots, pending cases and conversions. */
    public static function covers(BigDecimal $shortage, BigDecimal $named, BigDecimal $pending, BigDecimal $converted): bool
    {
        return $named->plus($pending)->plus($converted)->isEqualTo($shortage);
    }

    /** An overlap group's bound at a member's recognition: raised to cover this member's exposure over what is already assigned, never lowered (C-37). */
    public static function groupBound(BigDecimal $current, BigDecimal $exposure, BigDecimal $assigned): BigDecimal
    {
        return BigDecimal::max($current, $exposure->plus($assigned));
    }

    /** An assignment within the company's recognition exposure and the group's bound (C-37). */
    public static function withinBounds(BigDecimal $assign, BigDecimal $exposure, BigDecimal $assignedOnCase, BigDecimal $bound, BigDecimal $assignedOnGroup): bool
    {
        return $assign->plus($assignedOnCase)->isLessThanOrEqualTo($exposure) && $assign->plus($assignedOnGroup)->isLessThanOrEqualTo($bound);
    }
}
