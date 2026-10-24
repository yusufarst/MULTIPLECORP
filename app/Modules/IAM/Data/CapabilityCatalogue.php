<?php

namespace App\Modules\IAM\Data;

use App\Support\Audit\AuthorityClass;
use InvalidArgumentException;

/**
 * The capability catalogue of PERMISSIONS_MATRIX §4 — 80 capabilities, ADM 37, ADM_PLUS 26 and OWNER_ONLY 17
 * — with each capability's class, its static prerequisites (RG-06) and the WORKFLOWS §4 mapping of §5
 * (W4-01–W4-27). A prerequisite is another capability or GRANT, at least one company in scope; a
 * capability whose prerequisite is the view capability of its subject, its report or its record is
 * CONTEXTUAL, that view checked by the action or query that knows the subject. The `capabilities` rows
 * are written from this list (TASK-005's table; `CatalogueSync`); nothing outside it is a capability.
 */
final class CapabilityCatalogue
{
    /** At least one company in scope (PERMISSIONS_MATRIX §4, "grant"). */
    public const GRANT = 'grant';

    /** The subject family's, the report's, the decision's or the record's view capability, checked in context. */
    public const CONTEXTUAL = 'contextual';

    /**
     * code => [class, prerequisites]: the rows of PERMISSIONS_MATRIX §4, in its order.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const CAPABILITIES = [
        'stock.view' => ['ADM', []],
        'projects.view' => ['ADM', [self::GRANT]],
        'finance.view' => ['ADM', [self::GRANT]],
        'cost.view' => ['ADM', [self::GRANT]],
        'profit.view' => ['ADM', ['finance.view', 'cost.view']],
        'evidence.view' => ['ADM', [self::GRANT]],
        'reports.export' => ['ADM', [self::CONTEXTUAL]],
        'reports.consolidated' => ['OWNER_ONLY', []],
        'review.view' => ['OWNER_ONLY', []],
        'audit.view' => ['OWNER_ONLY', []],
        'security_log.view' => ['OWNER_ONLY', []],
        'scope.all_companies' => ['OWNER_ONLY', []],
        'users.manage' => ['OWNER_ONLY', []],
        'permissions.manage' => ['OWNER_ONLY', []],
        'companies.manage' => ['OWNER_ONLY', []],
        'tax.configure' => ['OWNER_ONLY', []],
        'requirements.set_waiver_eligibility' => ['OWNER_ONLY', []],
        'requirements.relax' => ['OWNER_ONLY', []],
        'writeoff.supersede' => ['OWNER_ONLY', []],
        'stock.attribute_surplus' => ['OWNER_ONLY', []],
        'stock.resolve_unattributed' => ['OWNER_ONLY', []],
        'receivables.write_off' => ['OWNER_ONLY', []],
        'projects.force_complete' => ['OWNER_ONLY', []],
        'import.commit' => ['OWNER_ONLY', []],
        'parties.manage' => ['ADM', ['projects.view']],
        'products.edit' => ['ADM', []],
        'warehouse.manage' => ['ADM', ['stock.view']],
        'projects.manage' => ['ADM', ['projects.view']],
        'quotation.manage' => ['ADM', ['projects.view', 'finance.view']],
        'projects.confirm' => ['ADM', ['projects.view']],
        'stock.reserve' => ['ADM', ['stock.view', 'projects.view']],
        'purchase.manage' => ['ADM', ['cost.view']],
        'stock.receive' => ['ADM', ['stock.view']],
        'stock.dispatch' => ['ADM', ['stock.view', 'projects.view']],
        'stock.move' => ['ADM', ['stock.view']],
        'stock.count' => ['ADM', ['stock.view']],
        'delivery.record' => ['ADM', ['projects.view']],
        'dropship.confirm' => ['ADM', ['projects.view']],
        'handover.record' => ['ADM', ['projects.view']],
        'documents.issue' => ['ADM', [self::CONTEXTUAL]],
        'evidence.upload' => ['ADM', ['evidence.view']],
        'invoice.issue' => ['ADM', ['finance.view']],
        'billing.record' => ['ADM', ['finance.view']],
        'payment.record' => ['ADM', ['finance.view']],
        'payment.apply' => ['ADM', ['finance.view']],
        'expense.record' => ['ADM', ['cost.view']],
        'disbursement.record' => ['ADM', ['finance.view']],
        'cashin.record' => ['ADM', ['finance.view']],
        'bank_statement.record' => ['ADM', ['finance.view', 'evidence.upload']],
        'requirements.manage' => ['ADM', ['projects.view']],
        'projects.complete' => ['ADM', ['projects.view']],
        'receivables.dispute' => ['ADM', ['finance.view']],
        'requirements.waive' => ['ADM', ['projects.view']],
        'import.prepare' => ['ADM', ['projects.view', self::CONTEXTUAL]],
        'stock.reverse' => ['ADM_PLUS', ['stock.view']],
        'sales_return.record' => ['ADM_PLUS', ['stock.view', 'projects.view']],
        'purchase_return.record' => ['ADM_PLUS', ['stock.view', 'cost.view']],
        'purchase.correct_cost' => ['ADM_PLUS', ['cost.view']],
        'finance.contra' => ['ADM_PLUS', ['finance.view']],
        'payment.reallocate' => ['ADM_PLUS', ['finance.view']],
        'invoice.correct' => ['ADM_PLUS', ['finance.view']],
        'documents.correct' => ['ADM_PLUS', [self::CONTEXTUAL]],
        'billing.correct' => ['ADM_PLUS', ['finance.view']],
        'decisions.supersede' => ['ADM_PLUS', [self::CONTEXTUAL]],
        'fulfillment.correct' => ['ADM_PLUS', ['projects.view']],
        'warnings.override' => ['ADM_PLUS', [self::CONTEXTUAL]],
        'cash.correct' => ['ADM_PLUS', ['finance.view']],
        'purchase.cancel' => ['ADM_PLUS', ['cost.view']],
        'refund.record' => ['ADM_PLUS', ['finance.view']],
        'settlement.record' => ['ADM_PLUS', ['finance.view', 'evidence.view']],
        'stock.adjust' => ['ADM_PLUS', ['stock.view']],
        'stock.condition' => ['ADM_PLUS', ['stock.view']],
        'stock.count_apply' => ['ADM_PLUS', ['stock.view']],
        'stock.resolve_unattributed_evidence' => ['ADM_PLUS', ['stock.view', 'evidence.view']],
        'stock.allocate_intercompany' => ['ADM_PLUS', ['stock.view']],
        'projects.cancel_scope' => ['ADM_PLUS', ['projects.view']],
        'delivery.close' => ['ADM_PLUS', ['projects.view']],
        'purchase.close_remainder' => ['ADM_PLUS', ['cost.view']],
        'cases.close' => ['ADM_PLUS', ['projects.view']],
        'projects.cancel' => ['ADM_PLUS', ['projects.view']],
    ];

    /**
     * The view, report and scope capabilities (§4's counts: ADM 7, OWNER_ONLY 5). Every other capability is
     * a command capability, which also needs at least one company in scope (§4; CS-05).
     *
     * @var list<string>
     */
    public const VIEWS = [
        'stock.view', 'projects.view', 'finance.view', 'cost.view', 'profit.view', 'evidence.view', 'reports.export',
        'reports.consolidated', 'review.view', 'audit.view', 'security_log.view', 'scope.all_companies',
    ];

