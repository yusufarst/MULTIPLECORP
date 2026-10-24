<?php

namespace App\Support\SecurityLog;

use RuntimeException;

/**
 * The keyed hashes of SECURITY LG-03 (D-SEC-13): a submitted login identifier, an address and a provider
 * subject reach the security log only as an HMAC-SHA-256 under the dedicated secret of
 * `config('security.log_hmac_key')`, never as a plain hash, which a small input space such as IPv4 makes
 * reversible. The same input always gives the same hash, so the detection signals of LG-07 can group by it.
 */
final class SecurityLogHash
{
    private const MINIMUM_KEY_LENGTH = 32;

    /** A submitted login identifier, compared without case or surrounding space. */
    public static function identifier(string $identifier): string
    {
        return self::hash('identifier', mb_strtolower(trim($identifier)));
    }

    /** An address, in its canonical text form, so that two spellings of one IPv6 address hash alike. */
    public static function address(string $address): string
    {
        $packed = @inet_pton(trim($address));

        return self::hash('address', $packed === false ? trim($address) : (string) inet_ntop($packed));
    }

    /** A provider subject: the Google account's `sub`, the one provider of AU-22. */
    public static function subject(string $subject): string
    {
        return self::hash('subject', trim($subject));
    }

    private static function hash(string $purpose, string $value): string
    {
        return hash_hmac('sha256', "{$purpose}\0{$value}", self::key());
    }

    private static function key(): string
    {
        $key = config('security.log_hmac_key');

        if (! is_string($key) || strlen($key) < self::MINIMUM_KEY_LENGTH) {
            throw new RuntimeException('The security log needs its dedicated SECURITY_LOG_HMAC_KEY of at least 32 characters (LG-03).');
        }

        // The repository's placeholders are public, so a hash under them would be reversible.
        if (app()->isProduction() && preg_match('/change-me|testing-only/i', $key) === 1) {
            throw new RuntimeException('The security log key is still a repository placeholder (LG-03; SX-01).');
        }

        if ($key === config('app.key')) {
            throw new RuntimeException('The security log key is a dedicated secret, never APP_KEY (LG-03).');
        }

        return $key;
    }
}
