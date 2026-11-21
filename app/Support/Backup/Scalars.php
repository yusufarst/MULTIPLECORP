<?php

namespace App\Support\Backup;

/** The backup tooling's reads of untyped values — database rows, command options, configuration — as scalars. */
final class Scalars
{
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function int(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    /** @return array<array-key, mixed> */
    public static function row(mixed $row): array
    {
        return is_object($row) ? get_object_vars($row) : (is_array($row) ? $row : []);
    }
}
