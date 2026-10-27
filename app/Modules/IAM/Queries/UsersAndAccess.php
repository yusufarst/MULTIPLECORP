<?php

namespace App\Modules\IAM\Queries;

use App\Modules\IAM\Data\AccessPreview;
use App\Modules\IAM\Data\CapabilityAreas;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Data\SessionHandle;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\UserAgentSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * PG-060 *Pengguna & Akses*'s reads (ROUTE-060; SCR-57; IA §8.6, §14.5; PJ-17), the Owner's alone (`users.manage`,
 * `permissions.manage`; OD-01): the directory of accounts and one account's detail — its role, company grants,
 * capability grants, sessions as a device summary with start and last activity, credential links with purpose,
 * issuer, issue time, expiry and use or revocation, TOTP status and enrolment time and the Google link history — never
 * a TOTP secret, a recovery code, a provider subject, a session identifier or payload, a raw address, a hash or a token
 * (PERMISSIONS_MATRIX §7.2 rows 1–7 and 125). Companies are named by the caller from ORG's directory, as internal ids
 * here. Each read is a fixed number of queries, whatever the rows (PFM-02).
 */
final class UsersAndAccess
{
    /** The built-in roles an account may be given (RG-01). */
    public const ROLES = AccountAccess::ROLES;

    public function __construct(private readonly AccountAccess $access) {}

    /** A page of the directory (PT-05; PT-08), "Muat Lagi" adding as many again. */
    public const PAGE = 25;

    /**
     * The directory's first `$limit` accounts by name, each with its role, state, factor, link and last activity, and
     * whether more follow (PT-08) — one statement, whatever the rows (PFM-02).
     *
     * @return array{rows: list<array{id: string, name: string, email: string, role: ?string, active: bool, totp: bool, google: bool, last_activity: ?string}>, more: bool}
     */
    public function directory(int $limit = self::PAGE): array
    {
        $limit = max(self::PAGE, min(500, $limit));
        $rows = array_values(DB::table('users')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->orderBy('users.name')->orderBy('users.id')
            ->limit($limit + 1)
            ->get([
                'users.public_id', 'users.name', 'users.email', 'roles.code as role', 'users.is_active', 'users.two_factor_confirmed_at',
                DB::raw("exists (select 1 from user_external_identities x where x.user_id = users.id and x.provider = 'google' and x.unlinked_at is null) as google"),
                DB::raw('(select max(s.last_activity) from sessions s where s.user_id = users.id) as last_activity'),
            ])
            ->map(fn (object $row): array => [
                'id' => self::text($row->public_id),
                'name' => self::text($row->name),
                'email' => self::text($row->email),
                'role' => is_string($row->role) ? $row->role : null,
                'active' => $row->is_active === true,
                'totp' => $row->two_factor_confirmed_at !== null,
                'google' => $row->google === true,
                'last_activity' => is_numeric($row->last_activity) ? CarbonImmutable::createFromTimestamp((int) $row->last_activity)->toIso8601ZuluString() : null,
            ])
            ->all());

        return ['rows' => array_slice($rows, 0, $limit), 'more' => count($rows) > $limit];
    }

