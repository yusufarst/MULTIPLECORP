<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * The redaction of SECURITY LG-03 and the line-break neutralization of LG-04, shared by the business audit,
 * the security events and the technical logs: a value under a sensitive key — a password, a token, a
 * secret, a cookie, an authorization header, a TOTP or recovery code, a provider subject — is replaced by a
 * mark, whatever its value, and no logged string can start a line of its own. The request keys an OAuth
 * callback or a code form submits — `state`, `code`, `key` — are sensitive in logs only, since a business
 * row's `state` and `code` are what its audit must show.
 */
final class Redaction
{
    public const MARK = '[REDACTED]';

    /** Key fragments that are sensitive wherever they appear in a key. */
    private const FRAGMENTS = '/password|passwd|passphrase|secret|token|cookie|authorization|api[_-]?key|app_key|csrf|xsrf|recovery_code|private_key|pkce|code_verifier|signature|totp|two_factor|provider_subject|oauth|(^|[_-])otp($|[_-])/i';

    /** Keys that are sensitive only as a whole: as fragments they would also mark ordinary business keys. */
    private const WHOLE_KEYS = ['nonce', 'otp', 'session', 'session_id', 'remember'];

    /** Request keys that are sensitive in logs and security details, but are ordinary business columns. */
    private const REQUEST_KEYS = ['state', 'code', 'key'];

    private const TRACE_FRAMES = 20;

    /**
     * @param  bool  $request  also the request keys, for a log line or a security event's details
     */
    public static function isSensitiveKey(string|int $key, bool $request = false): bool
    {
        if (is_int($key)) {
            return false;
        }

        $whole = strtolower($key);

        return preg_match(self::FRAGMENTS, $key) === 1
            || in_array($whole, self::WHOLE_KEYS, true)
            || ($request && in_array($whole, self::REQUEST_KEYS, true));
    }

    /**
     * The values with every sensitive key's value replaced by the mark, at any depth.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function redact(array $values, bool $request = false): array
    {
        foreach ($values as $key => $value) {
            if (self::isSensitiveKey($key, $request)) {
                $values[$key] = self::MARK;
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value, $request);
            }
        }

        return $values;
    }

    /** The string with every line break and other control character written as a visible escape (LG-04). */
    public static function neutralize(string $value): string
    {
        return (string) preg_replace_callback(
            '/[\x00-\x1F\x7F]|\x{0085}|\x{2028}|\x{2029}/u',
            fn (array $match): string => match ($match[0]) {
                "\n" => '\n',
                "\r" => '\r',
                "\t" => '\t',
                default => sprintf('\u{%04X}', mb_ord($match[0])),
            },
            $value,
        );
    }

    /**
     * The values of a log record: redacted with the request keys, every string at any depth neutralized,
     * and an exception reduced to what names it — its class, code, place and argument-free trace —, a
     * database failure without its bound values or the values its error detail quotes.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function forLog(array $values): array
    {
        $values = self::redact($values, request: true);

        array_walk_recursive($values, function (mixed &$value): void {
            if ($value instanceof Throwable) {
                $value = self::exception($value);
            } elseif (is_string($value)) {
                $value = self::neutralize($value);
            }
        });

        return $values;
    }

    /** The message a log line may carry for an exception (LG-03, LG-04). */
    public static function exceptionMessage(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return self::neutralize("SQLSTATE[{$e->getCode()}] on: {$e->getSql()}");
        }

        if ($e instanceof PDOException) {
            return "SQLSTATE[{$e->getCode()}]";
        }

        return self::neutralize($e->getMessage());
    }

    /**
     * @return array<string, mixed>
     */
    private static function exception(Throwable $e): array
    {
        $trace = array_map(
            fn (array $frame): string => self::neutralize(($frame['file'] ?? '[internal]').':'.($frame['line'] ?? 0).' '.($frame['class'] ?? '').($frame['type'] ?? '').$frame['function']),
            array_slice($e->getTrace(), 0, self::TRACE_FRAMES),
        );

        return [
            'class' => $e::class,
            'code' => $e->getCode(),
            'message' => self::exceptionMessage($e),
            'at' => $e->getFile().':'.$e->getLine(),
            'trace' => $trace,
            'previous' => $e->getPrevious() === null ? null : self::exception($e->getPrevious()),
        ];
    }
}
