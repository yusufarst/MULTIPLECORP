<?php

namespace App\Modules\ORG\Services;

use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The output taxes in force for a company on a business date (FS-13; DATABASE §12): each active OUTPUT_TAX type with its rate version and its SALES_INVOICE
 * treatment rule, the company's own before the group's. None configured, none returned: a rate or treatment is never assumed.
 */
final class OutputTaxes
{
    /**
     * @return list<array{tax_type_id: int, code: string, rate_percent: string, numerator: string, denominator: string, rounding: string, rule_id: int, treatment: string, evidence_required: bool}>
     */
    public function inForce(int $companyId, string $day): array
    {
        $taxes = [];

        foreach (DB::select(<<<'SQL'
            select t.id, t.code, r.rate_percent, r.base_numerator, r.base_denominator, r.rounding_mode, u.id as rule_id, u.treatment, u.evidence_required
            from tax_types t
            join tax_rate_versions r on r.tax_type_id = t.id and r.effective @> ?::date
            join lateral (
                select id, treatment, evidence_required from tax_treatment_rules u
                where u.tax_type_id = t.id and u.transaction_context = 'SALES_INVOICE' and u.effective @> ?::date and (u.company_id = ? or u.company_id is null)
                order by u.company_id nulls last limit 1
            ) u on true
            where t.category = 'OUTPUT_TAX' and t.is_active
            order by t.code
            SQL, [$day, $day, $companyId]) as $row) {
            if (is_object($row)) {
                $taxes[] = ['tax_type_id' => (int) Row::text($row, 'id'), 'code' => Row::text($row, 'code'), 'rate_percent' => Row::text($row, 'rate_percent'), 'numerator' => Row::text($row, 'base_numerator'),
                    'denominator' => Row::text($row, 'base_denominator'), 'rounding' => Row::text($row, 'rounding_mode'), 'rule_id' => (int) Row::text($row, 'rule_id'), 'treatment' => Row::text($row, 'treatment'), 'evidence_required' => ($row->evidence_required ?? false) === true];
            }
        }

        return $taxes;
    }
}
