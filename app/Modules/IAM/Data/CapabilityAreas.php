<?php

namespace App\Modules\IAM\Data;

use App\Support\Audit\AuthorityClass;

/**
 * PT-30's and H7-06's grouping of the grant editor and the preview "by area" (TASK-024): a Level 1 presentation of
 * PERMISSIONS_MATRIX §4 — the view capabilities first, then the WORKFLOWS §4 rows the catalogue maps, every ADM_PLUS
 * capability under "Kewenangan khusus" (GL-036; RG-08). The OWNER_ONLY capabilities belong to no area: they are never
 * offered for an account (RG-02; OD-02).
 */
final class CapabilityAreas
{
    /** The areas in their order on the page. */
    public const ORDER = ['view', 'master', 'projects', 'operations', 'finance', 'import', 'special'];

    /** WORKFLOWS §4 rows by area; a capability takes the first row's area that names it. */
    private const ROWS = [
        'W4-10' => 'master',
        'W4-11' => 'projects', 'W4-12' => 'projects', 'W4-15' => 'projects', 'W4-16' => 'projects', 'W4-24' => 'projects',
        'W4-13' => 'operations',
        'W4-14' => 'finance', 'W4-17' => 'finance',
        'W4-25' => 'import', 'W4-27' => 'import',
    ];

    /** The area of a capability an account may hold, or null for an OWNER_ONLY one. */
    public static function of(string $code): ?string
    {
        $class = CapabilityCatalogue::classOf($code);

        if ($class === AuthorityClass::OwnerOnly) {
            return null;
        }

        if ($class === AuthorityClass::AdmPlus) {
            return 'special';
        }

        if (in_array($code, CapabilityCatalogue::VIEWS, true)) {
            return 'view';
        }

        foreach (self::ROWS as $row => $area) {
            if (in_array($code, CapabilityCatalogue::WORKFLOW_ROWS[$row][1], true)) {
                return $area;
            }
        }

        return 'projects';
    }

    /**
     * Every capability an account may be granted, by area in the page's order, each in the catalogue's order.
     *
     * @return array<string, list<string>>
     */
    public static function grantable(): array
    {
        $areas = array_fill_keys(self::ORDER, []);

        foreach (CapabilityCatalogue::codes() as $code) {
            $area = self::of($code);

            if ($area !== null) {
                $areas[$area][] = $code;
            }
        }

        return array_filter($areas, fn (array $codes): bool => $codes !== []);
    }
}
