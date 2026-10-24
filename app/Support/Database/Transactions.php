<?php

namespace App\Support\Database;

use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;

/**
 * Whether the application holds a transaction open on the default connection — a command's own, never the
 * wrapping transaction a test runs in, which the framework's test transaction manager leaves out. The audit
 * writer requires one (SECURITY LG-01) and the security event writer waits for it to end (LG-02), so a test
 * proves the same transaction boundaries as production.
 */
final class Transactions
{
    public static function applicationOpen(): bool
    {
        /** @var DatabaseTransactionsManager $manager */
        $manager = app('db.transactions');
        $connection = DB::getDefaultConnection();

        return $manager->callbackApplicableTransactions()
            ->contains(fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $connection);
    }
}
