<?php

namespace App\Http\Middleware;

use App\Http\BackgroundRequest;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * The framework's session middleware, except that a background request reads the session and never writes it
 * or refreshes its cookie (RV-06; RB-05; H7-13; HO-20): the session row's last activity stays the last
 * user-initiated request, so the 60-minute idle limit is the session lifetime itself (AU-07).
 */
class StartSessionUnlessBackground extends StartSession
{
    protected function addCookieToResponse(Response $response, Session $session): void
    {
        if (! BackgroundRequest::is(request())) {
            parent::addCookieToResponse($response, $session);
        }
    }

    protected function saveSession($request): void
    {
        if (! BackgroundRequest::is($request)) {
            parent::saveSession($request);
        }
    }
}
