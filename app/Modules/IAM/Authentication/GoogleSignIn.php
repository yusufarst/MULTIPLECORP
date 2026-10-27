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

    /** A LINK attempt's verified identity, kept for the holder's confirming request for 10 minutes (AU-23). */
    public const LINK = 'auth.google_link';

    /** The flash key of PG-065's answer to a LINK attempt: `ready` or `failed`. */
    public const LINK_ANSWER = 'auth.google_link_answer';

    /** The address value that starts a LINK attempt from PG-065 (`?tujuan=tautkan`). */
    public const LINK_PURPOSE = 'tautkan';

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
        if ($request->query('tujuan') === self::LINK_PURPOSE) {
            return $this->startLink($request);
        }

        // A signed-in session never starts a sign-in: it goes home, as the `guest` middleware sent it (AU-22).
        if ($request->user() !== null) {
            return redirect('/');
        }

        if (! self::enabled()) {
            return $this->failure($request, 'NOT_CONFIGURED');
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

        if (is_array($attempt) && ($attempt['purpose'] ?? null) === 'LINK') {
            return $this->linkCallback($request, $attempt);
        }

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

    /**
     * The verified identity a LINK attempt keeps for this account, still within its 10 minutes (AU-23).
     *
     * @return array{sub: string, email: ?string}|null
     */
    public static function pendingLink(Request $request, User $account): ?array
    {
        $held = $request->session()->get(self::LINK);

        if (! is_array($held) || ($held['user'] ?? null) !== $account->id || ! is_string($held['sub'] ?? null)
            || ! is_int($held['expires_at'] ?? null) || $held['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        return ['sub' => $held['sub'], 'email' => is_string($held['email'] ?? null) ? $held['email'] : null];
    }

    /**
     * ROUTE-102 with `tujuan=tautkan` (AU-23): only the holder links, from its active session established with TOTP,
     * active TOTP on the account; the attempt records the account it is for.
     */
    private function startLink(Request $request): Response
    {
        $user = $request->user();

        if (! self::enabled() || ! $user instanceof User || $user->getAttribute('two_factor_confirmed_at') === null
            || $request->session()->get(TwoFactorChallenge::METHOD) !== EstablishSession::TOTP) {
            return $this->linkFailure($request, ! self::enabled() ? 'NOT_CONFIGURED' : 'NOT_ALLOWED');
        }

        $nonce = Str::random(40);
        $request->session()->put(self::ATTEMPT, ['purpose' => 'LINK', 'user' => $user->id, 'nonce' => $nonce, 'started_at' => now()->getTimestamp()]);

        return $this->provider($request)->redirectWithNonce($nonce);
    }

    /**
     * ROUTE-103 for a LINK attempt: Google's response verified in the session of the account the attempt recorded;
     * the verified `sub` and e-mail kept for 10 minutes; nothing is linked here (AU-22; AU-23).
     *
     * @param  array<mixed>  $attempt
     */
    private function linkCallback(Request $request, array $attempt): Response
    {
        $user = $request->user();

        if (! self::enabled() || ! $user instanceof User || ($attempt['user'] ?? null) !== $user->id || ! is_string($attempt['nonce'] ?? null)
            || ! is_int($attempt['started_at'] ?? null) || now()->getTimestamp() - $attempt['started_at'] > self::ATTEMPT_SECONDS) {
            return $this->linkFailure($request, 'NO_ATTEMPT');
        }

        $verified = $this->provider($request)->verifiedIdentity($attempt['nonce']);

        if ($verified['identity'] === null) {
            return $this->linkFailure($request, $verified['reason']);
        }

        $request->session()->put(self::LINK, [
            'user' => $user->id,
            'sub' => $verified['identity']['sub'],
            'email' => $verified['identity']['email'] ?? null,
            'expires_at' => now()->getTimestamp() + self::ATTEMPT_SECONDS,
        ]);
        $request->session()->flash(self::LINK_ANSWER, 'ready');

        return redirect('/akun');
    }

    /** A LINK attempt's failure answers generically on PG-065, recorded with its reason (AU-22; AU-23). */
    private function linkFailure(Request $request, string $reason): Response
    {
        $this->events->record(SecurityEventKind::GoogleSigninFailed, userId: is_numeric($request->user()?->getAuthIdentifier()) ? (int) $request->user()->getAuthIdentifier() : null, reasonCode: "LINK_{$reason}", routeName: $request->route()?->getName(), ipAddress: $request->ip(), userAgent: $request->userAgent());
        $request->session()->forget(['state', 'code_verifier']);
        $request->session()->flash(self::LINK_ANSWER, 'failed');

        return redirect($request->user() === null ? '/masuk' : '/akun');
    }

    /** One generic failure on PG-001, recorded with its reason; password sign-in stays (AU-22, AU-24). */
    private function failure(Request $request, string $reason, ?string $subject = null): Response
    {
        $this->events->record(SecurityEventKind::GoogleSigninFailed, reasonCode: $reason, routeName: $request->route()?->getName(), providerSubject: $subject, ipAddress: $request->ip(), userAgent: $request->userAgent());
        $request->session()->forget(['state', 'code_verifier']);
        $request->session()->flash(self::FAILED, true);

        return redirect('/masuk'.TwoFactorChallenge::addressQuery($request));
    }
}
