<?php

namespace App\Modules\IAM\Services;

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Enums\BuiltinRole;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\AccountHistory;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\TransactionClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The steps PG-060's NX-02 commands share (CONCURRENCY_IDEMPOTENCY §16; SECURITY AU-09, AU-11, AU-18, AU-20 c;
 * PERMISSIONS_MATRIX RG-09, RG-10), run inside a command's transaction under the locks its context took — the
 * Owner set (LK-02) where a role or an active state of an OWNER-role account moves, then the actor's and the target's
 * rows (LK-03) in id order. The credential-token and session statements are a transaction's last (LR-02): a link is
 * locked by the statement that writes it (LK-04), and the sessions are deleted by `user_id` under LK-03 (LK-05).
 */
final class AccountAccess
{
    /** RG-01's two built-in roles, the one each account holds. */
    public const ROLES = [BuiltinRole::Owner->value, BuiltinRole::AdminOperasional->value];

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AccountHistory $history,
        private readonly SecurityEventRecorder $events,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * An NX-02 command through the envelope: TX-09, no company (the Owner's all-company scope; RC-03), its fingerprint
     * over the target's public id and the typed payload (CI-02).
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(CommandContext): CommandResult  $action
     * @param  (Closure(Outcome): (array<string, mixed>|null))|null  $replayState
     */
    public function run(string $commandId, string $type, int $actorUserId, string $capability, ?string $target, array $payload, Closure $action, ?Closure $replayState = null): Outcome
    {
        $request = new CommandRequest($commandId, $type, $actorUserId, null, $capability, Fingerprint::of($type, null, ['target' => $target, ...$payload]), TransactionClass::AccessChange);

        return $this->envelope->run($request, $action, replayState: $replayState);
    }

    /**
     * A security event after the command's commit (LG-02), the acting Owner as its actor, and LG-07's alert when given —
     * work after the commit whose failure never changes the outcome (SQ-21).
     */
    public function announce(CommandContext $context, SecurityEventKind $kind, int $userId, ?string $reasonCode = null, ?string $alert = null): void
    {
        $actor = $context->request->actorUserId;
        $context->afterCommit(function () use ($kind, $userId, $actor, $reasonCode, $alert): void {
            $this->events->record($kind, userId: $userId, actorUserId: $actor, reasonCode: $reasonCode);

            if ($alert !== null) {
                Log::alert($alert, ['user_id' => $userId, 'actor_user_id' => $actor]);
            }
        });
    }

    /**
     * A link's issue in the command's transaction: its business audit event — the link's identifier, purpose and
     * expiry, never the token (LG-03) — and, after the commit, ACCOUNT_SETUP_LINK_ISSUED for an ONBOARDING link or
     * PASSWORD_RESET_REQUESTED for a RESET one, with its reason (AU-12; LG-02).
     *
     * @param  array{token: string, id: int, expires_at: CarbonImmutable}  $link
     */
    public function recordLink(CommandContext $context, User $row, array $link, string $purpose, ?string $reasonCode, ?string $reason = null): void
    {
        $this->audit->record(
            action: 'IAM.CredentialLinkIssued',
            authorityClass: AuthorityClass::OwnerOnly,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'user',
            entityId: $row->id,
            entityPublicId: $row->public_id,
            reason: $reason ?? $reasonCode,
            commandId: $context->request->commandId,
            metadata: ['credential_link' => $link['id'], 'purpose' => $purpose, 'expires_at' => $link['expires_at']->toIso8601ZuluString()],
        );
        $this->announce($context, $purpose === 'ONBOARDING' ? SecurityEventKind::AccountSetupLinkIssued : SecurityEventKind::PasswordResetRequested, $row->id, $reasonCode);
    }

    /** The target's row at LK-03 beside the actor's, and the Owner set at LK-02 first when the command moves it (RV-04). */
    public function locks(int $targetUserId, bool $ownerSet): LockSet
    {
        $locks = (new LockSet)->add('users', ['id' => $targetUserId]);

        return $ownerSet ? $locks->add('roles', ['id' => $this->ownerRoleId()]) : $locks;
    }

    /** The Owner set's lock, for a re-derivation that found the target moving it (§5; RV-04). */
    public function ownerSet(): LockSet
    {
        return (new LockSet)->add('roles', ['id' => $this->ownerRoleId()]);
    }

    /** The built-in ADMIN_OPERASIONAL role row's id, a new account's role (RG-04). */
    public function adminRoleId(): int
    {
        $id = DB::table('roles')->where('code', BuiltinRole::AdminOperasional->value)->where('is_builtin', true)->value('id');

        return is_numeric($id) ? (int) $id : throw new LogicException('The built-in roles are missing: run authorization:sync-catalogue first.');
    }

    /** The built-in OWNER role row's id, the key of LK-02. */
    public function ownerRoleId(): int
    {
        return $this->history->ownerRoleId();
    }

    /** Whether the account holds the built-in OWNER role, as committed. */
    public function holdsOwnerRole(int $userId): bool
    {
        return DB::table('user_roles')->where('user_id', $userId)->where('role_id', $this->ownerRoleId())->exists();
    }

    /** The active accounts holding the OWNER role but the one given — RG-09's count, read under LK-02 (RV-04). */
    public function activeOwnersBesides(int $userId): int
    {
        return DB::table('user_roles')->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role_id', $this->ownerRoleId())->where('users.is_active', true)->where('users.id', '!=', $userId)->count();
    }

    /** The account's role code, as committed. */
    public function roleOf(int $userId): ?string
    {
        $code = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_roles.user_id', $userId)->value('roles.code');

        return is_string($code) ? $code : null;
    }

    /**
     * The account's image under the keys TASK-020's reading of the audit trail uses (AU-25; DIR-097 R1).
     *
     * @return array<string, mixed>
     */
    public function image(int $userId): array
    {
        $current = $this->history->current($userId);

        return AccountHistory::image($current['role'], $current['granted_by'], $current['granted_at'], $current['active']);
    }

    /**
     * The account's active capability and company grants.
     *
     * @return array{capabilities: list<string>, companies: list<int>}
     */
    public function grants(int $userId): array
    {
        $capabilities = DB::table('user_capability_grants')->join('capabilities', 'capabilities.id', '=', 'user_capability_grants.capability_id')
            ->where('user_capability_grants.user_id', $userId)->whereNull('user_capability_grants.revoked_at')->orderBy('capabilities.code')->pluck('capabilities.code')
            ->filter(fn (mixed $code): bool => is_string($code))->values()->all();
        $companies = DB::table('user_company_grants')->where('user_id', $userId)->whereNull('revoked_at')->orderBy('company_id')->pluck('company_id')
            ->map(fn (mixed $id): int => self::integer($id))->all();

        /** @var list<string> $capabilities */
        return ['capabilities' => $capabilities, 'companies' => array_values($companies)];
    }

    /** The account row's `lock_version` (IP-03). */
    public static function version(User $row): int
    {
        return self::integer($row->getAttribute('lock_version'));
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * RG-10: the account's active grants revoked by the acting Owner; answers what was revoked.
     *
     * @return array{capabilities: list<string>, companies: list<int>}
     */
    public function revokeGrants(int $userId, int $by, CarbonImmutable $now): array
    {
        $revoked = $this->grants($userId);
        DB::table('user_capability_grants')->where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => $now, 'revoked_by' => $by]);
        DB::table('user_company_grants')->where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => $now, 'revoked_by' => $by]);

        return $revoked;
    }

    /**
     * RV-08: the account's export requests that its grants no longer cover marked WITHHELD in the change's own
     * transaction — PENDING, RUNNING or READY and not expired, whose recorded company set, capability set or masking
     * context the account's authority, read again under its row, lacks. The masking context's form is TASK-060's; an
     * entry naming a capability must still be held. Answers how many were withheld.
     */
    public function withholdExports(int $userId, CarbonImmutable $now): int
    {
        $authority = $this->capabilities->fresh($userId);
        $requests = DB::table('export_requests')->where('requested_by', $userId)->whereIn('state', ['PENDING', 'RUNNING', 'READY'])
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->get(['id', 'company_ids', 'capability_codes', 'masking_context']);
        $withheld = [];

        foreach ($requests as $request) {
            if (! $this->covers($authority, self::array($request->company_ids), self::array($request->capability_codes), self::array($request->masking_context))) {
                $withheld[] = $request->id;
            }
        }

        return $withheld === [] ? 0 : DB::table('export_requests')->whereIn('id', $withheld)->whereIn('state', ['PENDING', 'RUNNING', 'READY'])->update(['state' => 'WITHHELD']);
    }

    /** AU-18's test on the account's effective capabilities as the change leaves them, read under its row (RV-11). */
    public function highRisk(int $userId): bool
    {
        $this->capabilities->fresh($userId);

        return $this->capabilities->requiresTotp($userId);
    }

    /**
     * AU-18's or AU-20 c's reissue (RV-11; RV-15): the password made unusable — the hash computed before the
     * transaction —, every unused link revoked and one RESET link issued, the token statements last but the sessions
     * (LR-02). The TOTP fields are cleared in the same statement when `$clearTotp` (C-62). Answers the link.
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable}
     */
    public function reissue(User $row, int $by, string $unusableHash, CarbonImmutable $now, bool $clearTotp): array
    {
        $this->makeUnusable($row, $unusableHash, $now, $clearTotp);

        return $this->issueLink($row->id, 'RESET', $by, $now, revokeAll: true);
    }

    /** The password made unusable — and the four TOTP fields cleared with it in one statement when asked (C-62). */
    public function makeUnusable(User $row, string $unusableHash, CarbonImmutable $now, bool $clearTotp): void
    {
        DB::table('users')->where('id', $row->id)->update(['password' => $unusableHash, ...($clearTotp ? CredentialLinks::CLEARED_TOTP : []), 'updated_at' => $now]);
    }

    /**
     * A credential link: the account's open links revoked first — every unused one, or the one of the same purpose
     * (AU-11's reissue) —, then the new one inserted, a token of 256 bits kept only as its keyed hash (AU-11).
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable}
     */
    public function issueLink(int $userId, string $purpose, int $by, CarbonImmutable $now, bool $revokeAll = false): array
    {
        DB::table('credential_tokens')->where('user_id', $userId)->whereNull('used_at')->whereNull('revoked_at')
            ->when(! $revokeAll, fn ($query) => $query->where('purpose', $purpose))->update(['revoked_at' => $now]);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = $now->addMinutes(CredentialLinks::LIFETIMES[$purpose]);
        $id = DB::table('credential_tokens')->insertGetId([
            'user_id' => $userId, 'purpose' => $purpose, 'issued_by' => $by, 'token_hash' => CredentialLinks::hash($token), 'issued_at' => $now, 'expires_at' => $expires,
        ]);

        return ['token' => $token, 'id' => $id, 'expires_at' => $expires];
    }

    /** Every unused link of the account revoked (AU-11: on deactivation and every reset); answers how many. */
    public function revokeLinks(int $userId, CarbonImmutable $now): int
    {
        return DB::table('credential_tokens')->where('user_id', $userId)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);
    }

    /** Every session of the account deleted by `user_id` under its row — a pending challenge's included (AU-09; LK-05). */
    public function endSessions(int $userId): int
    {
        return DB::table('sessions')->where('user_id', $userId)->delete();
    }

    /**
     * @param  list<string>  $companies
     * @param  list<string>  $capabilities
     * @param  list<string>  $masking
     */
    private function covers(Authority $authority, array $companies, array $capabilities, array $masking): bool
    {
        foreach ($companies as $company) {
            if (! is_numeric($company) || ! $authority->scope->includes((int) $company)) {
                return false;
            }
        }

        foreach ([...$capabilities, ...array_filter($masking, fn (string $entry): bool => CapabilityCatalogue::has($entry))] as $capability) {
            if (! $authority->has($capability)) {
                return false;
            }
        }

        return $authority->active;
    }

    /**
     * A PostgreSQL array column's values.
     *
     * @return list<string>
     */
    private static function array(mixed $value): array
    {
        if (! is_string($value) || trim($value, '{}') === '') {
            return [];
        }

        return array_map(fn (?string $entry): string => trim((string) $entry, '"'), str_getcsv(trim($value, '{}'), ',', '"', '\\'));
    }

    /** Whether the code names the built-in OWNER role. */
    public static function isOwnerRole(?string $role): bool
    {
        return $role === BuiltinRole::Owner->value;
    }
}
