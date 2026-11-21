<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\DB;

/**
 * The monthly inputs of PFM-18 and GT-07 (TASK-075; PERFORMANCE §12 item 6): the heaviest statements by total time from
 * `pg_stat_statements` — normalized by PostgreSQL, their literals replaced by placeholders, so no value is read —, and
 * the largest tables with their indexes. An entry past half of its threshold is marked for a measured review. Bounded
 * read-only statements, off the request path.
 */
final class StatementStatistics
{
    /**
     * @return array{available: bool, statements: list<array{query: string, calls: int, mean_us: int, review: bool}>, relations: list<array{name: string, bytes: int, review: bool}>}
     */
    public function report(int $top): array
    {
        $available = (bool) DB::scalar("select exists (select from pg_extension where extname = 'pg_stat_statements')");
        $halfMeanUs = intdiv(config()->integer('monitoring.report.mean_ms') * 1000, 2);
        $halfBytes = intdiv(config()->integer('monitoring.report.table_bytes'), 2);
        $statements = [];

        if ($available) {
            foreach (DB::select('select left(regexp_replace(query, \'\s+\', \' \', \'g\'), 160) as query, calls, (mean_exec_time * 1000)::bigint as mean_us
                from pg_stat_statements where dbid = (select oid from pg_database where datname = current_database())
                order by total_exec_time desc limit ?', [$top]) as $row) {
                $values = (array) $row;
                $mean = is_numeric($values['mean_us'] ?? null) ? (int) $values['mean_us'] : 0;
                $statements[] = ['query' => is_string($values['query'] ?? null) ? $values['query'] : '', 'calls' => is_numeric($values['calls'] ?? null) ? (int) $values['calls'] : 0, 'mean_us' => $mean, 'review' => $mean >= $halfMeanUs];
            }
        }

        $relations = [];

        foreach (DB::select("select c.relname as name, pg_total_relation_size(c.oid) as bytes from pg_class c join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind = 'r' order by pg_total_relation_size(c.oid) desc limit ?", [$top]) as $row) {
            $values = (array) $row;
            $bytes = is_numeric($values['bytes'] ?? null) ? (int) $values['bytes'] : 0;
            $relations[] = ['name' => is_string($values['name'] ?? null) ? $values['name'] : '', 'bytes' => $bytes, 'review' => $bytes >= $halfBytes];
        }

        return ['available' => $available, 'statements' => $statements, 'relations' => $relations];
    }
}
