<?php

namespace App\Modules\IAM\Data;

use Closure;

/**
 * The special projections of PERMISSIONS_MATRIX §7.3 that every resource shares: the relation marker for a
 * company outside the viewer's scope (PJ-04), the two sides of an inter-company allocation (PJ-05, PJ-06;
 * XL-1) and a derived figure's entitlement (PJ-22).
 */
final class Projection
{
    /** PJ-04: what a viewer sees of a company outside its scope — never its name, code or identifier. */
    public const ANOTHER_COMPANY = ['relation' => 'ANOTHER_COMPANY'];

    /**
     * PJ-04: the company named within scope, the relation marker outside it — its fields built only within.
     *
     * @param  Closure(): array<string, mixed>  $company  the company's own projected fields
     * @return array<string, mixed>
     */
    public static function company(Authority $viewer, int $companyId, Closure $company): array
    {
        return $viewer->active && $viewer->scope->includes($companyId) ? $company() : self::ANOTHER_COMPANY;
    }

    /**
     * PJ-05 and PJ-06 (XL-1): an allocation row — a consumption or a lot draw whose source company is not its
     * bearer — as each side's `cost.view` holder sees it. The bearer side sees the cost in full as its
     * project's own, the source side's identifiers masked unless that company is in scope too; the source
     * side sees the draw from its lot, the bearer's company, project, reason and actor masked unless in scope.
     * Neither side sees anything without `cost.view` of its own company. The bearer's project, a project
     * reference, also needs `projects.view` of the bearer company (§7.1).
     *
     * @param  array<string, mixed>  $common  quantity, date, cost and the allocation marker
     * @param  array<string, mixed>  $sourceSide  the source company and its lot's acquisition references
     * @param  array<string, mixed>  $bearerSide  the bearer company, reason and actor
     * @param  array<string, mixed>  $bearerProject  the bearer's project reference
     * @return array<string, mixed>|null null when the viewer sees neither side
     */
    public static function allocation(Authority $viewer, int $sourceCompanyId, int $bearerCompanyId, array $common, array $sourceSide, array $bearerSide, array $bearerProject = []): ?array
    {
        $source = $viewer->allows('cost.view', $sourceCompanyId);
        $bearer = $viewer->allows('cost.view', $bearerCompanyId);

        if (! $source && ! $bearer) {
            return null;
        }

        $bearerFields = $bearer ? $bearerSide : self::ANOTHER_COMPANY;

        if ($bearer && $bearerProject !== [] && $viewer->allows('projects.view', $bearerCompanyId)) {
            $bearerFields['project'] = $bearerProject;
        }

        return [
            ...$common,
            'source' => $source ? $sourceSide : self::ANOTHER_COMPANY,
            'bearer' => $bearerFields,
        ];
    }

    /**
     * PJ-22: a derived figure takes the highest class and every family of its inputs, so it is shown only to
     * a viewer entitled to each of them, for the figure's company.
     *
     * @param  list<string>  $familyCapabilities  the view capability of each input's family
     */
    public static function admitsDerived(Authority $viewer, array $familyCapabilities, ?int $companyId = null): bool
    {
        foreach ($familyCapabilities as $capability) {
            if (! Audience::family($capability, $companyId)->admits($viewer)) {
                return false;
            }
        }

        return $viewer->active;
    }
}
