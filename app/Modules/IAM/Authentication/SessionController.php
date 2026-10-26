<?php

namespace App\Modules\IAM\Authentication;

use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/**
 * ROUTE-101 `IAM AttemptLogin` and ROUTE-108 `IAM Logout`: Fortify's session controller behind IAM's boundary
 * (ARCHITECTURE §8) — the sign-in through IAM's pipeline (AttemptLogin), the logout through the framework's
 * logout, the session invalidated and the forgery token renewed (AU-06) —, so no route names the library.
 */
final class SessionController extends AuthenticatedSessionController {}
