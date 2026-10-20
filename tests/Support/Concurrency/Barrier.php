<?php

namespace Tests\Support\Concurrency;

use LogicException;
use RuntimeException;

/**
 * The barrier of TEST_STRATEGY §6: inside a task of Sessions::run(), await() holds the session at a
 * named point until every session has reached it. Arrivals are files in a directory of the run, so a
 * barrier never takes a database lock of its own and never forgets an arrival.
 */
final class Barrier
{
    /** @var array{directory: string, parties: int}|null */
    private static ?array $run = null;

    /**
     * @return array{directory: string, parties: int}
     */
    public static function open(int $parties): array
    {
        $directory = sys_get_temp_dir().'/multiplecorp-barrier-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException("The barrier directory {$directory} cannot be created.");
        }

        return ['directory' => $directory, 'parties' => $parties];
    }

    /**
     * @param  array{directory: string, parties: int}  $run
     */
    public static function join(array $run): void
    {
        self::$run = $run;
    }

    /**
     * @param  array{directory: string, parties: int}  $run
     */
    public static function close(array $run): void
    {
        array_map('unlink', glob($run['directory'].'/*') ?: []);
        rmdir($run['directory']);
    }

    public static function await(string $point, int $timeoutSeconds = 30): void
    {
        $run = self::$run ?? throw new LogicException('Barrier::await() runs inside a task of Sessions::run().');
        $arrivals = $run['directory'].'/'.md5($point);

        touch($arrivals.'.'.getmypid());

        $deadline = hrtime(true) + $timeoutSeconds * 1_000_000_000;

        while (count(glob($arrivals.'.*') ?: []) < $run['parties']) {
            if (hrtime(true) > $deadline) {
                throw new RuntimeException("The barrier \"{$point}\" was not reached by all {$run['parties']} sessions within {$timeoutSeconds} s.");
            }

            usleep(5_000);
        }
    }
}
