<?php

namespace App\Http\Controllers;

use App\Support\HealthProbe;
use Illuminate\Http\Response;

/**
 * EN-03's minimal health check, ROUTE-277 and INT-277 (TASK-072; PRQ-03; SECURITY SX-03; RB-09): 200 when the application
 * answers, 503 with the same minimal answer when it cannot — no version, no path, no count, no record and no word of
 * which dependency failed. Registered outside the web group, so it starts no session and sets no cookie; the business
 * heartbeat's read-only smoke checks are the operator's (INFRASTRUCTURE §6).
 */
class HealthCheck
{
    public function __invoke(HealthProbe $probe): Response
    {
        $answers = $probe->answers();

        return response($answers ? 'OK' : 'UNAVAILABLE', $answers ? 200 : 503, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
