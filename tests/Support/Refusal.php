<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Proves that PostgreSQL refuses a write (TASK-005): the write runs in a savepoint of the test's
 * transaction, so the refusal leaves the transaction usable, and the refusal must carry the expected
 * SQLSTATE — 23514 a CHECK or a guard trigger, 23505 a unique key, 23P01 an exclusion constraint, 23503 a
 * foreign key, 42501 a missing privilege — and name the expected constraint, trigger or table in its
 * message.
 */
final class Refusal
{
    public const CHECK = '23514';

    public const UNIQUE = '23505';

    public const EXCLUSION = '23P01';

    public const FOREIGN_KEY = '23503';

    public const PRIVILEGE = '42501';

    public static function assert(string $sqlState, string $named, Closure $write, ?string $connection = null): void
    {
        try {
            DB::connection($connection)->transaction(static function () use ($write): void {
                $write();
            });
        } catch (QueryException $exception) {
            Assert::assertSame($sqlState, $exception->getCode(), $exception->getMessage());
            Assert::assertStringContainsString($named, $exception->getMessage());

            return;
        }

        Assert::fail("The write was accepted; expected SQLSTATE {$sqlState} naming {$named}.");
    }
}
