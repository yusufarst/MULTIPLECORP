<?php

namespace App\Modules\OPS\Queries;

use InvalidArgumentException;
use JsonException;
use LogicException;

/**
 * The opaque position of a log row in keyset order (`occurred_at`, `id`) — a page's "Muat Lagi" cursor and an event's
 * key on PG-063 and PG-064 (PF-13–PF-15; IP-18): the stored instant at its full precision and the id, signed with the
 * application key, so a cursor or key the page did not hand out is refused (MSG-26; SM-01) rather than read.
 */
final class LogCursor
{
    public static function of(string $occurredAt, int $id): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([$occurredAt, $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $payload.'.'.self::signature($payload);
    }

    /**
     * The instant and id a cursor names; refused when it is not one this application signed.
     *
     * @return array{string, int}
     */
    public static function read(string $cursor): array
    {
        $parts = explode('.', $cursor);

        if (count($parts) !== 2 || strlen($cursor) > 200 || ! hash_equals(self::signature($parts[0]), $parts[1])) {
            throw new InvalidArgumentException('The log cursor is not valid.');
        }

        try {
            $decoded = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true, 2, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (! is_array($decoded) || ! isset($decoded[0], $decoded[1]) || ! is_string($decoded[0]) || ! is_int($decoded[1])) {
            throw new InvalidArgumentException('The log cursor is not valid.');
        }

        return [$decoded[0], $decoded[1]];
    }

    private static function signature(string $payload): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('A log cursor is signed with the application key.');
        }

        return substr(hash_hmac('sha256', "log-cursor|{$payload}", $key), 0, 22);
    }
}
