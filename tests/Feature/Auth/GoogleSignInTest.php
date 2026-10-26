<?php

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Authentication\GoogleSignInProvider;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 and TL-14 (TASK-019; SM-20; M-41–M-43): Google sign-in with the provider faked — no test calls Google —:
// the redirect's scopes, prompt, PKCE, state and nonce; the callback's one call, the token exchange, its ID token
// checked and the subject read from it alone; an existing linked account with active TOTP reaching PG-002 and
// nothing else, every other case one generic failure; a callback while a challenge is pending leading to it
// unchanged; Google sign-in off with no client configured — acceptance criteria 1, 2 and 4.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->travelTo(now()->setTimestamp(1_800_000_015));
    config(['services.google.client_id' => 'klien-uji', 'services.google.client_secret' => 'rahasia-klien-uji']);
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
    DB::table('user_external_identities')->insert(['user_id' => $this->user->id, 'provider' => 'google', 'provider_subject' => 'sub-laras', 'email_at_link' => 'laras@gmail.example', 'linked_at' => now(), 'linked_by' => $this->user->id]);
    $this->sent = new ArrayObject;
});

afterEach(fn () => @unlink($this->hotFile));

/** Google's token endpoint, faked: each queued answer in turn, every request kept. */
function fakeGoogle(array $answers): void
{
    $stack = HandlerStack::create(new MockHandler($answers));
    $sent = test()->sent;
    $stack->push(Middleware::history($sent));
    app()->instance(GoogleSignIn::HTTP_CLIENT, new Client(['handler' => $stack]));
}

function tokenAnswer(array $overrides = [], ?string $nonce = null): GuzzleResponse
{
    $claims = [...['iss' => 'https://accounts.google.com', 'aud' => 'klien-uji', 'sub' => 'sub-laras', 'email' => 'laras@gmail.example', 'nonce' => $nonce ?? session(GoogleSignIn::ATTEMPT)['nonce'], 'exp' => now()->getTimestamp() + 600], ...$overrides];
    $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

    return new GuzzleResponse(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'akses-uji', 'id_token' => $encode(['alg' => 'RS256']).'.'.$encode($claims).'.tanda', 'expires_in' => 3599]));
}

/** ROUTE-102, then ROUTE-103 with Google's answer: the state and code Google would send back. */
function googleCallback(callable $answer, array $query = []): TestResponse
{
    test()->get('/masuk/google')->assertRedirect();
    fakeGoogle([$answer()]);

    return test()->get('/masuk/google/callback?'.http_build_query([...['state' => session('state'), 'code' => 'kode-uji'], ...$query]));
}

function googleFailures(): array
{
    return DB::table('security_events')->where('event_kind', 'GOOGLE_SIGNIN_FAILED')->orderBy('id')->pluck('reason_code')->all();
}

it('sends Google the code flow with openid email only, select_account, PKCE S256, the state and the nonce (AU-22; INT-102)', function () {
    $location = $this->get('/masuk/google')->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->and($query)->toMatchArray(['client_id' => 'klien-uji', 'scope' => 'openid email', 'response_type' => 'code', 'prompt' => 'select_account', 'code_challenge_method' => 'S256', 'redirect_uri' => url('/masuk/google/callback')])
        ->and($query['state'])->toBe(session('state'))
        ->and($query['nonce'])->toBe(session(GoogleSignIn::ATTEMPT)['nonce'])
        ->and($query['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', (string) session('code_verifier'), true)), '+/', '-_'), '='))
        ->and(session(GoogleSignIn::ATTEMPT)['purpose'])->toBe('SIGN_IN');
});

it('takes a linked account with active TOTP to PG-002\'s challenge and signs nothing in (AU-22; K2; criterion 2)', function () {
    googleCallback(fn () => tokenAnswer())->assertRedirect('/masuk/verifikasi');

    expect(auth()->check())->toBeFalse()
        ->and(session(TwoFactorChallenge::KEY)['purpose'])->toBe(TwoFactorChallenge::PURPOSE_GOOGLE)
        ->and(session(TwoFactorChallenge::KEY)['user'])->toBe($this->user->id)
        ->and(session(GoogleSignIn::ATTEMPT))->toBeNull()
        ->and($this->sent)->toHaveCount(1);

    // The one call: the token exchange, with the PKCE verifier and without the authorization request's parameters.
    $request = $this->sent[0]['request'];
    parse_str((string) $request->getBody(), $form);
    expect((string) $request->getUri())->toBe('https://www.googleapis.com/oauth2/v4/token')
        ->and($form)->toHaveKeys(['code', 'code_verifier', 'client_secret', 'redirect_uri'])->not->toHaveKeys(['nonce', 'prompt'])
        ->and($this->sent[0]['options']['connect_timeout'] ?? null)->toBe(GoogleSignInProvider::CONNECT_TIMEOUT)
        ->and($this->sent[0]['options']['timeout'] ?? null)->toBe(GoogleSignInProvider::TIMEOUT);

    // The challenge after Google: a TOTP code only, never a recovery code (AU-19).
    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[0]])->assertStatus(422);
    googleCallback(fn () => tokenAnswer())->assertRedirect('/masuk/verifikasi');
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    expect(auth()->id())->toBe($this->user->id);
});

