<?php

namespace App\Support\Database;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A PostgreSQL `bigint[]` as a list of integers, for a set of record keys a constraint or a computation
 * reads — a company set, for instance — which DATABASE §17 keeps out of `jsonb`.
 *
 * @implements CastsAttributes<list<int>, mixed>
 */
final class IntegerList implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match('/^\{(-?\d+(,-?\d+)*)?\}$/', $value) !== 1) {
            throw new InvalidArgumentException("{$key} is not a PostgreSQL integer array.");
        }

        $items = trim($value, '{}');

        return $items === '' ? [] : array_map(intval(...), explode(',', $items));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("{$key} takes a list of integers.");
        }

        foreach ($value as $item) {
            if (! is_int($item)) {
                throw new InvalidArgumentException("{$key} takes a list of integers.");
            }
        }

        return '{'.implode(',', $value).'}';
    }
}
