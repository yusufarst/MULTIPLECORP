<?php

namespace App\Modules\IAM\Authentication;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * SECURITY AU-22's Google provider, a subclass of Socialite's inside IAM's authentication boundary (ARCHITECTURE
 * §8): the authorization-code flow with the scopes `openid email` only — Socialite's default adds `profile` —,
 * `prompt=select_account` every time, PKCE with S256 and the attempt's `nonce`; Socialite's `state` kept in the
 * session and pulled at the callback. The token exchange is the only call to Google, with a connect timeout of 5 s
 * and a total of 15 s; the subject and the e-mail come from the verified ID token, never from the userinfo
 * endpoint, and no token is kept.
 */
final class GoogleSignInProvider extends GoogleProvider
{
    public const CONNECT_TIMEOUT = 5;

    public const TIMEOUT = 15;

    /** @var list<string> */
    protected $scopes = ['openid', 'email'];

    public function __construct(Request $request, string $clientId, string $clientSecret, string $redirectUrl)
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
        $this->enablePKCE();
    }

    /** Google's consent page for an attempt whose nonce the session keeps. */
    public function redirectWithNonce(string $nonce): RedirectResponse
    {
        return $this->with(['prompt' => 'select_account', 'nonce' => $nonce])->redirect();
    }

    /**
     * The verified subject and e-mail of the callback, or null for a used, stale or mismatched state, a failed
     * token exchange or an ID token that fails its checks — one generic failure, its reason named.
     *
     * @return array{identity: array{sub: string, email: string|null}|null, reason: string}
     */
    public function verifiedIdentity(string $nonce): array
    {
        try {
            if ($this->hasInvalidState()) {
                throw new InvalidStateException;
            }
        } catch (InvalidStateException) {
            return ['identity' => null, 'reason' => 'STATE_MISMATCH'];
        }

        $code = $this->getCode();

        if ($code === '') {
            return ['identity' => null, 'reason' => 'NO_CODE'];
        }

        try {
            $response = $this->getAccessTokenResponse($code);
        } catch (\Throwable) {
            return ['identity' => null, 'reason' => 'TOKEN_EXCHANGE_FAILED'];
        }

        $identity = GoogleIdToken::verified(is_array($response) ? ($response['id_token'] ?? null) : null, $this->clientId, $nonce, now()->getTimestamp());

        return $identity === null ? ['identity' => null, 'reason' => 'ID_TOKEN_INVALID'] : ['identity' => $identity, 'reason' => 'VERIFIED'];
    }

    /**
     * The token exchange, its timeouts on the request itself so they hold whatever client sends it.
     *
     * @param  string  $code
     * @return mixed
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
            RequestOptions::FORM_PARAMS => array_filter($this->getTokenFields($code), fn (mixed $value): bool => is_scalar($value) || $value === null),
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT => self::TIMEOUT,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * The token request's own fields — not the authorization request's `prompt` and `nonce`.
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    protected function getTokenFields($code)
    {
        $parameters = $this->parameters;
        $this->parameters = [];

        try {
            /** @var array<string, mixed> */
            return parent::getTokenFields($code);
        } finally {
            $this->parameters = $parameters;
        }
    }
}
