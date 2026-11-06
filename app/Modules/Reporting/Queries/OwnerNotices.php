<?php

namespace App\Modules\Reporting\Queries;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The Owner's security notices heading *Beranda*'s G1 (IA §9; SECURITY AU-12, AU-13, LG-07; INT-020): a credential
 * link the Owner issued that was used — MSG-29's Owner variant with the account and the time —, and the LG-07 alerts
 * that are single security events — an OWNER_RECOVERY_LINK_ISSUED, an AUTHORIZATION_ANOMALY, a successful Owner
 * sign-in from an address not seen for it in 30 days —, each as its neutral type, its time and a link to PG-064, or
 * to PG-060 for the account a link was used for. Derived by one bounded statement over the security events recorded
 * since the Owner's previous sign-in (PFM-15), so nothing is marked read; older ones stay on PG-064. No Admin page has
 * them.
 */
final class OwnerNotices
{
    public const LIMIT = 10;

    /**
     * @return list<array{key: string, kind: string, at: string, account: ?string, href: string}>
     */
    public function since(int $ownerId, string $since): array
    {
        $select = fn (Builder $query, string $kind): Builder => $query->selectRaw('e.id, e.occurred_at, cast(? as text) as kind, u.name as account, u.public_id::text as account_id', [$kind]);

        $used = $select(DB::table('security_events as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->whereIn('e.event_kind', ['ACCOUNT_SETUP_COMPLETED', 'PASSWORD_RESET_COMPLETED'])->where('e.occurred_at', '>', $since)
            ->whereExists(fn (Builder $token) => $token->selectRaw('1')->from('credential_tokens as t')->whereColumn('t.user_id', 'e.user_id')->where('t.issued_by', $ownerId)->whereNotNull('t.used_at')), 'LINK_USED');
        $alerts = DB::table('security_events as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->whereIn('e.event_kind', ['OWNER_RECOVERY_LINK_ISSUED', 'AUTHORIZATION_ANOMALY'])->where('e.occurred_at', '>', $since)
            ->selectRaw('e.id, e.occurred_at, e.event_kind as kind, u.name as account, u.public_id::text as account_id');
        $address = $select(DB::table('security_events as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.event_kind', 'LOGIN_SUCCEEDED')->where('e.user_id', $ownerId)->where('e.occurred_at', '>', $since)->whereNotNull('e.ip_hash')
            ->whereNotExists(fn (Builder $seen) => $seen->selectRaw('1')->from('security_events as o')->whereColumn('o.user_id', 'e.user_id')->where('o.event_kind', 'LOGIN_SUCCEEDED')
                ->whereColumn('o.ip_hash', 'e.ip_hash')->whereColumn('o.occurred_at', '<', 'e.occurred_at')->whereRaw("o.occurred_at >= e.occurred_at - interval '30 days'")), 'NEW_ADDRESS');

        $rows = DB::query()->fromSub($used->unionAll($alerts)->unionAll($address), 'n')->orderByDesc('n.occurred_at')->orderByDesc('n.id')->limit(self::LIMIT)->get()->all();

        return array_values(array_map(fn (object $row): array => [
            'key' => 'n'.Row::text($row, 'id'),
            'kind' => Row::text($row, 'kind'),
            'at' => Row::text($row, 'occurred_at'),
            'account' => Row::nullable($row, 'account'),
            'href' => Row::text($row, 'kind') === 'LINK_USED' && Row::nullable($row, 'account_id') !== null
                ? '/pengaturan/pengguna?akun='.Row::text($row, 'account_id')
                : '/aktivitas/log-keamanan?asal=beranda',
        ], $rows));
    }
}
