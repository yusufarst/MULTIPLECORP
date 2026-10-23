<?php

namespace App\Support\Database;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A PostgreSQL `text[]` of enumeration codes as a list of strings — a document type's allowed modes, for
 * instance —, each a short uppercase code of DATABASE §1, which needs no quoting in the array literal;
 * `NULL` is no code, the literal reading it as a null element.
 *
 * @implements CastsAttributes<list<string>, mixed>
 */
final class CodeList implements CastsAttributes
{
    private const CODE = '(?!NULL(?![A-Z0-9_]))[A-Z][A-Z0-9_]*';

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match('/^\{('.self::CODE.'(,'.self::CODE.')*)?\}$/', $value) !== 1) {
            throw new InvalidArgumentException("{$key} is not a PostgreSQL array of codes.");
        }

        $items = trim($value, '{}');

        return $items === '' ? [] : explode(',', $items);
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
            throw new InvalidArgumentException("{$key} takes a list of codes.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || preg_match('/^'.self::CODE.'$/', $item) !== 1) {
                throw new InvalidArgumentException("{$key} takes a list of codes.");
            }
        }

        return '{'.implode(',', $value).'}';
    }
}
