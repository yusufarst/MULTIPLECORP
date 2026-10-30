<?php

namespace App\Modules\OPS\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Redaction;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PG-063 *Riwayat Aktivitas* (ROUTE-063; `OPS ShowActivityHistory`; BR-XC-01; LG-01; IA §8.6): the business audit of the
 * whole workspace under `audit.view` (OWNER_ONLY), newest first in keyset order — one bounded branch per company of the
 * scope and one for the events without a company, merged by (`occurred_at`, `id`) in one statement on IX-13's
 * (`company_id`, `occurred_at`, `id`) (PF-13; PF-41). The period narrows every branch, a company the branches; an actor
 * reads the actor's own index and a record its timeline's (IX-13), each with the other filters as predicates (PF-42).
 * A row carries the narrow columns only — the time and business date, the actor or the operator's recorded identity,
 * the company, the action, the record, the reason and the authority class —, an event's before, after and metadata
 * read on demand for that one event (PF-06; PJ-11). "Muat Lagi" continues from a signed cursor, no total (PF-14).
 */
final class ActivityHistory
{
    public const PAGE = 50;

    /** The company filter's value for the events without a company (*Tanpa perusahaan*). */
    public const NO_COMPANY = 'tanpa';

    /** The records a filter or a link names by their public identifier, and the page each opens (IA §11). */
    private const RECORDS = [
        'project' => ['table' => 'projects', 'href' => '/proyek/{id}'],
        'user' => ['table' => 'users', 'href' => '/pengaturan/pengguna?akun={id}'],
        'company' => ['table' => 'companies', 'href' => '/pengaturan/perusahaan'],
        'product' => ['table' => 'products', 'href' => '/data-master/produk?produk={id}'],
        'client_organization' => ['table' => 'client_organizations', 'href' => '/data-master/klien?klien={id}'],
        'supplier' => ['table' => 'suppliers', 'href' => '/data-master/pemasok?pemasok={id}'],
        'evidence_document' => ['table' => 'evidence_documents', 'href' => '/dokumen/bukti?bukti={id}'],
        'invoice' => ['table' => 'invoices', 'href' => '/keuangan/invoice/{id}'],
    ];

