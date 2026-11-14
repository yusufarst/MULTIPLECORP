<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * PERFORMANCE QB-01–QB-17's measure (TASK-068; PFM-01): the data statements of a request beyond the envelope every
 * authenticated request pays first — the session's read and write, the account row and the resolvers' three grant reads
 * (PERFORMANCE §4; EN-02) —, so a page's count is compared with its budget's own number. A test that signs in with
 * `actingAs` skips the account row; whichever envelope statements a request runs are left out, nothing else.
 */
final class QueryBudget
{
    /** The envelope's statements, by the table each reads or writes. */
    private const ENVELOPE = [
        '/^(select|update|insert into|delete from) .*"sessions"/i',
        '/^select \* from "users" where "id" = \? limit 1$/i',
        '/^select "is_active" from "users" where "id" = \? limit 1$/i',
        '/^select .* from "user_roles"/i',
        '/^select .* from "user_capability_grants"/i',
        '/^select .* from "user_company_grants"/i',
    ];

    /**
     * The statements the callable runs, the envelope's left out.
     *
     * @return list<string>
     */
    public static function statements(callable $run): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $statements = [];

        foreach ($queries as $query) {
            $sql = trim((string) preg_replace('/\s+/', ' ', (string) $query['query']));

            foreach (self::ENVELOPE as $pattern) {
                if (preg_match($pattern, $sql) === 1) {
                    continue 2;
                }
            }

            $statements[] = $sql;
        }

        return $statements;
    }

    /** The number of data statements beyond the envelope. */
    public static function beyondEnvelope(callable $run): int
    {
        return count(self::statements($run));
    }
}
