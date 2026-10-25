<?php

namespace App\Support\Search;

/**
 * A selected row's column as a search answer carries it: a string, or null where the column is empty.
 */
final class Row
{
    public static function text(object $row, string $column): string
    {
        return self::nullable($row, $column) ?? '';
    }

    public static function nullable(object $row, string $column): ?string
    {
        $value = data_get($row, $column);

        return is_scalar($value) ? (string) $value : null;
    }
}
