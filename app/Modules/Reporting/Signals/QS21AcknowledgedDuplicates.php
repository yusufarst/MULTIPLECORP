<?php

namespace App\Modules\Reporting\Signals;

use App\Support\RecordAddress;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-21 (WORKFLOWS §10; IA §9.1): duplicate warnings overridden or confirmed — the audit events of the acknowledged
 * variant (CONCURRENCY_IDEMPOTENCY section 11) on IX-13's partial index over the acknowledged codes — shown per family
 * within scope (PERMISSIONS_MATRIX §7.4): stock events under RD-02, delivery events under RD-03, payments under RD-04,
 * evidence under RD-08; each also reaches the Owner through QS-14. Stock events written without a company — a count's,
 * an unattributed recognition's, a condition change's — form the pool's branch, read with `stock.view`. Newest first;
 * the record.
 */
final class QS21AcknowledgedDuplicates extends Signal
{
    /** The pool's branch: the stock events that carry no company. */
    private const POOL = 0;

    public function code(): string
    {
        return 'QS-21';
    }

    public function companies(Context $context): array
    {
        $companies = array_values(array_filter(array_keys($context->companies), fn (int $company): bool => self::codes($context, $company) !== []));

        return $context->viewer->has('stock.view') ? [...$companies, self::POOL] : $companies;
    }

    protected function newestFirst(): bool
    {
        return true;
    }

    protected function branch(Context $context, int $company): Builder
    {
        $codes = $company === self::POOL ? AuditCodes::ACKNOWLEDGED_STOCK : self::codes($context, $company);

        return DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->when($company === self::POOL, fn (Builder $where) => $where->whereNull('a.company_id'), fn (Builder $where) => $where->where('a.company_id', $company))
            // The literal list of IX-13's partial index first, so the plan uses it; then the viewer's families.
            ->whereRaw('a.action in ('.AuditCodes::ACKNOWLEDGED_SQL.')')->whereIn('a.action', $codes)->orderByDesc('a.occurred_at')->orderByDesc('a.id')
            ->selectRaw("'a' || a.id as key, a.company_id, a.action as subject, u.name as ref, a.entity_type as value, null::text as unit, a.occurred_at::text as at, a.entity_public_id::text as target");
    }

    /**
     * The acknowledged codes of the families the viewer reads in the company.
     *
     * @return list<string>
     */
    private static function codes(Context $context, int $company): array
    {
        return [
            ...($context->viewer->has('stock.view') ? AuditCodes::ACKNOWLEDGED_STOCK : []),
            ...($context->viewer->allows('projects.view', $company) ? AuditCodes::ACKNOWLEDGED_DELIVERY : []),
            ...($context->viewer->allows('finance.view', $company) ? AuditCodes::ACKNOWLEDGED_PAYMENT : []),
            ...($context->viewer->allows('evidence.view', $company) ? AuditCodes::ACKNOWLEDGED_EVIDENCE : []),
        ];
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