it('answers every account it cannot sign in with one generic failure that reveals nothing (AU-22; H8-20; criterion 1)', function (Closure $arrange, array $claims, string $reason) {
    $arrange($this);

    $answer = googleCallback(fn () => tokenAnswer($claims))->assertRedirect('/masuk');

    $this->get('/masuk')->assertInertia(fn ($page) => $page->component('Auth/SignIn', false)->where('googleFailed', __('auth.google_failed')));
    expect(auth()->check())->toBeFalse()
        ->and(session(TwoFactorChallenge::KEY))->toBeNull()
        ->and(googleFailures())->toBe([$reason])
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_SIGNIN_FAILED')->value('identifier_hash'))->not->toBe('sub-laras');
})->with([
    'an unknown subject' => [fn () => null, ['sub' => 'sub-tak-dikenal'], 'UNKNOWN_SUBJECT'],
    'an unlinked subject' => [fn ($test) => DB::table('user_external_identities')->update(['unlinked_at' => now(), 'unlinked_by' => $test->user->id, 'unlink_reason' => 'SELF']), [], 'UNKNOWN_SUBJECT'],
    'an inactive account' => [fn ($test) => DB::table('users')->where('id', $test->user->id)->update(['is_active' => false, 'deactivated_at' => now()]), [], 'INACTIVE_ACCOUNT'],
    'an account without active TOTP' => [fn ($test) => DB::table('users')->where('id', $test->user->id)->update(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null]), [], 'NO_ACTIVE_TOTP'],
    'an account in recovery' => [fn ($test) => DB::table('audit_events')->insert(['occurred_at' => now(), 'action' => 'IAM.RecoveryCodeUsed', 'authority_class' => 'SYSTEM', 'actor_user_id' => $test->user->id, 'entity_type' => 'user', 'entity_id' => $test->user->id, 'entity_public_id' => $test->user->public_id]), [], 'IN_RECOVERY'],
    'another nonce' => [fn () => null, ['nonce' => 'nonce-lain'], 'ID_TOKEN_INVALID'],
    'another audience' => [fn () => null, ['aud' => 'klien-lain'], 'ID_TOKEN_INVALID'],
    'another issuer' => [fn () => null, ['iss' => 'https://evil.example'], 'ID_TOKEN_INVALID'],
    'an expired token' => [fn () => null, ['exp' => 1_800_000_000], 'ID_TOKEN_INVALID'],
]);

it('fails generically on a stale or mismatched state, a failed exchange and a missing or aged marker (AU-22; M-43)', function () {
    googleCallback(fn () => tokenAnswer(), ['state' => 'state-lain'])->assertRedirect('/masuk');

    googleCallback(fn () => new GuzzleResponse(500))->assertRedirect('/masuk');

    // A callback with no attempt started, and one older than ten minutes.
    $this->get('/masuk/google/callback?state=x&code=y')->assertRedirect('/masuk');
    $this->get('/masuk/google')->assertRedirect();
    $this->travel(GoogleSignIn::ATTEMPT_SECONDS + 1)->seconds();
    fakeGoogle([tokenAnswer()]);
    $this->get('/masuk/google/callback?'.http_build_query(['state' => session('state'), 'code' => 'kode-uji']))->assertRedirect('/masuk');

    expect(googleFailures())->toBe(['STATE_MISMATCH', 'TOKEN_EXCHANGE_FAILED', 'NO_ATTEMPT', 'NO_ATTEMPT'])
        ->and(auth()->check())->toBeFalse();
});

it('leads a callback that arrives while a challenge is pending to that challenge, unchanged and consuming nothing (M-43)', function () {
    googleCallback(fn () => tokenAnswer())->assertRedirect('/masuk/verifikasi');
    $challenge = session(TwoFactorChallenge::KEY);

    // A replayed or doubled callback.
    $this->get('/masuk/google/callback?state=apa-saja&code=kode-uji')->assertRedirect('/masuk/verifikasi');

    expect(session(TwoFactorChallenge::KEY))->toBe($challenge)
        ->and($this->sent)->toHaveCount(1)
        ->and(googleFailures())->toBe([]);
});

it('turns Google sign-in off with no client configured: no button, and both routes refuse (GLC-09; criterion 4)', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/masuk')->assertInertia(fn ($page) => $page->component('Auth/SignIn', false)->where('google', false));
    $this->get('/masuk/google')->assertRedirect('/masuk');
    $this->get('/masuk/google/callback?state=x&code=y')->assertRedirect('/masuk');
    expect(googleFailures())->toBe(['NOT_CONFIGURED', 'NOT_CONFIGURED']);

    config(['services.google.client_id' => 'klien-uji', 'services.google.client_secret' => 'rahasia-klien-uji']);
    $this->get('/masuk')->assertInertia(fn ($page) => $page->where('google', true));
});

it('throttles the redirect and the callback per client address (AU-05)', function () {
    foreach (range(1, 10) as $attempt) {
        $this->get('/masuk/google')->assertRedirect();
    }

    $this->get('/masuk/google')->assertRedirect('/masuk')->assertSessionHasErrors(['email' => trans_choice('messages.msg_20', 1, ['count' => 1])]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get('/masuk/google')->assertRedirect();
});

it('never starts a sign-in from an authenticated session (AU-22)', function () {
    $this->actingAs($this->user)->get('/masuk/google')->assertRedirect('/');
    expect(app(GoogleIdentities::class)->accountFor('sub-laras')?->id)->toBe($this->user->id);
});
