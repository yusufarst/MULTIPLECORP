<?php

namespace App\Support\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The bounds of CONCURRENCY_IDEMPOTENCY's transaction classes that run no command of their own (HO-21; RY-04; SZ-05). On
 * staging and production the runtime's login defaults to TX-06's 5 s statement timeout, which page reads rely on, and
 * the 15 s idle-in-transaction backstop (docker/postgres/provision-roles.sh), so a transaction of a longer class raises
 * its own bounds by SET LOCAL, which end with it. The commands' classes are TransactionClass's, set by the command
 * envelope; INFRASTRUCTURE §4.4 owns the values and may change them from measurement (RY-09).
 */
final class Timeouts
{
    /** TX-03 outside a command: the import pipeline's dry run, every row's command inside one rolled-back transaction. */
    public const DRIVEN = "set local lock_timeout = '10s'; set local statement_timeout = '60s'; set local idle_in_transaction_session_timeout = '60s'";

    /** TX-04: a job's claim and finalization, an export's admission and its file, the upload's own transaction. */
    public const BOOKKEEPING = "set local lock_timeout = '5s'; set local statement_timeout = '30s'";

    /** TX-05: a job's snapshot read — an export renders inside it, so the idle backstop rises with its statement bound. */
    public const JOB_SNAPSHOT = "set local statement_timeout = '120s'; set local idle_in_transaction_session_timeout = '120s'";

    /** TX-05 for `ReconcileGuards`: 300 s per guard family. */
    public const RECONCILIATION = "set local statement_timeout = '300s'; set local idle_in_transaction_session_timeout = '300s'";

    /**
     * Inside an open transaction only: SET LOCAL outside one is a no-op the server warns about.
     *
     * @param  self::DRIVEN|self::BOOKKEEPING|self::JOB_SNAPSHOT|self::RECONCILIATION  $bounds
     */
    public static function apply(string $bounds, ?ConnectionInterface $connection = null): void
    {
        ($connection ?? DB::connection())->unprepared($bounds);
    }
}
