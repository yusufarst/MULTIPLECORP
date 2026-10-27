<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * `IAM CreateUser` (ROUTE-121; INT-121; NX-02; AU-01; RG-04; WF-ACC-02): one individual account per person, identified
 * by its normalized — trimmed, lower-case — e-mail, created by the Owner (`users.manage`) with the ADMIN_OPERASIONAL
 * role and no company and no capability (default deny), and inactive: the Owner gives its grants and its link, then
 * activates it (WF-ACC-02's "→ activate"; AU-12; RV-11), each a separate command with its own answer (IP-19; UXI-31).
 * Its password is unusable until the person sets one through the link (AU-12). Its audit event carries the account's
 * image under TASK-020's keys (AU-25; DIR-097 R1).
 */
final class CreateUser
{
    public const TYPE = 'IAM.CreateUser';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, string $name, string $email): Outcome
    {
        $name = trim($name);
        $email = self::normalized($email);
        // Computed before the transaction (CONCURRENCY_IDEMPOTENCY §3): the hash of an unguessable value (AU-20 c).
        $unusable = Hash::make(Str::random(64));

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', null, ['name' => $name, 'email' => $email],
            function (CommandContext $context) use ($name, $email, $unusable): CommandResult {
                $context->lock(new LockSet);
                $now = $context->now();

                if (DB::table('users')->whereRaw('lower(email) = ?', [$email])->exists()) {
                    throw Refused::rejected('AU-01', ['field' => 'email']);
                }

                $actor = $context->request->actorUserId;
                $id = DB::table('users')->insertGetId([
                    'name' => $name, 'email' => $email, 'password' => $unusable, 'is_active' => false,
                    'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
                ]);
                $role = $this->access->adminRoleId();
                DB::table('user_roles')->insert([
                    'user_id' => $id, 'role_id' => $role, 'granted_by' => $actor, 'granted_at' => $now,
                    'command_id' => $context->request->commandId, 'command_line_no' => 2, 'recorded_at' => $now,
                ]);
                $publicId = DB::table('users')->where('id', $id)->value('public_id');

                $this->audit->record(
                    action: 'IAM.UserCreated',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $actor,
                    companyId: null,
                    entityType: 'user',
                    entityId: $id,
                    entityPublicId: is_string($publicId) ? $publicId : null,
                    commandId: $context->request->commandId,
                    change: AuditChange::created(['name' => $name, 'email' => $email, ...$this->access->image($id)]),
                );

                return new CommandResult('user', $id);
            });
    }

    /** AU-01's identity: the e-mail trimmed and lower-cased. */
    public static function normalized(string $email): string
    {
        return Str::lower(trim($email));
    }
}
