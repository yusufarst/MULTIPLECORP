<?php

namespace App\Modules\IAM\Data;

/**
 * The five intentional cross-company link families of PERMISSIONS_MATRIX §8 (XL-1–XL-5), each with the
 * columns of DATABASE §15's inventory that carry it, who may create it and what each side sees. No other
 * cross-company reference is creatable (CS-04; C-32): the composite company keys refuse it.
 */
enum CrossCompanyLink: string
{
    case AllocationOverlay = 'XL-1';
    case CorrectionCaseSide = 'XL-2';
    case CorrectionCaseContext = 'XL-3';
    case LinkedProject = 'XL-4';
    case InterCompanyTransfer = 'XL-5';

    /**
     * The `table.column` pairs DATABASE §15 lists for the family.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return match ($this) {
            self::AllocationOverlay => ['stock_consumptions.source_company_id', 'stock_consumptions.bearer_company_id', 'cost_attributions.source_company_id', 'cost_attributions.bearer_company_id'],
            self::CorrectionCaseSide => ['stock_consumptions.case_company_id', 'stock_restorations.case_company_id', 'cost_attributions.case_company_id'],
            self::CorrectionCaseContext => ['correction_cases.causal_project_id', 'correction_cases.source_lot_id'],
            self::LinkedProject => ['projects.linked_project_id', 'projects.link_reason'],
            self::InterCompanyTransfer => ['payments.transfer_from_disbursement_id', 'other_cash_receipts.transfer_from_disbursement_id'],
        };
    }

    /**
     * XL-1: a new allocation needs `stock.allocate_intercompany` and a grant on the consuming (bearer) company
     * only, beside the command's own capability (C-28).
     */
    public static function mayAllocate(Authority $actor, int $bearerCompanyId): bool
    {
        return $actor->allows('stock.allocate_intercompany', $bearerCompanyId);
    }

    /** XL-2: a case side is set with the case company in scope; the other side's rows are SYS consequences. */
    public static function maySetCaseSide(Authority $actor, int $caseCompanyId): bool
    {
        return $actor->active && $actor->scope->includes($caseCompanyId);
    }

    /** XL-4: `projects.manage` with both companies in scope — an actor cannot link to a project it cannot see. */
    public static function mayLinkProjects(Authority $actor, int $companyId, int $linkedCompanyId): bool
    {
        return $actor->allows('projects.manage', $companyId) && $actor->allows('projects.manage', $linkedCompanyId);
    }

    /**
     * XL-5: the citing Cash-In needs its capability — `payment.record` or `cashin.record` — in the receiving
     * company and `finance.view` of the cited disbursement's company; otherwise the Owner records it.
     */
    public static function mayCiteTransfer(Authority $actor, string $capability, int $receivingCompanyId, int $payingCompanyId): bool
    {
        return in_array($capability, ['payment.record', 'cashin.record'], true)
            && $actor->allows($capability, $receivingCompanyId)
            && $actor->allows('finance.view', $payingCompanyId);
    }
}
