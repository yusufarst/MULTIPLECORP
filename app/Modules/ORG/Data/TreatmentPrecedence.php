<?php

namespace App\Modules\ORG\Data;

/**
 * DATABASE §12's precedence of tax treatment rules (DIR-024 D-1; BR-FIN-17): among the rules of one context and tax
 * type in force on a day — at most one global and one of the company, C-52 forbidding two of either —, the company's
 * overrides the global one; with neither, no treatment is configured and nothing is assumed (FS-13).
 */
final class TreatmentPrecedence
{
    /**
     * @param  list<array{company_id: int|null, treatment: string, evidence_required: bool}>  $inForce
     * @return array{company_id: int|null, treatment: string, evidence_required: bool}|null
     */
    public static function choose(array $inForce, int $companyId): ?array
    {
        $global = null;

        foreach ($inForce as $rule) {
            if ($rule['company_id'] === $companyId) {
                return $rule;
            }

            if ($rule['company_id'] === null) {
                $global = $rule;
            }
        }

        return $global;
    }
}
