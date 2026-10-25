<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * PERFORMANCE PF-21's name search over one column: the names equal to the term or beginning with it first, from
 * a bounded query on the pattern-class index (IX-17); then, for a term with a trigram, the substring and
 * word-similarity hits of the GIN trigram index (DATABASE §21), ranked within a candidate set of 200 and cut to
 * the rows asked for. The caller's query already carries the scope (PF-22), so no bound, rank or cut sees a row
 * outside it.
 */
final class NameSearch
{
    public const CANDIDATES = 200;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query  the scoped base query, selecting the row's fields and its
     *                                                       internal `search_key`, which the merge uses and no answer
     *                                                       carries
     * @param  literal-string  $column  the qualified name column, e.g. `products.name`
     * @return array{rows: list<object>, cut: bool}
     */
    public static function run(EloquentBuilder|QueryBuilder $query, string $column, Term $term, int $rows): array
    {
        $prefix = (clone $query)
            ->whereRaw('lower('.$column.") like ? escape '\\'", [$term->likeLiteral().'%'])
            ->orderByRaw('lower('.$column.') = ? desc', [$term->lower()])
            ->orderByRaw('lower('.$column.')')
            ->limit($rows)
            ->get()
            ->all();
        $prefix = array_values($prefix);

        if (! $term->hasTrigram()) {
            return ['rows' => $prefix, 'cut' => false];
        }

        $candidates = (clone $query)
            ->where(fn (EloquentBuilder|QueryBuilder $where) => $where
                ->whereRaw($column." ilike ? escape '\\'", ['%'.$term->likeLiteral().'%'])
                ->orWhereRaw('? <% '.$column, [$term->lower()]))
            ->orderByRaw('word_similarity(?, '.$column.') desc', [$term->lower()])
            ->orderByRaw('lower('.$column.')')
            ->limit(self::CANDIDATES + 1)
            ->get()
            ->all();
        $candidates = array_values($candidates);

        $seen = [];

        foreach ($prefix as $row) {
            $seen[Row::text($row, 'search_key')] = true;
        }

        $similar = array_values(array_filter(
            array_slice($candidates, 0, self::CANDIDATES),
            fn (object $row): bool => ! isset($seen[Row::text($row, 'search_key')]),
        ));

        return ['rows' => array_slice([...$prefix, ...$similar], 0, $rows), 'cut' => count($candidates) > self::CANDIDATES];
    }
}
