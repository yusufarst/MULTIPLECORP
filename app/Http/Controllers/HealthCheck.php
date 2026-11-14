<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * EN-03's minimal health check (TASK-072; PRQ-03; SECURITY SX-03): the application answers, nothing more — no version, no
 * dependency's state, no internals. Registered outside the web group, so it starts no session and sets no cookie; the
 * business heartbeat's read-only smoke checks are the operator's (INFRASTRUCTURE §6).
 */
class HealthCheck
{
    public function __invoke(): Response
    {
        return response('OK', 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
