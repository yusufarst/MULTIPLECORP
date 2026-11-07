<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The landing page (ROUTE-104; NAV-001; NAV-006; NAV-007): PG-006 *Beranda* for an account holding `review.view` — the
 * Owner's (OD-14) —, otherwise PG-007 *Pekerjaan*. A sign-in, a recovery code, an enrolment and a signed-in account
 * opening a sign-in page all go there, unless the sign-in resumes the address first asked for.
 */
final class Landing
{
    public const OWNER = '/beranda';

    public const ACCOUNT = '/pekerjaan';

    public static function of(?Authenticatable $user): string
    {
        if (! $user instanceof User) {
            return self::ACCOUNT;
        }

        return app(EffectiveCapabilities::class)->for($user)->has('review.view') ? self::OWNER : self::ACCOUNT;
    }
}
