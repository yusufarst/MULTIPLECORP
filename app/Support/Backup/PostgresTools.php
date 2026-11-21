<?php

namespace App\Support\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * `pg_dump` and `pg_restore` (HO-30) and the tooling's own reads of the database they work on, under the login the
 * operator gives to the run alone — the owner role's in practice, which a dump of every table and a restore of owned
 * objects need (DATABASE §28: used only in deployments and audited maintenance windows). It is never the runtime's
 * connection or the migrations' one: it comes from the run's environment (`BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD`),
 * as `GuardMaintenance` reads the administration credential, and reaches the tools through their environment, never
 * their arguments, a manifest or a log. The tools' major version must be the server's.
 */
final class PostgresTools
{
    public const CONNECTION = 'backup_operator';

    /** The operator's login for this run, on the live database or on a restore's new one. */
    public static function login(?string $database = null): Connection
    {
        $base = (array) config('database.connections.'.Scalars::text(config('database.default')));
        $username = self::given('BACKUP_DB_USERNAME', 'backup.database.username');
        $password = self::given('BACKUP_DB_PASSWORD', 'backup.database.password');

        if ($username === '') {
            throw new RuntimeException('give this run the database login for the dump and the restore (BACKUP_DB_USERNAME and BACKUP_DB_PASSWORD); it is never kept in the runtime\'s environment');
        }

        config(['database.connections.'.self::CONNECTION => [...$base, 'username' => $username, 'password' => $password, 'database' => $database ?? ($base['database'] ?? null)]]);
        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    /** Refuses tools of another major version than the server's (HO-30). */
    public static function assertVersion(Connection $connection, string $tool): void
    {
        $server = intdiv(Scalars::int($connection->scalar('show server_version_num')), 10000);
        $result = Process::timeout(60)->run([$tool, '--version']);

        if (! $result->successful() || preg_match('/\s(\d+)(\.\d+)*/', $result->output(), $match) !== 1) {
            throw new RuntimeException("{$tool} cannot be run.");
        }

        if ((int) $match[1] !== $server) {
            throw new RuntimeException("{$tool} {$match[1]} is not the server's major version {$server} (HO-30).");
        }
    }

    /**
     * Runs a tool against a connection's database, its password in the environment.
     *
     * @param  list<string>  $arguments
     */
    public static function run(Connection $connection, string $tool, array $arguments): string
    {
        $config = $connection->getConfig();
        $config = is_array($config) ? $config : [];
        $result = Process::timeout(config()->integer('backup.tool_timeout_seconds'))
            ->env(['PGPASSWORD' => self::text($config, 'password'), 'PGSSLMODE' => self::text($config, 'sslmode') ?: 'prefer', 'PGCONNECT_TIMEOUT' => '30'])
            ->run([$tool, '--no-password', '--host='.self::text($config, 'host'), '--port='.self::text($config, 'port'), '--username='.self::text($config, 'username'),
                ...$arguments, '--dbname='.$connection->getDatabaseName()]);

        if (! $result->successful()) {
            throw new RuntimeException("{$tool} failed: ".trim(mb_substr($result->errorOutput(), 0, 800)));
        }

        return $result->output();
    }

    /**
     * Runs `pg_restore` on a dump alone, without a database: its list or one table's data as text.
     *
     * @param  list<string>  $arguments
     */
    public static function read(string $dump, array $arguments): string
    {
        $result = Process::timeout(config()->integer('backup.tool_timeout_seconds'))->run([config()->string('backup.pg_restore'), ...$arguments, $dump]);

        if (! $result->successful()) {
            throw new RuntimeException('the dump cannot be read: '.trim(mb_substr($result->errorOutput(), 0, 400)));
        }

        return $result->output();
    }

    /**
     * The rows of one table of a dump, from `pg_restore`'s COPY text: each row's named columns, `\N` read as null.
     *
     * @param  list<string>  $columns
     * @return list<array<string, ?string>>
     */
    public static function rows(string $dump, string $table, array $columns): array
    {
        $text = self::read($dump, ['--data-only', '--table='.$table, '--file=-']);
        $rows = [];
        $positions = null;

        foreach (explode("\n", $text) as $line) {
            if ($positions === null) {
                if (preg_match('/^COPY \S+ \(([^)]*)\) FROM stdin;$/', $line, $match) === 1) {
                    $names = array_map(fn (string $name): string => trim($name, ' "'), explode(',', $match[1]));
                    $positions = [];

                    foreach ($columns as $column) {
                        $at = array_search($column, $names, true);

                        if ($at === false) {
                            throw new RuntimeException("the dump's {$table} has no column {$column}");
                        }

                        $positions[$column] = $at;
                    }
                }

                continue;
            }

            if ($line === '\\.') {
                break;
            }

            $values = explode("\t", $line);
            $row = [];

            foreach ($positions as $column => $at) {
                $value = $values[$at] ?? '\\N';
                $row[$column] = $value === '\\N' ? null : stripcslashes($value);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** The recovery point a dump records: the time `pg_dump` began, at its snapshot. */
    public static function createdAt(string $dump): ?string
    {
        return preg_match('/Archive created at (\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) ?(\S*)/', self::read($dump, ['--list']), $match) === 1 ? trim($match[1].' '.$match[2]) : null;
    }

    private static function given(string $variable, string $config): string
    {
        $value = getenv($variable);

        return is_string($value) && $value !== '' ? $value : Scalars::text(config($config));
    }

    /** @param  array<array-key, mixed>  $config */
    private static function text(array $config, string $key): string
    {
        $value = $config[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
