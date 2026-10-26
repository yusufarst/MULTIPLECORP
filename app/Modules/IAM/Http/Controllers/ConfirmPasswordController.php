<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\AttemptLogin;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ROUTE-109 `IAM ConfirmPassword` (AU-15; IP-10; INT-011): the framework's password confirmation, its time kept in
 * the session where RequireStepUp reads it for 15 minutes; a wrong password answers in the dialog with MSG-19,
 * throttled — five a minute per account, MSG-20 — and recorded (WS-12; LG-02).
 */
final class ConfirmPasswordController
{
    public const PER_MINUTE = 5;

    public function __invoke(Request $request, SecurityEventRecorder $events): JsonResponse
    {
        $user = $request->user();
        $id = $user?->getAuthIdentifier();
        $userId = is_numeric($id) ? (int) $id : null;
        $key = 'confirm-password:'.($userId ?? 'none');

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['message' => trans_choice('messages.msg_20', 1, ['count' => max(1, (int) ceil($seconds / 60))])], 429, ['Retry-After' => (string) $seconds]);
        }

        $password = AttemptLogin::password($request->input('password'));

        if ($user === null || $password === '' || ! Hash::check($password, $user->getAuthPassword())) {
            RateLimiter::hit($key, 60);
            $events->record(SecurityEventKind::PasswordConfirmationFailed, userId: $userId, routeName: 'password.confirm', ipAddress: $request->ip(), userAgent: $request->userAgent());

            return response()->json(['message' => __('messages.msg_19'), 'errors' => ['password' => [__('messages.msg_19')]]], 422);
        }

        RateLimiter::clear($key);
        $request->session()->put(RequireStepUp::CONFIRMED_AT, time());

        return response()->json(['confirmed' => true]);
    }
}