    /** The rows one bounded read of a timeline narrowed by company takes before its page returns short (PF-42). */
    public const SCAN = 1000;

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * @param  array{dari: ?string, sampai: ?string, perusahaan: ?string, pelaku: ?string, catatan: ?string, setelah: ?string}  $query
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, array $query): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'code', 'legal_name']) as $company) {
            $companies[] = ['internal' => (int) Row::text($company, 'id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name')];
        }

        $from = self::day($query['dari']);
        $to = self::day($query['sampai']);
        $company = $query['perusahaan'];
        $chosen = match (true) {
            $company === null => [...array_column($companies, 'internal'), null],
            $company === self::NO_COMPANY => [null],
            default => array_column(array_filter($companies, fn (array $row): bool => $row['code'] === $company), 'internal'),
        };
        $actor = self::actor($query['pelaku']);
        $record = self::record($query['catatan']);
        $filters = ['dari' => $from, 'sampai' => $to, 'perusahaan' => $company, 'pelaku' => $query['pelaku'], 'catatan' => $query['catatan']];
        $base = [
            'companies' => array_map(fn (array $row): array => ['code' => $row['code'], 'name' => $row['name']], $companies),
            'actors' => DB::table('users')->orderBy('name')->limit(500)->get(['public_id', 'name'])
                ->map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name')])->values()->all(),
            'filters' => $filters,
            'record' => $record === null ? null : ['type' => $record['type'], 'id' => $record['public_id']],
        ];

        try {
            $after = $query['setelah'] === null ? null : LogCursor::read($query['setelah']);
        } catch (InvalidArgumentException) {
            return [...$base, 'rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        // A filter naming no actor, record or company of the scope reads nothing (SM-01).
        $empty = ($query['pelaku'] !== null && $actor === null) || ($query['catatan'] !== null && $record === null) || $chosen === [];
        $page = $empty ? ['rows' => [], 'short' => null] : $this->page($chosen, $company !== null, $from, $to, $actor, $record, $after);
        $rows = array_map(fn (object $row): array => $this->row($row), array_slice($page['rows'], 0, self::PAGE));
        $more = count($page['rows']) > self::PAGE && $rows !== [] ? $rows[count($rows) - 1]['key'] : null;

        return [...$base, 'rows' => $rows, 'more' => $page['short'] === null ? $more : LogCursor::of(...$page['short']), 'partial' => $page['short'] !== null, 'cursor_failed' => false];
    }

    /**
     * One event's before, after and metadata, shown on demand (PF-06; PJ-11) — the Owner sees every key a user may see,
     * nothing LG-03 keeps out. Null for a key this page did not hand out.
     *
     * @return ?array{key: string, before: array<string, string>, after: array<string, string>, metadata: array<string, string>}
     */
    public function detail(string $key): ?array
    {
        try {
            [$occurredAt, $id] = LogCursor::read($key);
        } catch (InvalidArgumentException) {
            return null;
        }

        $row = DB::table('audit_events')->where('id', $id)->whereRaw('occurred_at = cast(? as timestamptz)', [$occurredAt])->first(['before', 'after', 'metadata']);

        return is_object($row) ? [
            'key' => $key,
            'before' => self::values(Row::nullable($row, 'before')),
            'after' => self::values(Row::nullable($row, 'after')),
            'metadata' => self::values(Row::nullable($row, 'metadata')),
        ] : null;
    }

    /**
     * The merged branches' page of narrow columns, page size + 1 rows (PF-41), and — for a timeline narrowed by company —
     * the position its bounded read stopped at when it returns short (PF-42).
     *
     * @param  list<?int>  $companies
     * @param  ?array{type: string, public_id: string, id: int}  $record
     * @param  ?array{string, int}  $after
     * @return array{rows: list<object>, short: ?array{string, int}}
     */
    private function page(array $companies, bool $narrowed, ?string $from, ?string $to, ?int $actor, ?array $record, ?array $after): array
    {
        $filtered = fn (Builder $query, int $limit = self::PAGE + 1): Builder => $query
            ->when($from !== null, fn (Builder $where) => $where->where('occurred_at', '>=', BusinessDay::start((string) $from)))
            ->when($to !== null, fn (Builder $where) => $where->where('occurred_at', '<', BusinessDay::end((string) $to)))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(occurred_at, id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit);
        $short = null;

        if ($record !== null || $actor !== null) {
            // One timeline: the record's (IX-13) or the actor's (DATABASE §20), the period a range of its index.
            $timeline = DB::table('audit_events')
                ->when($record !== null, fn (Builder $where) => $where->where('entity_type', $record['type'] ?? '')->where('entity_id', $record['id'] ?? 0))
                ->when($actor !== null, fn (Builder $where) => $where->where('actor_user_id', $actor));

            if ($narrowed) {
                // A company is no key of these indexes: one read takes at most SCAN rows and the page returns short, with
                // "more" from the last row read, rather than reading the whole timeline (PF-42).
                $scanned = $filtered($timeline->select(['id', 'occurred_at', 'company_id']), self::SCAN)->get()->all();
                $wanted = array_values(array_filter($scanned, fn (object $row): bool => in_array(Row::nullable($row, 'company_id') === null ? null : (int) Row::text($row, 'company_id'), $companies, true)));
                $last = $scanned === [] ? null : $scanned[count($scanned) - 1];
                $short = count($wanted) <= self::PAGE && count($scanned) === self::SCAN && $last !== null ? [Row::text($last, 'occurred_at'), (int) Row::text($last, 'id')] : null;
                $branches = DB::table('audit_events')->select('id')->whereIn('id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), array_slice($wanted, 0, self::PAGE + 1)) ?: [0]);
            } else {
                $branches = $filtered($timeline->select('id'));
            }
        } else {
            $branch = fn (?int $company): Builder => $filtered(DB::table('audit_events')->select('id')
                ->when($company === null, fn (Builder $where) => $where->whereNull('company_id'), fn (Builder $where) => $where->where('company_id', $company)));
            $branches = $branch($companies[0]);

            foreach (array_slice($companies, 1) as $company) {
                $branches->unionAll($branch($company));
            }
        }

        $rows = array_values(DB::table('audit_events as a')->joinSub($branches, 'b', 'b.id', '=', 'a.id')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->orderByDesc('a.occurred_at')->orderByDesc('a.id')->limit(self::PAGE + 1)
            ->get(['a.id', 'a.occurred_at', 'a.action', 'a.entity_type', 'a.entity_public_id', 'a.reason', 'a.authority_class', 'u.name as actor', 'c.code as company',
                DB::raw("a.metadata->>'business_date' as business_date"), DB::raw("a.metadata->'operator' as operator")])->all());

        return ['rows' => $rows, 'short' => $short];
    }

    /**
     * The operator's recorded identity of a console command (AU-14): the stated name with the host account, as
     * `OperatorIdentity::describe()` writes it; a plain string as recorded.
     */
    private static function operator(?string $json): ?string
    {
        $value = $json === null ? null : json_decode($json, true);
        $name = is_array($value) ? ($value['name'] ?? $value['stated_name'] ?? null) : $value;
        $host = is_array($value) ? ($value['host_account'] ?? null) : null;

        return ! is_string($name) || $name === '' ? null : (is_string($host) && $host !== '' ? "{$name} ({$host})" : $name);
    }

    /** @return array<string, mixed> */
    private function row(object $row): array
    {
        $occurredAt = Row::text($row, 'occurred_at');
        $at = CarbonImmutable::parse($occurredAt);
        $businessDate = Row::nullable($row, 'business_date');
        $type = Row::nullable($row, 'entity_type');
        $publicId = Row::nullable($row, 'entity_public_id');
        $target = $type !== null && $publicId !== null ? (self::RECORDS[$type] ?? null) : null;

        return [
            'key' => LogCursor::of($occurredAt, (int) Row::text($row, 'id')),
            'at' => $at->toIso8601String(),
            // A backdated event shows its business date beside its recorded time (BR-DT-05; PT-18).
            'business_date' => $businessDate !== null && $businessDate !== $at->setTimezone(BusinessDate::TIMEZONE)->toDateString() ? $businessDate : null,
            'actor' => Row::nullable($row, 'actor'),
            'operator' => Row::nullable($row, 'actor') === null ? self::operator(Row::nullable($row, 'operator')) : null,
            'company' => Row::nullable($row, 'company'),
            'action' => Row::text($row, 'action'),
            'record' => $type === null ? null : [
                'type' => $type,
                'href' => $target === null ? null : str_replace('{id}', (string) $publicId, $target['href']),
                'filter' => $target === null ? null : "{$type}:{$publicId}",
            ],
            'reason' => Row::nullable($row, 'reason'),
            'authority' => Row::text($row, 'authority_class'),
        ];
    }

    private static function day(?string $value): ?string
    {
        try {
            return $value === null ? null : BusinessDay::start($value)->setTimezone(BusinessDate::TIMEZONE)->toDateString();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function actor(?string $publicId): ?int
    {
        $id = $publicId !== null && preg_match('/^[0-9a-f-]{36}$/', $publicId) === 1 ? DB::table('users')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** @return ?array{type: string, public_id: string, id: int} */
    private static function record(?string $value): ?array
    {
        $parts = $value === null ? [] : explode(':', $value, 2);

        if (count($parts) !== 2 || ! isset(self::RECORDS[$parts[0]]) || preg_match('/^[0-9a-f-]{36}$/', $parts[1]) !== 1) {
            return null;
        }

        $id = DB::table(self::RECORDS[$parts[0]]['table'])->where('public_id', strtolower($parts[1]))->value('id');

        return is_numeric($id) ? ['type' => $parts[0], 'public_id' => strtolower($parts[1]), 'id' => (int) $id] : null;
    }

    /**
     * A JSON object's keys as display strings, nested values as JSON, LG-03's forbidden keys dropped.
     *
     * @return array<string, string>
     */
    private static function values(?string $json): array
    {
        $decoded = $json === null ? null : json_decode($json, true);
        $values = [];

        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            // The keys the application's redaction treats as secret never show, should one ever be recorded (LG-03).
            if (Redaction::isSensitiveKey($key)) {
                continue;
            }

            $values[(string) $key] = match (true) {
                $value === null => '—',
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            };
        }

        return $values;
    }
}
