<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * ROUTE-102 `IAM RedirectToGoogle` and ROUTE-103 `IAM HandleGoogleCallback` (SECURITY AU-22; D6, K2): Google
 * sign-in for existing accounts only, always followed by the TOTP challenge — a Google sign-in alone never
 * establishes a session. The attempt marker, its purpose and its 10-minute age, and its `nonce` live in the
 * session; a SIGN_IN attempt starts only in a session with no authenticated account. At the callback a challenge
 * already pending in the session is led to unchanged, before every other check (M-43); otherwise a missing, stale
 * or mismatched marker or state, a failed token exchange, a failed ID-token check, an unknown or unlinked subject,
 * an inactive account, one without active TOTP and one in recovery (AU-20 b) all get one generic failure and
 * GOOGLE_SIGNIN_FAILED with its reason. The account is found by its subject alone — no registration, no automatic
 * linking. With no client configured, Google sign-in is off (GLC-09).
 */
final class GoogleSignIn
{
    public const ATTEMPT = 'auth.google_attempt';

    public const ATTEMPT_SECONDS = 600;

    /** The flash key of PG-001's generic Google failure. */
    public const FAILED = 'auth.google_failed';

    /** The container key of a Guzzle client that answers for Google — the tests' fake provider (SM-20). */
    public const HTTP_CLIENT = 'iam.google.http_client';

    public function __construct(
        private readonly GoogleIdentities $identities,
        private readonly SecurityEventRecorder $events,
    ) {}

    public static function enabled(): bool
    {
        return is_string(config('services.google.client_id')) && config('services.google.client_id') !== ''
            && is_string(config('services.google.client_secret')) && config('services.google.client_secret') !== '';
    }

    /** ROUTE-102: Google's consent page, the attempt's marker and nonce kept in the session. */
    public function start(Request $request): Response
    {
        if (! self::enabled() || $request->user() !== null) {
            return $this->failure($request, self::enabled() ? 'SESSION_AUTHENTICATED' : 'NOT_CONFIGURED');
        }

        $nonce = Str::random(40);
        $request->session()->put(self::ATTEMPT, ['purpose' => 'SIGN_IN', 'nonce' => $nonce, 'started_at' => now()->getTimestamp()]);

        return $this->provider($request)->redirectWithNonce($nonce);
    }

    /** ROUTE-103: Google's return, which changes no business state (RB-03). */
    public function callback(Request $request): Response
    {
        // M-43: a duplicate or replayed callback while a challenge is pending leads to it, consuming nothing.
        if (TwoFactorChallenge::current($request) !== null) {
            return RouteSecondFactor::toChallenge($request);
        }

        $attempt = $request->session()->pull(self::ATTEMPT);

        if (! self::enabled()) {
            return $this->failure($request, 'NOT_CONFIGURED');
        }

        if (! is_array($attempt) || ($attempt['purpose'] ?? null) !== 'SIGN_IN' || ! is_string($attempt['nonce'] ?? null)
            || ! is_int($attempt['started_at'] ?? null) || now()->getTimestamp() - $attempt['started_at'] > self::ATTEMPT_SECONDS) {
            return $this->failure($request, 'NO_ATTEMPT');
        }

        if ($request->user() !== null) {
            return $this->failure($request, 'SESSION_AUTHENTICATED');
        }

        $verified = $this->provider($request)->verifiedIdentity($attempt['nonce']);

        if ($verified['identity'] === null) {
            return $this->failure($request, $verified['reason']);
        }

        $account = $this->identities->accountFor($verified['identity']['sub']);
        $reason = match (true) {
            ! $account instanceof User => 'UNKNOWN_SUBJECT',
            $account->getAttribute('is_active') !== true => 'INACTIVE_ACCOUNT',
            $account->getAttribute('two_factor_confirmed_at') === null => 'NO_ACTIVE_TOTP',
            self::inRecovery($account) => 'IN_RECOVERY',
            default => null,
        };

        if ($reason !== null) {
            return $this->failure($request, $reason, $verified['identity']['sub']);
        }

        TwoFactorChallenge::begin($request, $account, TwoFactorChallenge::PURPOSE_GOOGLE);

        return RouteSecondFactor::toChallenge($request);
    }

    /**
     * AU-20 b: the account is in recovery while its latest TOTP event is a recovery code's use — read from the
     * audit's typed columns, never stored.
     */
    public static function inRecovery(User $account): bool
    {
        $latest = DB::table('audit_events')->where('entity_type', 'user')->where('entity_id', $account->id)
            ->whereIn('action', ['IAM.TotpEnrolled', 'IAM.TotpReplaced', 'IAM.TotpReset', 'IAM.TotpDisabled', 'IAM.RecoveryCodeUsed'])
            ->orderByDesc('id')->value('action');

        return $latest === 'IAM.RecoveryCodeUsed';
    }

    private function provider(Request $request): GoogleSignInProvider
    {
        $redirect = config('services.google.redirect');

        $provider = new GoogleSignInProvider(
            $request,
            config()->string('services.google.client_id'),
            config()->string('services.google.client_secret'),
            url(is_string($redirect) ? $redirect : '/masuk/google/callback'),
        );
        $client = app()->bound(self::HTTP_CLIENT) ? app(self::HTTP_CLIENT) : null;

        if ($client instanceof Client) {
            $provider->setHttpClient($client);
        }

        return $provider;
    }

    /** One generic failure on PG-001, recorded with its reason; password sign-in stays (AU-22, AU-24). */
    private function failure(Request $request, string $reason, ?string $subject = null): Response
    {
        $this->events->record(SecurityEventKind::GoogleSigninFailed, reasonCode: $reason, routeName: $request->route()?->getName(), providerSubject: $subject, ipAddress: $request->ip(), userAgent: $request->userAgent());
        $request->session()->flash(self::FAILED, true);

        return redirect('/masuk'.TwoFactorChallenge::addressQuery($request));
    }
}
