<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer after a sign-in (ROUTE-101, ROUTE-104, ROUTE-105; AU-06): the in-place dialog's JSON request is
 * answered in place (IP-11); the sign-in pages load the next page — the address first asked for, else the landing
 * page — as a full document, so no history state saved before the authentication boundary renders after it
 * (H7-12). An enrolment-only session goes to PG-004 alone, and tells the dialog it cannot resume the page
 * (AU-18; AUTH_JOURNEYS §2.5 step 5).
 */
final class SignInResponses implements LoginResponse
{
    public const ENROLMENT = '/verifikasi-dua-langkah';

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $enrolmentOnly = TwoFactorChallenge::enrolmentOnly($request);
        $target = $enrolmentOnly ? self::ENROLMENT : redirect()->intended(Fortify::redirects('login', '/'))->getTargetUrl();

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        if ($request->wantsJson()) {
            return new JsonResponse($enrolmentOnly ? ['signed_in' => true, 'enrolment_only' => true, 'location' => $target] : ['signed_in' => true]);
        }

        return redirect($target);
    }
}
