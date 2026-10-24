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
        if ($this->capabilities() === []) {
            return Audience::all()->admits($viewer);
        }

        $pooled = in_array($this, [self::PooledPhysical, self::Consolidated, self::OwnerReview, self::AuditSecurity], true);

        return Audience::anyOf(...array_map(
            fn (string $capability): Audience => Audience::family($capability, $pooled ? null : $companyId),
            $this->capabilities(),
        ))->admits($viewer);
    }
}
