<?php

namespace App\Modules\IAM\Data;

/**
 * The read classes of PERMISSIONS_MATRIX §7.4: each read of a page, signal or report belongs to one, which
 * names the capabilities that open it — inside the company scope where its class is S2–S4.
 */
enum ReadClass: string
{
    case MasterLookup = 'RD-01';
    case PooledPhysical = 'RD-02';
    case ProjectOperations = 'RD-03';
    case SalesReceivablesCash = 'RD-04';
    case ProcurementCost = 'RD-05';
    case Profitability = 'RD-06';
    case Consolidated = 'RD-07';
    case PrivateFiles = 'RD-08';
    case OwnerReview = 'RD-09';
    case AuditSecurity = 'RD-10';

    /**
     * The capabilities any one of which opens the class; none for the S0 master lookup.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::MasterLookup => [],
            self::PooledPhysical => ['stock.view'],
            self::ProjectOperations => ['projects.view'],
            self::SalesReceivablesCash => ['finance.view'],
            self::ProcurementCost => ['cost.view'],
            self::Profitability => ['profit.view'],
            self::Consolidated => ['reports.consolidated'],
            self::PrivateFiles => ['evidence.view'],
            self::OwnerReview => ['review.view', 'stock.resolve_unattributed', 'stock.attribute_surplus'],
            self::AuditSecurity => ['audit.view', 'security_log.view'],
        };
    }

    /**
     * Whether the viewer may read the class, for the given company where it names one. RD-08's evidence
     * family and RD-10's per-record timelines are checked by their reads (PJ-08, PJ-11).
     */
    public function admits(Authority $viewer, ?int $companyId = null): bool
    {
        // RD-01 is every active account's; RD-02 the pool's, with no company needed (§7.1; CS-05); RD-07, RD-09
        // and RD-10 the Owner's, through their OWNER_ONLY capabilities; the others a company-held family's.
        $audiences = match ($this) {
            self::MasterLookup => [Audience::all()],
            self::PooledPhysical => [Audience::pool()],
            self::Consolidated, self::OwnerReview, self::AuditSecurity => array_map(Audience::owner(...), $this->capabilities()),
            default => array_map(fn (string $capability): Audience => Audience::family($capability, $companyId), $this->capabilities()),
        };

        return Audience::anyOf(...$audiences)->admits($viewer);
    }
}
