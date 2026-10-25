<?php

namespace App\Support\Command;

use Closure;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use LogicException;

/**
 * A command's lock set and its planner (CONCURRENCY_IDEMPOTENCY §4–§5): rows named by table and key, taken in
 * ascending canonical order — class position, the class's table order, the key kind of §4.2's sub-orders, then
 * key (LR-01, LR-05) —, each once (LR-04), in the mode FOR NO KEY UPDATE (LR-03), one statement per row, the
 * single-row form LR-05 accepts without a plan to verify. A set is acquired in steps that only move forward: a
 * row re-derived later that sorts before one already held restarts the action with the union (§5 step 4). An
 * integer key is one value however it is given, so one row has one identity and one place in the order.
 */
final class LockSet
{
    /** @var array<string, array{0: LockClass, 1: int, 2: int, 3: string, 4: array<string, int|string>}> */
    private array $rows = [];

    /**
     * @param  array<string, int|string>  $key  the row's key columns in the class's key order — its natural key
     *                                          where the row may be created on first use, otherwise its id
     */
    public function add(string $table, array $key): self
    {
        $class = LockClass::of($table);

        if (! $class->isLockedBySet() || $key === []) {
            throw new InvalidArgumentException("{$table} is not locked by a lock set, or its key is empty (LR-02).");
        }

        $key = self::normalized($key);
        $index = (int) array_search($table, $class->tables(), true);
        $this->rows[self::identity($table, $key)] = [$class, $index, LockClass::kindOf($table, $key), $table, $key];

        return $this;
    }

    /** The rows of both sets, as the restart of §5 step 5 keeps them. */
    public function union(self $other): self
    {
        $union = new self;
        $union->rows = [...$this->rows, ...$other->rows];

        return $union;
    }

    /**
     * @param  array<string, int|string>  $key
     */
    public function contains(string $table, array $key): bool
    {
        return isset($this->rows[self::identity($table, self::normalized($key))]);
    }

    /** Whether every row of the other set is in this one — a required set that grew restarts the action (§5). */
    public function covers(self $other): bool
    {
        return array_diff_key($other->rows, $this->rows) === [];
    }

    /**
     * The rows in canonical order.
     *
     * @return list<array{class: LockClass, index: int, kind: int, table: string, key: array<string, int|string>}>
     */
    public function ordered(): array
    {
        $rows = array_values($this->rows);

        usort($rows, static function (array $a, array $b): int {
            return [$a[0]->value, $a[1], $a[2]] <=> [$b[0]->value, $b[1], $b[2]] ?: self::compareKeys($a[4], $b[4]);
        });

        return array_map(static fn (array $row): array => ['class' => $row[0], 'index' => $row[1], 'kind' => $row[2], 'table' => $row[3], 'key' => $row[4]], $rows);
    }

    /**
     * Locks the rows of the set that sort within the given bound, in order, skipping those already held; a row
     * not held that sorts before the highest held restarts the action with this set (§5 step 4). Returns the
     * keys that matched no row, for an action to create on first use or refuse.
     *
     * @param  Closure(string, array<string, int|string>): void|null  $observer  told of each statement (HO-13)
     * @return list<array{table: string, key: array<string, int|string>}>
     */
    public function acquire(ConnectionInterface $connection, LockPosition $held, ?LockClass $upTo = null, ?Closure $observer = null): array
    {
        $missing = [];
        $rows = array_values(array_filter(
            $this->ordered(),
            fn (array $row): bool => ($upTo === null || $row['class']->value <= $upTo->value) && ! $held->holds(self::identity($row['table'], $row['key'])),
        ));

        foreach ($rows as $row) {
            if ($held->sortsBefore($row['class'], $row['index'], $row['kind'], $row['key'])) {
                throw new Restart($this);
            }
        }

        foreach ($rows as $row) {
            $held->advance($row['class'], $row['index'], $row['kind'], $row['key'], self::identity($row['table'], $row['key']));
            $query = $connection->table($row['table']);

            foreach ($row['key'] as $column => $value) {
                $query->where($column, $value);
            }

            $found = $query->lock('for no key update')->exists();
            $observer?->__invoke($row['table'], $row['key']);

            if (! $found) {
                $missing[] = ['table' => $row['table'], 'key' => $row['key']];
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, int|string>  $key
     */
    public static function identity(string $table, array $key): string
    {
        return $table.'|'.json_encode($key, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, int|string>  $a
     * @param  array<string, int|string>  $b
     */
    public static function compareKeys(array $a, array $b): int
    {
        if (array_keys($a) !== array_keys($b)) {
            throw new LogicException('Two rows of one table and key kind are keyed by the same columns in the same order (LR-05).');
        }

        foreach ($a as $column => $value) {
            $order = is_int($value) && is_int($b[$column]) ? $value <=> $b[$column] : strcmp((string) $value, (string) $b[$column]);

            if ($order !== 0) {
                return $order;
            }
        }

        return 0;
    }

    /**
     * An integer given as its decimal string becomes the integer, so `5` and `'5'` are one row.
     *
     * @param  array<string, int|string>  $key
     * @return array<string, int|string>
     */
    private static function normalized(array $key): array
    {
        return array_map(
            static fn (int|string $value): int|string => is_string($value) && preg_match('/^(0|-?[1-9][0-9]{0,17})$/', $value) === 1 ? (int) $value : $value,
            $key,
        );
    }
}
