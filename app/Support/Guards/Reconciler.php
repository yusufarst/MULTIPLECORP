<?php

namespace App\Support\Guards;

use Illuminate\Database\ConnectionInterface;

/**
 * The comparison of each guard row with its formula (DATABASE §3.1; ARCHITECTURE §7): a guard row whose covered
 * columns differ from the facts, a guard missing for facts that need one, and a guard whose facts are gone, each
 * a difference. It only reads; nothing is repaired here (GAP-031).
 */
final class Reconciler
{
    /**
     * @return list<array{family: string, key: array<string, mixed>, stored: array<string, string>|null, expected: array<string, string>, carried: array<string, mixed>}>
     */
    public function differences(ConnectionInterface $connection, GuardFamily $family): array
    {
        $table = $family->table();
        $on = implode(' and ', array_map(fn (string $key): string => "g.{$key} = f.{$key}", $family->key()));
        $keys = implode(', ', array_map(fn (string $key): string => "coalesce(g.{$key}, f.{$key}) as {$key}", $family->key()));
        // Each expected value in its guard column's own type, so a rebuilt row and its audit read as the stored ones.
        $types = [];

        foreach ($connection->select('select attname, format_type(atttypid, atttypmod) as type from pg_attribute where attrelid = ?::regclass and attnum > 0 and not attisdropped', [$table]) as $attribute) {
            $attribute = (array) $attribute;

            if (is_string($attribute['attname'] ?? null) && is_string($attribute['type'] ?? null)) {
                $types[$attribute['attname']] = $attribute['type'];
            }
        }

        $typed = fn (string $column): string => "coalesce(f.{$column}, 0)::{$types[$column]}";
        $columns = implode(', ', array_map(fn (string $column): string => "g.{$column} as stored_{$column}, {$typed($column)} as expected_{$column}", $family->columns()));
        $differs = implode(' or ', array_map(fn (string $column): string => "g.{$column} is distinct from {$typed($column)}", $family->columns()));
        $carried = implode('', array_map(fn (string $column): string => ", f.{$column} as carried_{$column}", $family->carried() ?? []));
        $present = 'g.'.$family->key()[0].' is not null';

        $rows = $connection->select("select {$keys}, {$columns}, ({$present}) as present{$carried} from {$table} g full outer join ({$family->formula()}) f on {$on} where {$differs}");

        $differences = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $stored = [];
            $expected = [];
            $carriedValues = [];

            foreach ($family->carried() ?? [] as $column) {
                $carriedValues[$column] = $values["carried_{$column}"];
            }

            foreach ($family->columns() as $column) {
                $stored[$column] = self::text($values["stored_{$column}"]);
                $expected[$column] = self::text($values["expected_{$column}"]);
            }

            $differences[] = [
                'family' => $table,
                'key' => array_intersect_key($values, array_flip($family->key())),
                'stored' => $values['present'] === true ? $stored : null,
                'expected' => $expected,
                'carried' => $carriedValues,
            ];
        }

        return $differences;
    }

    /**
     * The rows that break a verified family's rule, each with its key, the stored counter and the highest number
     * the facts hold at or above it (DATABASE §3.1).
     *
     * @return list<array{family: string, key: array<string, mixed>, stored: array{next_value: string}|null, expected: array{above: string}}>
     */
    public function violations(ConnectionInterface $connection, Verification $verification): array
    {
        $violations = [];

        foreach ($connection->select($verification->violations) as $row) {
            $values = (array) $row;
            $violations[] = [
                'family' => $verification->table,
                'key' => array_intersect_key($values, array_flip($verification->key)),
                'stored' => $values['next_value'] === null ? null : ['next_value' => self::text($values['next_value'])],
                'expected' => ['above' => self::text($values['highest'] ?? null)],
            ];
        }

        return $violations;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
