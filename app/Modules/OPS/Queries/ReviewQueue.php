<?php

namespace App\Modules\OPS\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Queries\PreviousSignIn;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Data\AuditCodes;
use App\Support\BusinessDate;
use App\Support\RecordAddress;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * `OPS ShowReviewQueue` — PG-008 *Tinjauan* (ROUTE-008; SCR-07; IA §9.2; PERMISSIONS_MATRIX §10): a derived view,
 * never a stored queue and never an approval step — every listed action is already in effect and the Owner acts only
 * through the records' normal commands and correction rows. Two parts:
 *
 * - *Kondisi yang Masih Terbuka*: recovered money linked to a written-off invoice still unapplied, refunds with an
 *   unbacked remainder and invoices left outstanding after a payment — conditions that leave by themselves once
 *   their cause is resolved;
 * - *Aktivitas untuk Ditinjau* in four tabs, each its own list newest first within the chosen window — *Aktivitas*
 *   (audit events of authority class ADM_PLUS or OWNER_ONLY and acknowledged duplicate warnings, QS-21), *Keamanan*
 *   (the security kinds AUTHORIZATION_ANOMALY, EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED, the
 *   two undisclosed kinds with both records under the Owner's projection, their companies, the account and the
 *   match basis — `TECH-022`; GAP-034), *Bukti Gudang Bersama* (pool evidence) and *Impor* (import rows in state
 *   DUPLICATE whose committed twin lies outside the batch's scope) —, continued by "Muat Lagi", with no total.
 *
 * The window is the last seven days by default, *Hari ini*, *30 hari* or a range; the filters are a company — an
 * item without one, labelled *Gudang bersama* or *Tanpa perusahaan*, stays under a company filter — and an actor. The
 * divider "Tercatat setelah Anda masuk sebelumnya" is the Owner's previous sign-in (AU-13; PJ-17); nothing is marked
 * read, done or dismissed (UXS-18; UXS-19; UXS-40).
 */
final class ReviewQueue
{
    public const PAGE = 50;

    public const TABS = ['aktivitas', 'keamanan', 'bukti', 'impor'];

    /** The window keys and their days back from today (IA §9.2): *Hari ini*, *7 hari*, *30 hari*. */
    public const WINDOWS = ['hari_ini' => 0, '7_hari' => 6, '30_hari' => 29];

    /** The security kinds of the *Keamanan* tab (SECURITY LG-02; `TECH-022`). */
    public const SECURITY_KINDS = ['AUTHORIZATION_ANOMALY', 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED'];

    /** The conditions read per part, newest first. */
    private const CONDITIONS = 20;

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly PreviousSignIn $signIns,
    ) {}

    /**
     * @param  array{tab: ?string, jangka: ?string, dari: ?string, sampai: ?string, perusahaan: ?string, pelaku: ?string, setelah: ?string}  $query
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, array $query): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $tab = in_array($query['tab'], self::TABS, true) ? (string) $query['tab'] : 'aktivitas';
        [$window, $from, $to] = self::window($query['jangka'], $query['dari'], $query['sampai']);
        $companies = $this->companies($viewer);
        $company = self::chosen($companies, $query['perusahaan']);
        $actor = $query['pelaku'] !== null && preg_match('/^[0-9a-f-]{36}$/i', $query['pelaku']) === 1 ? DB::table('users')->where('public_id', strtolower($query['pelaku']))->value('id') : null;
        $after = self::cursor($query['setelah']);
        $read = match ($tab) {
            'keamanan' => $this->security($from, $to, $company, is_numeric($actor) ? (int) $actor : null, $after),
            'bukti' => $this->pool($from, $to, is_numeric($actor) ? (int) $actor : null, $after),
            'impor' => $this->imports($from, $to, is_numeric($actor) ? (int) $actor : null, $after),
            default => $this->activity(array_keys($companies), $from, $to, $company, is_numeric($actor) ? (int) $actor : null, $after),
        };

        return [
            'tab' => $tab,
            'window' => ['key' => $window, 'from' => $from->setTimezone(BusinessDate::TIMEZONE)->toDateString(), 'to' => $to->subSecond()->setTimezone(BusinessDate::TIMEZONE)->toDateString()],
            'filters' => ['perusahaan' => $company === null ? null : $companies[$company]['id'], 'pelaku' => is_numeric($actor) ? $query['pelaku'] : null],
            'companies' => array_values(array_map(fn (array $row): array => ['id' => $row['id'], 'code' => $row['code']], $companies)),
            'previous' => $this->signIns->of($viewerId),
            'items' => $read['items'],
            'more' => $read['more'],
        ];
    }

    /**
     * *Kondisi yang Masih Terbuka* (IA §9.2): three bounded statements, newest first, under the Owner's projection.
     *
     * @return list<array{kind: string, company: ?string, record: string, since: string, href: ?string}>
     */
    public function conditions(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $rows = [];

        $recovered = $viewer->scope->apply(DB::table('payments as p'), 'p.company_id')->join('application_source_balances as a', 'a.payment_id', '=', 'p.id')
            ->join('invoices as i', 'i.id', '=', 'p.recovery_for_invoice_id')->join('companies as c', 'c.id', '=', 'p.company_id')
            ->whereNotNull('p.recovery_for_invoice_id')->where('a.capacity_amount', '>', 0)->orderByDesc('p.id')->limit(self::CONDITIONS)
            ->get(['c.code', 'p.business_date', 'a.capacity_amount', 'i.public_id']);

        foreach ($recovered as $row) {
            $rows[] = ['kind' => 'recovered', 'company' => Row::text($row, 'code'), 'record' => Row::text($row, 'capacity_amount'), 'since' => Row::text($row, 'business_date'), 'href' => RecordAddress::of('invoice', Row::text($row, 'public_id'))];
        }

        $refunds = $viewer->scope->apply(DB::table('money_fact_balances as f'), 'f.company_id')->join('companies as c', 'c.id', '=', 'f.company_id')
            ->leftJoin('correction_cases as k', 'k.id', '=', 'f.residual_case_id')
            ->where('f.fact_kind', 'REFUND')->where('f.shortfall_amount', '>', 0)->orderByDesc('f.id')->limit(self::CONDITIONS)
            ->get(['c.code', 'f.recorded_at', 'f.shortfall_amount', 'k.public_id']);

        foreach ($refunds as $row) {
            $rows[] = ['kind' => 'unbacked_refund', 'company' => Row::text($row, 'code'), 'record' => Row::text($row, 'shortfall_amount'), 'since' => Row::text($row, 'recorded_at'), 'href' => RecordAddress::of('correction_case', Row::nullable($row, 'public_id'))];
        }

        $outstanding = $viewer->scope->apply(DB::table('invoice_balances as b'), 'b.company_id')->join('invoices as i', 'i.id', '=', 'b.invoice_id')->join('companies as c', 'c.id', '=', 'b.company_id')
            ->where('b.outstanding', '>', 0)->where('b.applied_amount', '>', 0)->where('i.state', 'ISSUED')->orderByDesc('b.invoice_id')->limit(self::CONDITIONS)
            ->get(['c.code', 'b.recorded_at', 'b.outstanding', 'i.public_id']);

        foreach ($outstanding as $row) {
            $rows[] = ['kind' => 'outstanding_after_payment', 'company' => Row::text($row, 'code'), 'record' => Row::text($row, 'outstanding'), 'since' => Row::text($row, 'recorded_at'), 'href' => RecordAddress::of('invoice', Row::text($row, 'public_id'))];
        }

        return $rows;
    }

    /**
     * QS-14 on *Beranda* (IA §9.1): the items recorded since the Owner's previous sign-in from every source of PG-008,
     * counted per company and bounded (PF-15) — one statement: a branch per company over its audit events, one for the
     * items without a company.
     *
     * @param  list<int>  $companies
     * @return array<int|string, int> company id, or '' for the items without one => a count up to 21
     */
    public function newSince(array $companies, string $since): array
    {
        $bound = 21;
        $branches = DB::query()->fromSub(
            DB::table('security_events')->whereIn('event_kind', self::SECURITY_KINDS)->where('occurred_at', '>', $since)->selectRaw('null::bigint as company_id')
                ->unionAll(DB::table('evidence_documents')->where('evidence_scope', 'POOL')->where('recorded_at', '>', $since)->selectRaw('null::bigint'))
                ->unionAll(self::duplicates()->where('r.recorded_at', '>', $since)->selectRaw('null::bigint')), 'x')->limit($bound)->select('x.company_id');

        foreach ($companies as $company) {
            $branches->unionAll(DB::table('audit_events as a')->where('a.company_id', $company)->where('a.occurred_at', '>', $since)
                ->whereRaw("(a.authority_class in ('ADM_PLUS', 'OWNER_ONLY') or a.action in (".AuditCodes::ACKNOWLEDGED_SQL.'))')
                ->limit($bound)->selectRaw('a.company_id'));
        }

        $counts = [];

        foreach ($branches->get() as $row) {
            $key = Row::nullable($row, 'company_id') ?? '';
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * The companies the queue may name, in the viewer's scope.
     *
     * @return array<int, array{id: string, code: string}>
     */
    private function companies(Authority $viewer): array
    {
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code']) as $row) {
            $companies[(int) Row::text($row, 'id')] = ['id' => Row::text($row, 'public_id'), 'code' => Row::text($row, 'code')];
        }

        return $companies;
    }

    /** @param  array<int, array{id: string, code: string}>  $companies */
    private static function chosen(array $companies, ?string $publicId): ?int
    {
        foreach ($companies as $id => $company) {
            if ($publicId !== null && strtolower($publicId) === $company['id']) {
                return $id;
            }
        }

        return null;
    }

    /**
     * *Aktivitas*: a branch per company — or the chosen one — and one for the events without a company, each on IX-13's
     * partial indexes of the privileged class and of the acknowledged codes, merged newest first (PF-41).
     *
     * @param  list<int>  $companies
     * @param  ?array{string, int}  $after
     * @return array{items: list<array<string, mixed>>, more: ?string}
     */
    private function activity(array $companies, CarbonImmutable $from, CarbonImmutable $to, ?int $company, ?int $actor, ?array $after): array
    {
        $branch = fn (?int $id): Builder => DB::table('audit_events')->select('id')
            ->when($id === null, fn (Builder $where) => $where->whereNull('company_id'), fn (Builder $where) => $where->where('company_id', $id))
            ->whereRaw("(authority_class in ('ADM_PLUS', 'OWNER_ONLY') or action in (".AuditCodes::ACKNOWLEDGED_SQL.'))')
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)
            ->when($actor !== null, fn (Builder $where) => $where->where('actor_user_id', $actor))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(occurred_at, id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(self::PAGE + 1);
        $ids = $company === null ? [...$companies, null] : [$company, null];
        $branches = $branch(array_shift($ids));

        foreach ($ids as $id) {
            $branches->unionAll($branch($id));
        }

        $rows = DB::table('audit_events as a')->joinSub($branches, 'b', 'b.id', '=', 'a.id')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->orderByDesc('a.occurred_at')->orderByDesc('a.id')->limit(self::PAGE + 1)
            ->get(['a.id', 'a.occurred_at', 'a.action', 'a.entity_type', 'a.entity_public_id', 'a.reason', 'u.name as actor', 'c.code as company',
                DB::raw("a.metadata->>'business_date' as business_date")])->all();

        return self::paged($rows, fn (object $row): array => [
            'key' => 'a'.Row::text($row, 'id'),
            'at' => Row::text($row, 'occurred_at'),
            'business_date' => Row::nullable($row, 'business_date'),
            'actor' => Row::nullable($row, 'actor'),
            'company' => Row::nullable($row, 'company'),
            'kind' => Row::text($row, 'action'),
            'reason' => Row::nullable($row, 'reason'),
            'href' => RecordAddress::of(Row::nullable($row, 'entity_type'), Row::nullable($row, 'entity_public_id')),
        ]);
    }

    /**
     * *Keamanan*: the three kinds on the partial index `security_events_ix14_owner_review (occurred_at)`; the two
     * undisclosed kinds with both records — the target and the related one — and their companies (`TECH-022`).
     *
     * @param  ?array{string, int}  $after
     * @return array{items: list<array<string, mixed>>, more: ?string}
     */
    private function security(CarbonImmutable $from, CarbonImmutable $to, ?int $company, ?int $actor, ?array $after): array
    {
        $rows = DB::table('security_events as e')->leftJoin('users as u', 'u.id', '=', DB::raw('coalesce(e.actor_user_id, e.user_id)'))
            ->leftJoin('companies as c', 'c.id', '=', 'e.company_id')->leftJoin('companies as r', 'r.id', '=', 'e.related_company_id')
            ->whereIn('e.event_kind', self::SECURITY_KINDS)->where('e.occurred_at', '>=', $from)->where('e.occurred_at', '<', $to)
            ->when($company !== null, fn (Builder $where) => $where->where(fn (Builder $or) => $or->whereNull('e.company_id')->orWhere('e.company_id', $company)->orWhere('e.related_company_id', $company)))
            ->when($actor !== null, fn (Builder $where) => $where->where(fn (Builder $or) => $or->where('e.actor_user_id', $actor)->orWhere('e.user_id', $actor)))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(e.occurred_at, e.id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('e.occurred_at')->orderByDesc('e.id')->limit(self::PAGE + 1)
            ->get(['e.id', 'e.occurred_at', 'e.event_kind', 'e.reason_code', 'e.target_entity_type', 'e.target_public_id', 'e.related_entity_type', 'e.related_public_id',
                'u.name as actor', 'c.code as company', 'r.code as related_company'])->all();

        return self::paged($rows, fn (object $row): array => [
            'key' => 'e'.Row::text($row, 'id'),
            'at' => Row::text($row, 'occurred_at'),
            'business_date' => null,
            'actor' => Row::nullable($row, 'actor'),
            'company' => Row::nullable($row, 'company'),
            'kind' => Row::text($row, 'event_kind'),
            'reason' => Row::nullable($row, 'reason_code'),
            'href' => RecordAddress::of(Row::nullable($row, 'target_entity_type'), Row::nullable($row, 'target_public_id')),
            'related' => Row::nullable($row, 'related_public_id') === null ? null : [
                'company' => Row::nullable($row, 'related_company'),
                'href' => RecordAddress::of(Row::nullable($row, 'related_entity_type'), Row::nullable($row, 'related_public_id')),
            ],
        ]);
    }

    /**
     * *Bukti Gudang Bersama*: the uploads of scope POOL, which carry no company (*Gudang bersama*).
     *
     * @param  ?array{string, int}  $after
     * @return array{items: list<array<string, mixed>>, more: ?string}
     */
    private function pool(CarbonImmutable $from, CarbonImmutable $to, ?int $actor, ?array $after): array
    {
        $rows = DB::table('evidence_documents as d')->leftJoin('users as u', 'u.id', '=', 'd.recorded_by')
            ->where('d.evidence_scope', 'POOL')->where('d.recorded_at', '>=', $from)->where('d.recorded_at', '<', $to)
            ->when($actor !== null, fn (Builder $where) => $where->where('d.recorded_by', $actor))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(d.recorded_at, d.id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('d.recorded_at')->orderByDesc('d.id')->limit(self::PAGE + 1)
            ->get(['d.id', 'd.recorded_at as occurred_at', 'd.evidence_type', 'd.declared_issuer', 'd.public_id', 'u.name as actor'])->all();

        return self::paged($rows, fn (object $row): array => [
            'key' => 'd'.Row::text($row, 'id'),
            'at' => Row::text($row, 'occurred_at'),
            'business_date' => null,
            'actor' => Row::nullable($row, 'actor'),
            'company' => null,
            'kind' => Row::text($row, 'evidence_type'),
            'reason' => Row::nullable($row, 'declared_issuer'),
            'href' => RecordAddress::of('evidence_document', Row::text($row, 'public_id')),
        ]);
    }

    /**
     * *Impor*: the import rows in state DUPLICATE whose committed twin lies in a company outside the batch's scope
     * (DP-18; UXS-19), which carry no company of their own (*Tanpa perusahaan*).
     *
     * @param  ?array{string, int}  $after
     * @return array{items: list<array<string, mixed>>, more: ?string}
     */
    private function imports(CarbonImmutable $from, CarbonImmutable $to, ?int $actor, ?array $after): array
    {
        $rows = self::duplicates()->leftJoin('users as u', 'u.id', '=', 'b.prepared_by')
            ->where('r.recorded_at', '>=', $from)->where('r.recorded_at', '<', $to)
            ->when($actor !== null, fn (Builder $where) => $where->where('b.prepared_by', $actor))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(r.recorded_at, r.id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('r.recorded_at')->orderByDesc('r.id')->limit(self::PAGE + 1)
            ->get(['r.id', 'r.recorded_at as occurred_at', 'r.import_kind', 'r.row_number', 'b.public_id', 'u.name as actor'])->all();

        return self::paged($rows, fn (object $row): array => [
            'key' => 'r'.Row::text($row, 'id'),
            'at' => Row::text($row, 'occurred_at'),
            'business_date' => null,
            'actor' => Row::nullable($row, 'actor'),
            'company' => null,
            'kind' => Row::text($row, 'import_kind'),
            'reason' => Row::text($row, 'row_number'),
            'href' => RecordAddress::of('import_batch', Row::text($row, 'public_id')),
        ]);
    }

    /** The DUPLICATE import rows whose committed twin lies outside their batch's company scope (DP-18). */
    private static function duplicates(): Builder
    {
        return DB::table('import_rows as r')->join('import_batches as b', 'b.id', '=', 'r.import_batch_id')->where('r.validation_state', 'DUPLICATE')
            ->whereExists(fn (Builder $twin) => $twin->selectRaw('1')->from('import_rows as t')->whereColumn('t.import_kind', 'r.import_kind')->whereColumn('t.import_identity', 'r.import_identity')
                ->where('t.validation_state', 'COMMITTED')->whereNotNull('t.company_id')->whereRaw('not (t.company_id = any (b.company_scope))'));
    }

    /**
     * @param  array<int, object>  $rows
     * @param  callable(object): array<string, mixed>  $map
     * @return array{items: list<array<string, mixed>>, more: ?string}
     */
    private static function paged(array $rows, callable $map): array
    {
        $more = count($rows) > self::PAGE;
        $rows = array_slice($rows, 0, self::PAGE);
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return ['items' => array_map($map, $rows), 'more' => $more && $last !== null ? Row::text($last, 'occurred_at').'|'.Row::text($last, 'id') : null];
    }

    /** @return ?array{string, int} */
    private static function cursor(?string $after): ?array
    {
        if ($after === null || preg_match('/^(.+)\|(\d+)$/', $after, $parts) !== 1) {
            return null;
        }

        try {
            return [CarbonImmutable::parse($parts[1])->toIso8601ZuluString(), (int) $parts[2]];
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The window's key and its UTC bounds (IA §9.2): the last seven days by default.
     *
     * @return array{string, CarbonImmutable, CarbonImmutable}
     */
    private static function window(?string $key, ?string $from, ?string $to): array
    {
        $today = BusinessDate::today();
        $day = '/^\d{4}-\d{2}-\d{2}$/';

        if ($key === 'rentang' && $from !== null && $to !== null && preg_match($day, $from) === 1 && preg_match($day, $to) === 1 && $from <= $to) {
            try {
                return ['rentang', BusinessDay::start($from), BusinessDay::end($to)];
            } catch (InvalidArgumentException) {
                // An impossible day falls back to the default window.
            }
        }

        $key = $key !== null && array_key_exists($key, self::WINDOWS) ? $key : '7_hari';
        $start = CarbonImmutable::parse($today, BusinessDate::TIMEZONE)->subDays(self::WINDOWS[$key])->toDateString();

        return [$key, BusinessDay::start($start), BusinessDay::end($today)];
    }
}