    /** The account a public identifier names, or none — answered as a missing page (AZ-11). */
    public function account(string $publicId): ?User
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1
            ? User::query()->where('public_id', strtolower($publicId))->first()
            : null;
    }

    /**
     * The account's active company grants, as internal ids for the caller to name.
     *
     * @return list<int>
     */
    public function companyIdsOf(User $account): array
    {
        return $this->access->grants($account->id)['companies'];
    }

    public function roleOf(User $account): ?string
    {
        return $this->access->roleOf($account->id);
    }

    /** A new account's public identifier, for the answer that opens its detail. */
    public function publicIdOf(int $userId): ?string
    {
        $publicId = DB::table('users')->where('id', $userId)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    /**
     * One account's detail, its company grants as internal ids for the caller to name.
     *
     * @return array<string, mixed>
     */
    public function detail(User $account, int $viewerId, string $viewerSessionId): array
    {
        $grants = $this->access->grants($account->id);

        return [
            'id' => $account->public_id,
            'name' => $account->name,
            'email' => $account->email,
            'lock_version' => AccountAccess::version($account),
            'role' => $this->access->roleOf($account->id),
            'active' => $account->getAttribute('is_active') === true,
            'deactivated_at' => self::instant($account->getRawOriginal('deactivated_at')),
            'self' => $account->id === $viewerId,
            'totp' => ['active' => $account->getAttribute('two_factor_confirmed_at') !== null, 'since' => self::instant($account->getRawOriginal('two_factor_confirmed_at'))],
            'capabilities' => $grants['capabilities'],
            'sessions' => $this->sessions($account->id, $viewerSessionId),
            'links' => $this->links($account->id),
            'google' => $this->google($account->id),
        ];
    }

    /**
     * The grant editor's catalogue: every capability an account may be granted, by area, with its class, its
     * prerequisites and whether it needs a company (RG-06; RG-08). OWNER_ONLY capabilities are absent (OD-02).
     *
     * @return list<array{area: string, capabilities: list<array{code: string, special: bool, prerequisites: list<string>, company: bool}>}>
     */
    public function catalogue(): array
    {
        $areas = [];

        foreach (CapabilityAreas::grantable() as $area => $codes) {
            $areas[] = ['area' => $area, 'capabilities' => array_map(fn (string $code): array => [
                'code' => $code,
                'special' => CapabilityCatalogue::classOf($code) === AuthorityClass::AdmPlus,
                'prerequisites' => CapabilityCatalogue::prerequisites($code),
                'company' => CapabilityCatalogue::needsCompany($code),
            ], $codes)];
        }

        return $areas;
    }

    /**
     * "Pratinjau Akses" of the account's grants as the editor leaves them (PT-30), companies keyed as the caller names
     * them.
     *
     * @param  list<string>  $companiesBefore
     * @param  list<string>  $capabilities
     * @param  list<string>  $companies
     * @return array<string, mixed>
     */
    public function preview(User $account, array $companiesBefore, string $role, array $capabilities, array $companies): array
    {
        if (AccountAccess::isOwnerRole($role)) {
            return ['owner' => true, 'companies' => [], 'rows' => [], 'summaries' => [], 'changes' => null];
        }

        $preview = new AccessPreview($this->access->grants($account->id)['capabilities'], $companiesBefore, $capabilities, $companies);

        return [
            'owner' => false,
            'companies' => $preview->companies(),
            'rows' => $preview->rows(),
            'summaries' => $preview->summaries(),
            'changes' => $preview->changes(),
            'missing' => $preview->addedWithoutPrerequisite(),
        ];
    }

    /**
     * The account's sessions as device summaries (AU-10; PJ-17): the handle, the device, the sign-in time read from
     * the session on the server and its last activity — never its identifier, payload or address.
     *
     * @return list<array{handle: string, device: ?string, started_at: ?string, last_activity: ?string, current: bool}>
     */
    private function sessions(int $userId, string $viewerSessionId): array
    {
        return array_values(DB::table('sessions')->where('user_id', $userId)->orderByDesc('last_activity')->get(['id', 'user_agent', 'payload', 'last_activity'])
            ->map(fn (object $session): array => [
                'handle' => SessionHandle::of(self::text($session->id)),
                'device' => UserAgentSummary::of(is_string($session->user_agent) ? $session->user_agent : null),
                'started_at' => self::signedInAt(is_string($session->payload) ? $session->payload : ''),
                'last_activity' => is_numeric($session->last_activity) ? CarbonImmutable::createFromTimestamp((int) $session->last_activity)->toIso8601ZuluString() : null,
                'current' => $session->id === $viewerSessionId,
            ])
            ->all());
    }

    /**
     * The account's credential links, newest first (PJ-17): purpose, issuer — the operator's console for an
     * OWNER_RECOVERY link, which records none —, issue time, expiry and use or revocation.
     *
     * @return list<array{purpose: string, issuer: ?string, console: bool, issued_at: ?string, expires_at: ?string, state: string, at: ?string}>
     */
    private function links(int $userId): array
    {
        return array_values(DB::table('credential_tokens')->leftJoin('users as issuers', 'issuers.id', '=', 'credential_tokens.issued_by')
            ->where('credential_tokens.user_id', $userId)->orderByDesc('credential_tokens.issued_at')->orderByDesc('credential_tokens.id')->limit(20)
            ->get(['credential_tokens.purpose', 'issuers.name as issuer', 'credential_tokens.issued_at', 'credential_tokens.expires_at', 'credential_tokens.used_at', 'credential_tokens.revoked_at'])
            ->map(function (object $link): array {
                $expires = self::instant($link->expires_at);
                $state = match (true) {
                    $link->used_at !== null => 'used',
                    $link->revoked_at !== null => 'revoked',
                    $expires !== null && CarbonImmutable::parse($expires)->isPast() => 'expired',
                    default => 'open',
                };

                return [
                    'purpose' => self::text($link->purpose),
                    'issuer' => is_string($link->issuer) ? $link->issuer : null,
                    'console' => $link->purpose === 'OWNER_RECOVERY',
                    'issued_at' => self::instant($link->issued_at),
                    'expires_at' => $expires,
                    'state' => $state,
                    'at' => self::instant($link->used_at ?? $link->revoked_at),
                ];
            })
            ->all());
    }

    /**
     * The account's Google link history (PJ-17; H7-17): the e-mail at link time, the link and unlink dates, the unlink
     * reason and the account that unlinked — never the provider subject.
     *
     * @return list<array{email: ?string, linked_at: ?string, unlinked_at: ?string, reason: ?string, by: ?string}>
     */
    private function google(int $userId): array
    {
        return array_values(DB::table('user_external_identities')->leftJoin('users as unlinkers', 'unlinkers.id', '=', 'user_external_identities.unlinked_by')
            ->where('user_external_identities.user_id', $userId)->where('user_external_identities.provider', 'google')
            ->orderByDesc('user_external_identities.linked_at')->orderByDesc('user_external_identities.id')
            ->get(['user_external_identities.email_at_link', 'user_external_identities.linked_at', 'user_external_identities.unlinked_at', 'user_external_identities.unlink_reason', 'unlinkers.name as by'])
            ->map(fn (object $row): array => [
                'email' => is_string($row->email_at_link) ? $row->email_at_link : null,
                'linked_at' => self::instant($row->linked_at),
                'unlinked_at' => self::instant($row->unlinked_at),
                'reason' => is_string($row->unlink_reason) ? $row->unlink_reason : null,
                'by' => is_string($row->by) ? $row->by : null,
            ])
            ->all());
    }

    /** The sign-in time a session keeps (SessionDeadline::SIGNED_IN_AT), read on the server only. */
    private static function signedInAt(string $payload): ?string
    {
        try {
            $data = base64_decode($payload, true);

            if ($data === false) {
                return null;
            }

            $decrypted = config('session.encrypt') === true ? Crypt::decrypt($data) : $data;
            $attributes = is_string($decrypted) ? unserialize($decrypted, ['allowed_classes' => false]) : null;
            $signedIn = is_array($attributes) && is_array($attributes['auth'] ?? null) ? ($attributes['auth']['signed_in_at'] ?? null) : null;

            return is_numeric($signedIn) ? CarbonImmutable::createFromTimestamp((int) $signedIn)->toIso8601ZuluString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function instant(mixed $value): ?string
    {
        return is_string($value) || $value instanceof \DateTimeInterface ? CarbonImmutable::parse($value)->toIso8601ZuluString() : null;
    }
}
