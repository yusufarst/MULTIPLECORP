<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The model-level guard of an append-only table — classes AF, AD, SN and AE of DATABASE §1 (C-53;
 * ARCHITECTURE §6): a saved row is never updated or deleted, and a correction is a new row. The runtime
 * role's privileges (INSERT and SELECT only, DATABASE §28) refuse the same at the database, also for a
 * query that bypasses the model.
 *
 * @phpstan-require-extends Model
 */
trait AppendOnly
{
    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): never
    {
        throw $this->appendOnlyRefusal('updated');
    }

    protected function performDeleteOnModel(): never
    {
        throw $this->appendOnlyRefusal('deleted');
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): never
    {
        throw $this->appendOnlyRefusal('updated');
    }

    /**
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     */
    protected function incrementOrDecrementEach(array $columns, array $extra, string $method): never
    {
        throw $this->appendOnlyRefusal('updated');
    }

    private function appendOnlyRefusal(string $change): LogicException
    {
        return new LogicException(sprintf('%s is append-only: a saved row is never %s (DATABASE §13; C-53).', $this->getTable(), $change));
    }
}
