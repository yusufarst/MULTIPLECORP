<?php

namespace App\Modules\IAM\Queries;

use App\Support\SecurityLog\SecurityEventKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The account's previous successful sign-in (AU-13; PJ-17): for a signed-in account, the successful sign-in before
 * the one its session began with — the second newest LOGIN_SUCCEEDED event, on `security_events (event_kind,
 * occurred_at, id)` —, or its only one. It divides *Tinjauan*'s newer items from the older and bounds the Owner's
 * notices and *Beranda*'s new review items; nothing is marked read (IA §9.2).
 */
final class PreviousSignIn
{
    public function of(int $userId): ?string
    {
        $times = DB::table('security_events')->where('event_kind', SecurityEventKind::LoginSucceeded->value)->where('user_id', $userId)
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(2)->pluck('occurred_at')->all();
        $at = $times[1] ?? $times[0] ?? null;

        return is_string($at) ? CarbonImmutable::parse($at)->toIso8601ZuluString() : null;
    }
}
