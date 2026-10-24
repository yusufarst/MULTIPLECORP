<?php

namespace App\Modules\IAM\Policies;

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * One gate per capability of PERMISSIONS_MATRIX §4 (D-PM-07; SECURITY EN-02, EN-08): each answers from the
 * effective-capability resolver, never from a role name (AZ-02), and denies a guest and an inactive account
 * (default deny). Given a company id, a gate also needs that company in the account's scope (AZ-01).
 */
final class CapabilityGates
{
    public static function register(): void
    {
        foreach (CapabilityCatalogue::codes() as $code) {
            Gate::define($code, static function (?Authenticatable $user, ?int $companyId = null) use ($code): bool {
                return $user !== null && app(EffectiveCapabilities::class)->for($user)->allows($code, $companyId);
            });
        }
    }
}
