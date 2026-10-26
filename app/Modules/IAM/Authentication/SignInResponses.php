<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answers after a sign-in and a logout (ROUTE-101, ROUTE-108; AU-06): the in-place dialog's JSON request is
 * answered in place (IP-11); the sign-in page and the logout load their next page as a full document, so no history
 * state saved before the authentication boundary renders after it (H7-12).
 */
final class SignInResponses implements LoginResponse, LogoutResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $signedIn = $request->user() !== null;
        $target = $signedIn ? redirect()->intended(Fortify::redirects('login', '/'))->getTargetUrl() : url(Fortify::redirects('logout', '/masuk'));

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        if ($request->wantsJson()) {
            return $signedIn ? new JsonResponse(['signed_in' => true]) : new JsonResponse(status: 204);
        }

        return redirect($target);
    }
}
