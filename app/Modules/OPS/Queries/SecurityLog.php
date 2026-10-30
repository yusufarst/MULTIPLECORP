<?php

namespace App\Modules\OPS\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use App\Support\SecurityLog\SecurityEventKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PG-064 *Log Keamanan* (ROUTE-064; `OPS ShowSecurityLog`; LG-02; IA §8.6): the security events under
 * `security_log.view` (OWNER_ONLY), newest first in keyset order on their own time keys — every kind on
 * (`occurred_at`, `id`), one kind on (`event_kind`, `occurred_at`, `id`) —, filtered by kind, period and company: only
 * the two TECH-022 kinds carry a company, on IX-14's index (PF-13; PF-42). Each event shows what LG-02 records — kind,
 * time, the account concerned and the acting account when different, the reason code, the route and target, the
 * identifier and the address as their keyed hashes only, the user-agent summary and the whitelisted details —, never a
 * secret, token, session or raw address (LG-03; PJ-21). A security notice opens its event here (INT-020).
 */
final class SecurityLog
{
    public const PAGE = 50;

    private const COMPANY_KINDS = ['EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED'];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * @param  array{jenis: ?string, dari: ?string, sampai: ?string, perusahaan: ?string, setelah: ?string, peristiwa: ?string}  $query
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, array $query): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'code', 'legal_name']) as $company) {
            $companies[] = ['internal' => (int) Row::text($company, 'id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name')];
        }

        $kinds = array_map(fn (SecurityEventKind $kind): string => $kind->value, SecurityEventKind::cases());
        $kind = in_array($query['jenis'], $kinds, true) ? $query['jenis'] : null;
        $from = self::day($query['dari']);
        $to = self::day($query['sampai']);
        $company = $query['perusahaan'] === null ? null : (array_values(array_filter($companies, fn (array $row): bool => $row['code'] === $query['perusahaan']))[0]['internal'] ?? 0);
        $base = [
            'kinds' => $kinds,
            'companies' => array_map(fn (array $row): array => ['code' => $row['code'], 'name' => $row['name']], $companies),
            'filters' => ['jenis' => $kind, 'dari' => $from, 'sampai' => $to, 'perusahaan' => $query['perusahaan']],
            // The event a security notice opens (INT-020), above the list; null for a key this page did not hand out.
            'focus' => $query['peristiwa'] === null ? null : $this->focus($query['peristiwa']),
        ];

        try {
            $after = $query['setelah'] === null ? null : LogCursor::read($query['setelah']);
        } catch (InvalidArgumentException) {
            return [...$base, 'rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $page = $this->query()
            ->when($kind !== null, fn (Builder $where) => $where->where('e.event_kind', $kind))
            ->when($company !== null, fn (Builder $where) => $where->whereIn('e.event_kind', self::COMPANY_KINDS)
                ->where(fn (Builder $either) => $either->where('e.company_id', $company)->orWhere('e.related_company_id', $company)))
            ->when($from !== null, fn (Builder $where) => $where->where('e.occurred_at', '>=', BusinessDay::start((string) $from)))
            ->when($to !== null, fn (Builder $where) => $where->where('e.occurred_at', '<', BusinessDay::end((string) $to)))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(e.occurred_at, e.id) < (cast(? as timestamptz), ?)', $after ?? []))
            ->orderByDesc('e.occurred_at')->orderByDesc('e.id')->limit(self::PAGE + 1)->get()->all();
        $rows = array_map(fn (object $row): array => self::row($row), array_slice($page, 0, self::PAGE));

        return [...$base, 'rows' => $rows, 'more' => count($page) > self::PAGE && $rows !== [] ? $rows[count($rows) - 1]['key'] : null, 'cursor_failed' => false];
    }

    /** @return ?array<string, mixed> */
    private function focus(string $key): ?array
    {
        try {
            [$occurredAt, $id] = LogCursor::read($key);
        } catch (InvalidArgumentException) {
            return null;
        }

        $row = $this->query()->where('e.id', $id)->whereRaw('e.occurred_at = cast(? as timestamptz)', [$occurredAt])->first();

        return is_object($row) ? self::row($row) : null;
    }

    /** The narrow columns of LG-02 with the accounts' names and the companies' codes — never a column LG-03 forbids. */
    private function query(): Builder
    {
        return DB::table('security_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('users as a', 'a.id', '=', 'e.actor_user_id')
            ->leftJoin('companies as c', 'c.id', '=', 'e.company_id')->leftJoin('companies as r', 'r.id', '=', 'e.related_company_id')
            ->select(['e.id', 'e.occurred_at', 'e.event_kind', 'e.user_id', 'e.actor_user_id', 'e.reason_code', 'e.route_name', 'e.target_public_id', 'e.target_entity_type',
                'e.identifier_hash', 'e.ip_hash', 'e.user_agent_summary', 'e.details', 'u.name as account', 'a.name as actor', 'c.code as company', 'r.code as related_company']);
    }

    /** @return array<string, mixed> */
    private static function row(object $row): array
    {
        $occurredAt = Row::text($row, 'occurred_at');
        $details = json_decode(Row::nullable($row, 'details') ?? 'null', true);
        $actorDiffers = Row::nullable($row, 'actor_user_id') !== null && Row::nullable($row, 'actor_user_id') !== Row::nullable($row, 'user_id');

        return [
            'key' => LogCursor::of($occurredAt, (int) Row::text($row, 'id')),
            'at' => CarbonImmutable::parse($occurredAt)->toIso8601String(),
            'kind' => Row::text($row, 'event_kind'),
            'account' => Row::nullable($row, 'account'),
            'actor' => $actorDiffers ? Row::nullable($row, 'actor') : null,
            'reason' => Row::nullable($row, 'reason_code'),
            'route' => Row::nullable($row, 'route_name'),
            'target' => Row::nullable($row, 'target_public_id') === null ? null : ['type' => Row::nullable($row, 'target_entity_type'), 'id' => Row::text($row, 'target_public_id')],
            // The keyed hashes only (LG-03): enough to match two events, never the identifier or the address.
            'identifier' => self::short(Row::nullable($row, 'identifier_hash')),
            'address' => self::short(Row::nullable($row, 'ip_hash')),
            'agent' => Row::nullable($row, 'user_agent_summary'),
            'companies' => array_values(array_filter([Row::nullable($row, 'company'), Row::nullable($row, 'related_company')])),
            'details' => is_array($details) ? array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : (string) json_encode($value), $details) : [],
        ];
    }

    private static function short(?string $hash): ?string
    {
        return $hash === null ? null : substr($hash, 0, 12);
    }

    private static function day(?string $value): ?string
    {
        try {
            return $value === null ? null : BusinessDay::start($value)->setTimezone(BusinessDate::TIMEZONE)->toDateString();
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
