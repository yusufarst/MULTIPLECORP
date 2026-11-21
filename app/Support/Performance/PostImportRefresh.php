<?php

namespace App\Support\Performance;

use Illuminate\Database\Connection;
use JsonException;

/**
 * The post-import refresh (TASK-077; PRQ-08; HO-36; IMP-10): after the opening import commits and before go-live, the
 * planner statistics are refreshed and each registered hot query of PERFORMANCE §12 is explained on the imported data —
 * `EXPLAIN` without `ANALYZE`, so no query runs and no data-changing statement is sent. Each plan is reported by its
 * shape alone — the nodes, the indexes and the relations scanned in full — never a value of the data (PFM-03), for the
 * operator to judge against the plans recorded at 1x, 10x and 100x. A sequential scan of a relation over 10,000 rows is
 * flagged by PERFORMANCE §12's step-4 rule.
 */
final class PostImportRefresh
{
    public const LARGE = 10_000;

    /**
     * @return list<array{use: string, index: string, uses_index: bool, top: string, cost: string, large_scans: list<string>}>
     */
    public function run(Connection $login): array
    {
        $login->statement('analyze');
        $company = $login->scalar('select min(id) from companies');
        $company = is_int($company) || is_numeric($company) ? (string) $company : 'null';
        $report = [];

        foreach (HotQueries::REGISTER as $use => [$index, $query]) {
            $explained = $login->scalar('explain (format json) '.str_replace('{company}', $company, $query));

            try {
                $decoded = json_decode(is_string($explained) ? $explained : '[]', true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $decoded = [];
            }

            $first = is_array($decoded) ? ($decoded[0] ?? null) : null;
            $plan = is_array($first) && is_array($first['Plan'] ?? null) ? $first['Plan'] : [];
            $nodes = self::nodes($plan);
            $large = [];

            foreach ($nodes as $node) {
                if ($node['type'] === 'Seq Scan' && $node['relation'] !== '' && self::rows($login, $node['relation']) > self::LARGE) {
                    $large[] = $node['relation'];
                }
            }

            $report[] = [
                'use' => $use,
                'index' => $index,
                'uses_index' => in_array($index, array_column($nodes, 'index'), true),
                'top' => is_string($plan['Node Type'] ?? null) ? $plan['Node Type'] : '?',
                'cost' => is_scalar($plan['Total Cost'] ?? null) ? (string) $plan['Total Cost'] : '?',
                'large_scans' => array_values(array_unique($large)),
            ];
        }

        return $report;
    }

    /**
     * Each node of a plan by its type, relation and index — the plan's shape, without its conditions or values.
     *
     * @param  array<array-key, mixed>  $node
     * @return list<array{type: string, relation: string, index: string}>
     */
    public static function nodes(array $node): array
    {
        $found = [[
            'type' => is_string($node['Node Type'] ?? null) ? $node['Node Type'] : '',
            'relation' => is_string($node['Relation Name'] ?? null) ? $node['Relation Name'] : '',
            'index' => is_string($node['Index Name'] ?? null) ? $node['Index Name'] : '',
        ]];

        foreach (is_array($node['Plans'] ?? null) ? $node['Plans'] : [] as $child) {
            if (is_array($child)) {
                $found = [...$found, ...self::nodes($child)];
            }
        }

        return $found;
    }

    private static function rows(Connection $login, string $relation): int
    {
        $rows = $login->scalar('select reltuples::bigint from pg_class where relname = ?', [$relation]);

        return is_int($rows) ? $rows : (is_numeric($rows) ? (int) $rows : 0);
    }
}
