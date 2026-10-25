<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * The one shared writer of guard rows (ARCHITECTURE §7; CONCURRENCY_IDEMPOTENCY GU-01–GU-04): one statement per
 * guard table and command, carrying each named row's net signed delta of all its columns — deltas several
 * posting services contribute to one row combined first —, the rows already locked in canonical order. It must
 * affect exactly the rows it names: fewer is a missing guard (SQ-18), never a silent success (GU-02); a CHECK
 * it breaks is its rule's refusal (SQ-04).
 */
final class GuardUpdate
{
    /**
     * @param  list<array{key: array<string, int|string>, deltas: array<string, int|string>}>  $rows  each row once
     */
    public static function apply(ConnectionInterface $connection, string $table, array $rows): void
    {
        if ($rows === [] || preg_match('/^[a-z_]+$/', $table) !== 1) {
            throw new InvalidArgumentException('A guard update names a guard table and at least one row (GU-01).');
        }

        $keys = array_keys($rows[0]['key']);
        $columns = array_keys($rows[0]['deltas']);
        $values = [];
        $bindings = [];
        $named = [];

        foreach ([...$keys, ...$columns] as $identifier) {
            if (preg_match('/^[a-z_]+$/', $identifier) !== 1) {
                throw new InvalidArgumentException('A guard update names its key and delta columns (GU-01).');
            }
        }

        foreach ($rows as $row) {
            if (array_keys($row['key']) !== $keys || array_keys($row['deltas']) !== $columns) {
                throw new InvalidArgumentException('Every row of one guard statement names the same key and delta columns (GU-01).');
            }

            // One row once, its contributions combined first (GU-01): a repeated key is a defect, not a missing guard.
            $identity = json_encode(array_map('strval', $row['key']), JSON_THROW_ON_ERROR);

            if (isset($named[$identity])) {
                throw new InvalidArgumentException('A guard update names each row once, its deltas combined (GU-01).');
            }

            $named[$identity] = true;

            $cells = [];

            foreach ($row['key'] as $value) {
                $cells[] = is_int($value) ? 'cast(? as bigint)' : 'cast(? as text)';
                $bindings[] = $value;
            }

            foreach ($row['deltas'] as $delta) {
                $cells[] = 'cast(? as numeric)';
                $bindings[] = (string) $delta;
            }

            $values[] = '('.implode(', ', $cells).')';
        }

        $names = [...array_map(fn (string $key): string => "k_{$key}", $keys), ...array_map(fn (string $column): string => "d_{$column}", $columns)];
        $set = implode(', ', array_map(fn (string $column): string => "{$column} = {$table}.{$column} + v.d_{$column}", $columns));
        $where = implode(' and ', array_map(fn (string $key): string => "{$table}.{$key} = v.k_{$key}", $keys));

        $affected = $connection->update(
            "update {$table} set {$set} from (values ".implode(', ', $values).') as v('.implode(', ', $names).") where {$where}",
            $bindings,
        );

        if ($affected !== count($rows)) {
            throw new GuardMissing($table, count($rows), $affected);
        }
    }
}
