<?php

namespace App\Modules\IAM\Data;

use LogicException;

/**
 * A session's handle on PG-060 and in ROUTE-130's `{sesi}` (AU-10; PJ-17): the Owner sees each session as a device
 * summary with its start and last activity and ends it by this handle — a keyed hash of the session identifier, which
 * itself is never shown, sent or logged (LG-03). A reading within the approved text: ROUTE-130 names the session while
 * PJ-17 forbids its identifier, so the address carries the handle and the command resolves it under the account's row.
 */
final class SessionHandle
{
    public static function of(string $sessionId): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('A session handle is keyed by the application key.');
        }

        return substr(hash_hmac('sha256', 'session-handle|'.$sessionId, $key), 0, 32);
    }

    public static function valid(string $handle): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $handle) === 1;
    }
}
