<?php

namespace App\Modules\DOC\Data;

use LogicException;

/**
 * An evidence link's handle on PG-038 and in ROUTE-231's unlink: a keyed hash of the link's internal identifier, which
 * the page never receives (the pattern of PG-060's session handle), resolved by the command among the evidence's
 * active links under the evidence's lock — whatever record the link names, with or without a public identifier.
 */
final class LinkHandle
{
    public static function of(int $linkId): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('A link handle is keyed by the application key.');
        }

        return substr(hash_hmac('sha256', 'evidence-link|'.$linkId, $key), 0, 32);
    }

    public static function valid(string $handle): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $handle) === 1;
    }
}
