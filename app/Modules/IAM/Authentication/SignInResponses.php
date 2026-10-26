<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer after a sign-in (ROUTE-101; AU-06): the in-place dialog's JSON request is answered in place (IP-11);
 * the sign-in page loads its next page — the address first asked for, else the landing page — as a full document,
 * so no history state saved before the authentication boundary renders after it (H7-12).
 */
final class SignInResponses implements LoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $target = redirect()->intended(Fortify::redirects('login', '/'))->getTargetUrl();

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return $request->wantsJson() ? new JsonResponse(['signed_in' => true]) : redirect($target);
    }
}
