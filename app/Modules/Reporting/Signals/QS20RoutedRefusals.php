<?php

namespace App\Modules\Reporting\Signals;

use App\Modules\OPS\Data\AuditCodes;
use App\Support\RecordAddress;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-20 (WORKFLOWS §10; IA §9.1): routed refusals needing other-company grants — the audit events of the routed
 * variant (SQ-23) of the last 14 days, on IX-13's partial index over the routed codes, one item per actor, refused
 * action and target, each a notice with its age that leaves the list after 14 days, no task state and no "selesai"
 * control. An item is shown when every event of its refused command lies in a company in the viewer's scope (PJ-13):
 * the test reads the other companies' events of that command by `command_id` — the one read beyond scope, returning
 * nothing from there (PF-24). The target is the event naming the record, not a company; its record is the link.
 */
final class QS20RoutedRefusals extends Signal
{
    public const DAYS = 14;

    public function code(): string
    {
        return 'QS-20';
    }

    public function companies(Context $context): array
    {
        return $context->viewer->has('review.view') ? array_keys($context->companies) : $context->holding('projects.view');
    }

    protected function newestFirst(): bool
    {
        return true;
    }

    protected function branch(Context $context, int $company): Builder
    {
        $scope = $context->viewer->scope;
        $outside = $scope->all ? null : '{'.implode(',', $scope->companyIds).'}';

        $latest = DB::table('audit_events as a')->where('a.company_id', $company)->whereRaw('a.action in ('.AuditCodes::ROUTED_SQL.')')
            ->where('a.occurred_at', '>=', now()->subDays(self::DAYS))->where('a.entity_type', '<>', 'company')
            ->when($outside !== null, fn (Builder $where) => $where->whereRaw('not exists (select 1 from audit_events o where o.command_id = a.command_id and o.action in ('.AuditCodes::ROUTED_SQL.') and (o.company_id is null or not (o.company_id = any (?::bigint[]))))', [$outside]))
            ->selectRaw('distinct on (a.actor_user_id, a.action, a.entity_type, a.entity_id) a.id, a.company_id, a.action, a.actor_user_id, a.entity_type, a.entity_public_id, a.occurred_at')
            ->orderBy('a.actor_user_id')->orderBy('a.action')->orderBy('a.entity_type')->orderBy('a.entity_id')->orderByDesc('a.occurred_at');

        return DB::query()->fromSub($latest, 'r')->leftJoin('users as u', 'u.id', '=', 'r.actor_user_id')->orderByDesc('r.occurred_at')
            ->selectRaw("'a' || r.id as key, r.company_id, r.action as subject, u.name as ref, r.entity_type as value, null::text as unit, r.occurred_at::text as at, r.entity_public_id::text as target");
    }

    protected function href(Context $context, object $row): ?string
    {
        return RecordAddress::of(Row::nullable($row, 'value'), Row::nullable($row, 'target'));
    }

    public function seeAll(Context $context): ?string
    {
        return null;
    }
}
