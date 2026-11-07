<?php

namespace App\Modules\Reporting\Reports;

use App\Support\Search\Row;
use Generator;
use Illuminate\Database\Query\Builder;

/**
 * A statement read in keyed chunks (PF-28; QB-15): ordered by a unique key, each chunk starting after the last row of
 * the one before — never an offset —, so an export's memory is bounded by one chunk; the page reads the same chunks.
 */
final class Keyed
{
    public const CHUNK = 1000;

    /**
     * @param  array<literal-string, string>  $keys  the key's SQL expressions, ascending, each with the alias it is selected as
     * @return Generator<int, object>
     */
    public static function rows(Builder $query, array $keys, int $size = self::CHUNK): Generator
    {
        $after = null;
        $expressions = implode(', ', array_keys($keys));
        $marks = implode(', ', array_fill(0, count($keys), '?'));

        do {
            $chunk = (clone $query)
                ->when($after !== null, fn (Builder $where) => $where->whereRaw("({$expressions}) > ({$marks})", $after ?? []))
                ->orderByRaw($expressions)
                ->limit($size)
                ->get()
                ->all();

            foreach ($chunk as $row) {
                yield $row;
            }

            $last = $chunk === [] ? null : $chunk[count($chunk) - 1];
            $after = $last === null ? null : array_map(fn (string $alias): string => Row::text($last, $alias), array_values($keys));
        } while (count($chunk) === $size);
    }
}