    /**
     * WORKFLOWS §4 mapping (PERMISSIONS_MATRIX §5): row => [class, capabilities]. OWN! rows map to OWNER_ONLY,
     * ADM rows to ADM and ADM+ rows to ADM_PLUS; W4-25 splits its actors; SYS and BG grant nothing of their own.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const WORKFLOW_ROWS = [
        'W4-01' => ['OWN!', ['users.manage', 'permissions.manage']],
        'W4-02' => ['OWN!', ['companies.manage', 'tax.configure']],
        'W4-03' => ['OWN!', ['requirements.set_waiver_eligibility']],
        'W4-04' => ['OWN!', ['requirements.relax']],
        'W4-05' => ['OWN!', ['writeoff.supersede']],
        'W4-06' => ['OWN!', ['stock.attribute_surplus']],
        'W4-07' => ['OWN!', ['stock.resolve_unattributed']],
        'W4-08' => ['OWN!', ['receivables.write_off']],
        'W4-09' => ['OWN!', ['projects.force_complete']],
        'W4-10' => ['ADM', ['parties.manage', 'products.edit', 'warehouse.manage']],
        'W4-11' => ['ADM', ['projects.manage', 'quotation.manage', 'projects.confirm']],
        'W4-12' => ['ADM', ['stock.reserve']],
        'W4-13' => ['ADM', ['purchase.manage', 'stock.receive', 'stock.dispatch', 'delivery.record', 'dropship.confirm', 'handover.record', 'stock.move', 'stock.count', 'documents.issue']],
        'W4-14' => ['ADM', ['documents.issue', 'evidence.upload', 'invoice.issue', 'billing.record', 'payment.record', 'payment.apply', 'expense.record', 'disbursement.record', 'cashin.record', 'bank_statement.record']],
        'W4-15' => ['ADM', ['requirements.manage']],
        'W4-16' => ['ADM', ['projects.complete']],
        'W4-17' => ['ADM', ['receivables.dispute']],
        'W4-18' => ['ADM+', ['stock.reverse', 'sales_return.record', 'purchase_return.record', 'purchase.correct_cost', 'finance.contra', 'payment.reallocate', 'invoice.correct', 'documents.correct', 'billing.correct', 'decisions.supersede', 'fulfillment.correct', 'warnings.override', 'cash.correct']],
        'W4-19' => ['ADM+', ['purchase.cancel']],
        'W4-20' => ['ADM+', ['refund.record', 'settlement.record', 'finance.contra']],
        'W4-21' => ['ADM+', ['stock.adjust', 'stock.condition', 'stock.count_apply', 'stock.resolve_unattributed_evidence']],
        'W4-22' => ['ADM+', ['stock.allocate_intercompany']],
        'W4-23' => ['ADM+', ['projects.cancel_scope', 'delivery.close', 'purchase.close_remainder', 'cases.close', 'projects.cancel']],
        'W4-24' => ['ADM', ['requirements.waive']],
        'W4-25' => ['ADM / OWN', ['import.prepare', 'import.commit']],
        'W4-26' => ['SYS', []],
        'W4-27' => ['BG', ['reports.export']],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    public static function has(string $code): bool
    {
        return isset(self::CAPABILITIES[$code]);
    }

    public static function classOf(string $code): AuthorityClass
    {
        return AuthorityClass::from(self::CAPABILITIES[$code][0] ?? throw new InvalidArgumentException("{$code} is not a capability of PERMISSIONS_MATRIX §4."));
    }

    public static function isOwnerOnly(string $code): bool
    {
        return self::classOf($code) === AuthorityClass::OwnerOnly;
    }

    /**
     * The capability prerequisites of a code, without GRANT and CONTEXTUAL.
     *
     * @return list<string>
     */
    public static function prerequisites(string $code): array
    {
        self::classOf($code);

        return array_values(array_filter(self::CAPABILITIES[$code][1], fn (string $prerequisite): bool => self::has($prerequisite)));
    }

    /** Whether the code is effective only with at least one company in scope: its GRANT prerequisite, or a command. */
    public static function needsCompany(string $code): bool
    {
        self::classOf($code);

        return in_array(self::GRANT, self::CAPABILITIES[$code][1], true) || ! in_array($code, self::VIEWS, true);
    }
}
