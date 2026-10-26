<?php

namespace App\Modules\IAM\Recovery;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CatalogueSync;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The installation step that creates the first Owner (RELEASE_PLAN PRQ-09; INFRASTRUCTURE §5; GAP-049; SM-24): the
 * authorization catalogue synced first, so the built-in OWNER role exists (TASK-011; DIR-094); then, in one
 * transaction under the OWNER role row (LK-02), the one account, active, holding the OWNER role, its password
 * unusable — the hash of an unguessable random value —, with the audit event of the OWNER assignment recording the
 * operator's identity and no user row as actor (AU-25's obligation; RG-11). It is refused once the workspace is
 * installed, so every later Owner comes only through `permissions.manage` (RG-05; OD-01). The first Owner's password
 * is set only through the OWNER_RECOVERY link AU-14's command issues right after it, the Owner present (DIR-085
 * OF-06): this step sets, reads and shows no password.
 */
final class Installation
{
    public const ACTION = 'IAM.WorkspaceInstalled';

    public function __construct(
        private readonly CatalogueSync $catalogue,
        private readonly AccountHistory $history,
        private readonly AuditRecorder $audit,
    ) {}

    /** @throws RecoveryRefused */
    public function install(string $name, string $email, OperatorIdentity $operator): User
    {
        OperatorIdentity::ensureConsole();
        $name = trim($name);
        $email = trim($email);

        if ($name === '' || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new RecoveryRefused('Give the first Owner\'s name with --name: one line of at most 120 characters.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            throw new RecoveryRefused('Give the first Owner\'s e-mail address with --email.');
        }

        $this->catalogue->run();
        // Unusable until the Owner sets a password through the OWNER_RECOVERY link (AU-20 c's form; DIR-085 OF-06).
        $unusable = Hash::make(Str::random(64));

        return DB::transaction(function () use ($name, $email, $operator, $unusable): User {
            $this->history->lockOwnerRole();

            if (DB::table('users')->exists() || DB::table('audit_events')->where('action', self::ACTION)->exists()) {
                throw new RecoveryRefused('The workspace is installed already: every later Owner comes through permissions.manage (RG-05; SM-24).');
            }

            $owner = new User;
            $owner->forceFill(['name' => $name, 'email' => $email, 'password' => $unusable, 'is_active' => true])->save();
            $grantedAt = now();
            DB::table('user_roles')->insert([
                'user_id' => $owner->id,
                'role_id' => $this->history->ownerRoleId(),
                'granted_by' => null,
                'granted_at' => $grantedAt,
            ]);

            $this->audit->record(
                action: self::ACTION,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $owner->id,
                entityPublicId: $owner->public_id,
                change: AuditChange::created(AccountHistory::image(AccountHistory::OWNER, null, $grantedAt, true)),
                metadata: ['operator' => $operator->toArray()],
            );

            return $owner;
        });
    }
}
