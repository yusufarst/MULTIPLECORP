<?php

namespace App\Modules\IAM\Data;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * An actor's company scope (PERMISSIONS_MATRIX CS-01–CS-03; RG-03): every company for a holder of
 * `scope.all_companies`, otherwise the companies of the actor's active company grants — none without a
 * grant (default deny). A query class applies it inside its query, before any filter, ranking,
 * aggregation or pagination (AZ-04; CS-07).
 */
final class CompanyScope
{
    /**
     * @param  list<int>  $companyIds  sorted, without duplicates; ignored when $all
     */
    private function __construct(public readonly bool $all, public readonly array $companyIds) {}

    public static function everyCompany(): self
    {
        return new self(true, []);
    }

    /**
     * @param  iterable<int>  $companyIds
     */
    public static function of(iterable $companyIds): self
    {
        $ids = [];

        foreach ($companyIds as $id) {
            $ids[(int) $id] = (int) $id;
        }

        ksort($ids);

        return new self(false, array_values($ids));
    }

    public static function none(): self
    {
        return new self(false, []);
    }

    public function includes(int $companyId): bool
    {
        return $this->all || in_array($companyId, $this->companyIds, true);
    }

    public function isEmpty(): bool
    {
        return ! $this->all && $this->companyIds === [];
    }

    /**
     * The working filter of INFORMATION_ARCHITECTURE §7 (WF-ACC-01; DP-02; TASK-016): the companies asked for,
     * intersected with this scope, so a filter narrows and never widens — a company outside the scope is
     * ignored, and a filter left with none of the scope's companies shows the whole scope, as no filter does.
     *
     * @param  iterable<int>  $companyIds
     */
    public function narrowedTo(iterable $companyIds): self
    {
        $kept = [];

        foreach ($companyIds as $id) {
            if ($this->includes((int) $id)) {
                $kept[] = (int) $id;
            }
        }

        return $kept === [] ? $this : self::of($kept);
    }

    /**
     * The query restricted to the scope on its company column; an empty scope matches no row. The query's
     * own conditions are grouped first, so an `orWhere` among them can never reach past the scope.
     *
     * @template TBuilder of EloquentBuilder<covariant Model>|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(EloquentBuilder|QueryBuilder $query, string $column = 'company_id'): EloquentBuilder|QueryBuilder
    {
        if ($this->all) {
            return $query;
        }

        self::grouped($query);
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        $this->companyIds === [] ? $base->whereRaw('false') : $base->whereIn($column, $this->companyIds);

        return $query;
    }

    /**
     * The query with its own conditions wrapped in one group, so a condition added after them — the scope, a
     * public identifier — binds the whole of them, an `orWhere` among them included.
     *
     * @template TBuilder of EloquentBuilder<covariant Model>|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function grouped(EloquentBuilder|QueryBuilder $query): EloquentBuilder|QueryBuilder
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        if ($base->wheres !== []) {
            $own = $base->forNestedWhere();
            $own->wheres = $base->wheres;
            $own->bindings['where'] = $base->bindings['where'];
            $base->wheres = [];
            $base->bindings['where'] = [];
            $base->addNestedWhereQuery($own);
        }

        return $query;
    }
}
