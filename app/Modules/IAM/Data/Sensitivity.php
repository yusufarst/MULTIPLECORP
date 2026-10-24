<?php

namespace App\Modules\IAM\Data;

/**
 * The scope and sensitivity classes of DOMAIN_MODEL with the Admin entitlements of PERMISSIONS_MATRIX §7.1:
 * S0 every active account, S1 `stock.view` pool-wide, S2–S4 a company in scope with the family's view
 * capability. The Owner holds every class.
 */
enum Sensitivity: string
{
    case S0 = 'S0';
    case S1 = 'S1';
    case S2 = 'S2';
    case S3 = 'S3';
    case S4 = 'S4';

    /**
     * Whether the viewer may see a field of this class, of the family whose view capability is named, held by
     * the given company; S0 and S1 name no company and no family.
     */
    public function admits(Authority $viewer, ?string $familyCapability = null, ?int $companyId = null): bool
    {
        return match ($this) {
            self::S0 => Audience::all()->admits($viewer),
            self::S1 => Audience::pool()->admits($viewer),
            default => $familyCapability !== null && Audience::family($familyCapability, $companyId)->admits($viewer),
        };
    }
}
