<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Modules\IAM\Authentication\GoogleSignIn;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PG-001 "Lanjutkan dengan Google" (ROUTE-102, INT-102) and Google's callback (ROUTE-103, INT-103): SECURITY
 * AU-22's flow, IAM's own, always followed by PG-002's challenge (K2).
 */
final class GoogleSignInController
{
    public function redirect(Request $request, GoogleSignIn $google): Response
    {
        return $google->start($request);
    }

    public function callback(Request $request, GoogleSignIn $google): Response
    {
        return $google->callback($request);
    }
}
