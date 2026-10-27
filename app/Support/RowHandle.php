<?php

namespace App\Support;

use LogicException;

/**
 * The handle a page names a row without a public identifier by — PG-059's bank account, PG-062's tax-configuration
 * row, PG-054's client unit, address and PIC —: a keyed hash of its table and identifier, which the page never
 * receives (the pattern of PG-060's session handle), resolved by the controller among the rows the page showed.
 */
final class RowHandle
{
    public static function of(string $table, int $id): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('A row handle is keyed by the application key.');
        }

        return substr(hash_hmac('sha256', "row-handle|{$table}|{$id}", $key), 0, 32);
    }

    public static function valid(string $handle): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $handle) === 1;
    }
}
