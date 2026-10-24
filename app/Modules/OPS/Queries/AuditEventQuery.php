<?php

namespace App\Modules\OPS\Queries;

use App\Modules\OPS\Enums\AuthorityClass;
use App\Modules\OPS\Models\AuditEvent;
use App\Support\BusinessDate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The read model of PG-063 *Riwayat Aktivitas* (SECURITY LG-05): the business audit under `audit.view`
 * (OWNER_ONLY), newest first in keyset order (IX-13), filtered by WIB business days (GAP-016) and by the
 * gap between a backdated command's business date and its recorded instant (LG-01; TM-44). It returns
 * rows only to a viewer holding the capability; the field projection of PJ-11 is TASK-012's.
 */
final class AuditEventQuery
{
    public const CAPABILITY = 'audit.view';

    /**
     * @param  string|null  $from  the first WIB business day, `Y-m-d`
     * @param  string|null  $to  the last WIB business day, `Y-m-d`
     * @param  int|null  $minimumBackdatedDays  only events recorded at least this many WIB days after their business date
     * @return KeysetPage<AuditEvent>
     */
    public function page(
        Authenticatable $viewer,
        ?string $from = null,
        ?string $to = null,
        ?int $companyId = null,
        ?int $actorUserId = null,
        ?string $action = null,
        ?string $entityType = null,
        ?string $entityPublicId = null,
        ?AuthorityClass $authorityClass = null,
        ?string $correlationId = null,
        ?string $commandId = null,
        ?int $minimumBackdatedDays = null,
        int $perPage = 50,
        ?string $cursor = null,
    ): KeysetPage {
        Gate::forUser($viewer)->authorize(self::CAPABILITY);

        $query = AuditEvent::query()
            ->when($from !== null, fn (Builder $query) => $query->where('occurred_at', '>=', BusinessDay::start((string) $from)))
            ->when($to !== null, fn (Builder $query) => $query->where('occurred_at', '<', BusinessDay::end((string) $to)))
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($actorUserId !== null, fn (Builder $query) => $query->where('actor_user_id', $actorUserId))
            ->when($action !== null, fn (Builder $query) => $query->where('action', $action))
            ->when($entityType !== null, fn (Builder $query) => $query->where('entity_type', $entityType))
            ->when($entityPublicId !== null, fn (Builder $query) => $query->where('entity_public_id', $entityPublicId))
            ->when($authorityClass !== null, fn (Builder $query) => $query->where('authority_class', $authorityClass?->value))
            ->when($correlationId !== null, fn (Builder $query) => $query->where('correlation_id', $correlationId))
            ->when($commandId !== null, fn (Builder $query) => $query->where('command_id', $commandId))
            ->when($minimumBackdatedDays !== null, fn (Builder $query) => $query
                ->whereNotNull('metadata->business_date')
                ->whereRaw("(recorded_at at time zone ?)::date - (metadata->>'business_date')::date >= ?", [BusinessDate::TIMEZONE, $minimumBackdatedDays]));

        return KeysetPage::of($query, $perPage, $cursor);
    }
}
