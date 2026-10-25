<?php

namespace App\Support\Command;

/**
 * The transaction classes of CONCURRENCY_IDEMPOTENCY TX-01–TX-10 that run commands, with the lock and
 * statement timeouts RY-04 sets by `SET LOCAL` (RY-09: P9 may change the values from measurement). Every
 * command class runs at READ COMMITTED; the snapshot reads and maintenance are not commands.
 */
enum TransactionClass: string
{
    case Interactive = 'TX-01';
    case Cascade = 'TX-02';
    case Driven = 'TX-03';
    case Identity = 'TX-07';
    case AccessChange = 'TX-09';

    public function lockTimeout(): string
    {
        return match ($this) {
            self::Interactive, self::Identity => '3s',
            self::Cascade => '5s',
            self::Driven => '10s',
            self::AccessChange => '20s',
        };
    }

    public function statementTimeout(): string
    {
        return match ($this) {
            self::Interactive => '10s',
            self::Cascade => '30s',
            self::Driven => '60s',
            self::Identity => '5s',
            self::AccessChange => '25s',
        };
    }
}
