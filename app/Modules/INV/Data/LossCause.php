<?php

namespace App\Modules\INV\Data;

use Brick\Math\BigDecimal;

/**
 * L-38's causal rule, derived and never taken from a payload (DIR-024 D-3; SF-LOSS): a unit lost under an open case is that case's, on its causal project (CM-11); unusable units damaged at the receipt of a project-linked line are that project's, as far as that damage goes; anything else is the lot's owning company's.
 */
final class LossCause
{
    /**
     * @param  array{id: int, company: int, project: ?int, project_company: ?int, open?: ?BigDecimal}|null  $case  `open` what the case still answers for, null when its units are not counted
     * @param  array{project: int, company: int, open: BigDecimal}|null  $receipt  the damage at the lot's receipt not yet lost or repaired
     * @return list<array{company: int, project: ?int, case: ?int, case_company: ?int, qty: BigDecimal}>
     */
    public static function of(int $lotCompany, ?array $case, ?array $receipt, string $condition, BigDecimal $qty): array
    {
        $parts = [];
        $left = $qty;

        if ($case !== null) {
            $open = $case['open'] ?? null;
            $taken = $open === null ? $left : BigDecimal::min($left, $open);

            if ($taken->isPositive()) {
                $parts[] = ['company' => $case['project_company'] ?? $lotCompany, 'project' => $case['project'], 'case' => $case['id'], 'case_company' => $case['company'], 'qty' => $taken];
                $left = $left->minus($taken);
            }
        }

        if ($left->isPositive() && $receipt !== null && $condition === 'UNUSABLE' && $receipt['open']->isPositive()) {
            $damaged = BigDecimal::min($left, $receipt['open']);
            $parts[] = ['company' => $receipt['company'], 'project' => $receipt['project'], 'case' => null, 'case_company' => null, 'qty' => $damaged];
            $left = $left->minus($damaged);
        }

        return $left->isPositive() ? [...$parts, ['company' => $lotCompany, 'project' => null, 'case' => null, 'case_company' => null, 'qty' => $left]] : $parts;
    }
}
