<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * TL-09 (TASK-093): the readings the in-place re-authentication's browser cases need and no module fixture holds —
 * an account's session aged past AU-07's idle limit, so its next request is answered 401, and the recorded outcome of
 * the commands a page sent, by `command_id`, so a replay is counted by what the server recorded, never by a second
 * effect. Synthetic accounts only; local only.
 */
final class ReauthFixture
{
    public static function age(Request $request): JsonResponse
    {
        $user = User::query()->where('public_id', $request->string('account')->toString())->first();

        if (! $user instanceof User) {
            return response()->json(['error' => 'unknown account'], 422);
        }

        $rows = DB::table('sessions')->where('user_id', $user->id);
        (clone $rows)->update(['last_activity' => DB::raw('last_activity - 3700')]);

        return response()->json(['sessions' => (clone $rows)->count()]);
    }

    public static function commands(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(explode(',', $request->string('ids')->toString()), fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1));
        $rows = $ids === [] ? [] : DB::table('command_log')
            ->leftJoin('users', 'users.id', '=', 'command_log.actor_user_id')
            ->whereIn('command_log.command_id', $ids)
            ->get(['command_log.command_id', 'command_log.state', 'users.public_id as actor'])
            ->map(fn (stdClass $row): array => (array) $row)
            ->all();

        return response()->json(['commands' => $rows]);
    }
}
