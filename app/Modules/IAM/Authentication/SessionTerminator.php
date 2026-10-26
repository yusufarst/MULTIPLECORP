<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Support\Facades\DB;

/**
 * Ending an account's sessions (SECURITY AU-09; HO-20): the framework's `database` session store keeps one row per
 * session with its account (AU-06), so an account's sessions end by deleting its rows — every one at deactivation
 * (TASK-024) and after a password set through a credential link, the others at a password change. A deleted session
 * is a guest's at its next request, which the authentication middleware answers (UXH-11).
 */
final class SessionTerminator
{
    /** @return int the sessions ended */
    public function endAll(int $userId, ?string $exceptSessionId = null): int
    {
        $table = config('session.table');

        return DB::table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $userId)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
