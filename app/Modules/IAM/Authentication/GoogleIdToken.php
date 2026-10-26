<?php

namespace App\Modules\IAM\Authentication;

/**
 * SECURITY AU-22's check of the ID token Google's token endpoint returns over HTTPS with the client secret, which
 * Google documents as trustworthy without a signature check: its `nonce` the attempt's, its audience the client
 * ID, its issuer `https://accounts.google.com` or `accounts.google.com`, not expired; the subject and the e-mail
 * are taken from it, never from the userinfo endpoint — the e-mail only when Google verified it, and with several
 * audiences only when the token was issued to this client (`azp`). Anything else answers null — one generic failure.
 */
final class GoogleIdToken
{
    public const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @return array{sub: string, email: string|null}|null
     */
    public static function verified(mixed $idToken, string $clientId, string $nonce, int $now): ?array
    {
        if (! is_string($idToken) || substr_count($idToken, '.') !== 2 || $clientId === '' || $nonce === '') {
            return null;
        }

        $payload = explode('.', $idToken)[1];
        $json = base64_decode(strtr($payload, '-_', '+/').str_repeat('=', (4 - strlen($payload) % 4) % 4), true);
        $claims = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($claims)) {
            return null;
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $subject = $claims['sub'] ?? null;
        $expires = $claims['exp'] ?? null;
        $email = $claims['email'] ?? null;

        $valid = is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce'])
            && in_array($clientId, $audiences, true)
            && (! is_array($audience) || ($claims['azp'] ?? null) === $clientId)
            && in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && is_int($expires) && $expires > $now
            && is_string($subject) && preg_match('/^[ -~]{1,255}$/', $subject) === 1;

        return $valid ? ['sub' => $subject, 'email' => is_string($email) && ($claims['email_verified'] ?? null) === true ? $email : null] : null;
    }
}
