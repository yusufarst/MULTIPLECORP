<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The step of ROUTE-101 after the password (SECURITY AU-16, AU-18): an account with active TOTP — its
 * confirmation time set — gets the pending challenge and no session, and is sent to PG-002; a high-risk account
 * without it is signed in to an enrolment-only session, which reaches PG-004 alone; any other account is signed
 * in. The sign-in page follows with a full document load, the in-place re-login of IP-11 with the JSON answer
 * `two_factor` and the code step in the same dialog (AUTH_JOURNEYS §2.5).
 */
final class RouteSecondFactor
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->attributes->get(AttemptLogin::VERIFIED);

        if (! $user instanceof User) {
            throw new LogicException('RouteSecondFactor follows AttemptLogin (ROUTE-101).');
        }

        if ($user->getAttribute('two_factor_confirmed_at') !== null) {
            TwoFactorChallenge::begin($request, $user, TwoFactorChallenge::PURPOSE_PASSWORD);

            return self::toChallenge($request);
        }

        $this->guard->login($user);

        if ($this->capabilities->requiresTotp($user->id)) {
            $request->session()->put(TwoFactorChallenge::ENROLMENT_ONLY, true);
        }

        return $next($request);
    }

    /** PG-002: a full document load from the sign-in page, the dialog's code step for the in-place re-login. */
    public static function toChallenge(Request $request): Response
    {
        $target = '/masuk/verifikasi'.TwoFactorChallenge::addressQuery($request);

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return $request->wantsJson() ? new JsonResponse(['two_factor' => true]) : redirect($target);
    }
}
