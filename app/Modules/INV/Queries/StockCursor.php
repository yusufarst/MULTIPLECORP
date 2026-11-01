<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Authority;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * PF-14's cursor for the stock lists (PG-018; PG-019; PG-020): encrypted and authenticated, binding the last key values with a digest of what they were made for — the list, its filters and sort, the viewer's company scope. One that fails a check is the page's MSG-26, never a silent first page (INT-008).
 */
final class StockCursor
{
    /**
     * @param  list<int|string>  $key
     * @param  array<string, mixed>  $binding
     */
    public static function of(Authority $viewer, array $key, array $binding): string
    {
        return Crypt::encryptString(json_encode(['k' => $key, 'b' => self::digest($viewer, $binding)], JSON_THROW_ON_ERROR));
    }

    /**
     * The key values, or null when it is not one this list handed out for these filters and this scope.
     *
     * @param  array<string, mixed>  $binding
     * @return list<int|string>|null
     */
    public static function read(Authority $viewer, string $cursor, array $binding, int $parts): ?array
    {
        try {
            $value = json_decode(Crypt::decryptString($cursor), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($value) || ! is_array($value['k'] ?? null) || ! is_string($value['b'] ?? null) || ! hash_equals(self::digest($viewer, $binding), $value['b']) || count($value['k']) !== $parts) {
            return null;
        }

        $key = [];

        foreach ($value['k'] as $part) {
            if (! is_int($part) && ! is_string($part)) {
                return null;
            }

            $key[] = $part;
        }

        return $key;
    }

    /** @param  array<string, mixed>  $binding */
    private static function digest(Authority $viewer, array $binding): string
    {
        return hash('sha256', json_encode([$binding, $viewer->scope->all, $viewer->scope->companyIds], JSON_THROW_ON_ERROR));
    }
}
