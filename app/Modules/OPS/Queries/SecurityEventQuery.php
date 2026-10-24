<?php

namespace App\Modules\OPS\Queries;

use App\Modules\OPS\Models\SecurityEvent;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityLogHash;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The read model of PG-064 *Log Keamanan* (SECURITY LG-05): the security events under `security_log.view`
 * (OWNER_ONLY), the two TECH-022 kinds included, newest first in keyset order, filtered by WIB business
 * days (GAP-016). A searched login identifier or address is matched through its keyed hash, never stored
 * or compared in the clear (LG-03). It returns rows only to a viewer holding the capability; an account's
 * own events (PJ-17) are not this read.
 */
final class SecurityEventQuery
{
    public const CAPABILITY = 'security_log.view';

    /**
     * @param  string|null  $from  the first WIB business day, `Y-m-d`
     * @param  string|null  $to  the last WIB business day, `Y-m-d`
     * @param  list<SecurityEventKind>  $kinds  none for every kind
     * @return KeysetPage<SecurityEvent>
     */
    public function page(
        Authenticatable $viewer,
        ?string $from = null,
        ?string $to = null,
        array $kinds = [],
        ?int $userId = null,
        ?int $actorUserId = null,
        ?string $reasonCode = null,
        ?string $identifier = null,
        ?string $ipAddress = null,
        ?string $correlationId = null,
        int $perPage = 50,
        ?string $cursor = null,
    ): KeysetPage {
        Gate::forUser($viewer)->authorize(self::CAPABILITY);

        $query = SecurityEvent::query()
            ->when($from !== null, fn (Builder $query) => $query->where('occurred_at', '>=', BusinessDay::start((string) $from)))
            ->when($to !== null, fn (Builder $query) => $query->where('occurred_at', '<', BusinessDay::end((string) $to)))
            ->when($kinds !== [], fn (Builder $query) => $query->whereIn('event_kind', array_map(fn (SecurityEventKind $kind): string => $kind->value, $kinds)))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($actorUserId !== null, fn (Builder $query) => $query->where('actor_user_id', $actorUserId))
            ->when($reasonCode !== null, fn (Builder $query) => $query->where('reason_code', $reasonCode))
            ->when($identifier !== null, fn (Builder $query) => $query->where('identifier_hash', SecurityLogHash::identifier((string) $identifier)))
            ->when($ipAddress !== null, fn (Builder $query) => $query->where('ip_hash', SecurityLogHash::address((string) $ipAddress)))
            ->when($correlationId !== null, fn (Builder $query) => $query->where('correlation_id', $correlationId));

        return KeysetPage::of($query, $perPage, $cursor);
    }
}
