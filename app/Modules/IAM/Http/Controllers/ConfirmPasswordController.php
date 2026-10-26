<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\AttemptLogin;
use App\Modules\IAM\Authentication\VerificationRefused;
use App\Modules\IAM\Authentication\VerificationSlots;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ROUTE-109 `IAM ConfirmPassword` (AU-15; IP-10; INT-011): the framework's password confirmation, its time kept in
 * the session where RequireStepUp reads it for 15 minutes; a wrong password answers in the dialog with MSG-19,
 * throttled — five a minute per account, MSG-20 — and recorded (WS-12; LG-02); the verification within the
 * authenticated slot of H6-12, which a flood of sign-ins cannot take.
 */
final class ConfirmPasswordController
{
    public const PER_MINUTE = 5;

    public function __invoke(Request $request, SecurityEventRecorder $events, VerificationSlots $slots): JsonResponse
    {
        $user = $request->user();
        $id = $user?->getAuthIdentifier();
        $userId = is_numeric($id) ? (int) $id : null;
        $key = 'confirm-password:'.($userId ?? 'none');

        // H6-07: the attempt counted with one atomic increment before it is evaluated, so a burst gets five.
        if (RateLimiter::hit($key, 60) > self::PER_MINUTE) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = max(1, (int) ceil($seconds / 60));

            return response()->json(['message' => trans_choice('messages.msg_20', $minutes, ['count' => $minutes])], 429, ['Retry-After' => (string) $seconds]);
        }

        $password = AttemptLogin::password($request->input('password'));

        try {
            $matches = $user !== null && $password !== ''
                && $slots->authenticated('password.confirm', fn (): bool => Hash::check($password, $user->getAuthPassword()));
        } catch (VerificationRefused) {
            return response()->json(['message' => trans_choice('messages.msg_20', 1, ['count' => 1])], 429, ['Retry-After' => '1']);
        }

        if (! $matches) {
            $events->record(SecurityEventKind::PasswordConfirmationFailed, userId: $userId, routeName: 'password.confirm', ipAddress: $request->ip(), userAgent: $request->userAgent());

            return response()->json(['message' => __('messages.msg_19'), 'errors' => ['password' => [__('messages.msg_19')]]], 422);
        }

        RateLimiter::clear($key);
        $request->session()->put(RequireStepUp::CONFIRMED_AT, now()->getTimestamp());

        return response()->json(['confirmed' => true]);
    }
}
