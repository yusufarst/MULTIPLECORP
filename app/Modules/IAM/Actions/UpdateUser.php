<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `IAM UpdateUser` (ROUTE-122; INT-122; NX-02): the account's name and e-mail — its editable fields; its language is
 * the account's own (AU-13) and its password never the Owner's to set (AU-12) — against the `lock_version` the form
 * was opened with, a stale one a CONFLICT (IP-03); the e-mail normalized and unique (AU-01).
 */
final class UpdateUser
{
    public const TYPE = 'IAM.UpdateUser';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target, string $name, string $email, int $lockVersion): Outcome
    {
        $name = trim($name);
        $email = CreateUser::normalized($email);

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['name' => $name, 'email' => $email, 'lock_version' => $lockVersion],
            function (CommandContext $context) use ($target, $name, $email, $lockVersion): CommandResult {
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);
                $now = $context->now();

                if (AccountAccess::version($row) !== $lockVersion) {
                    throw Refused::conflict('ST-01', ['lock_version' => AccountAccess::version($row), 'name' => $row->name, 'email' => $row->email]);
                }

                if (DB::table('users')->whereRaw('lower(email) = ?', [$email])->where('id', '!=', $row->id)->exists()) {
                    throw Refused::rejected('AU-01', ['field' => 'email']);
                }

                DB::table('users')->where('id', $row->id)->update(['name' => $name, 'email' => $email, 'lock_version' => $lockVersion + 1, 'updated_at' => $now]);

                $this->audit->record(
                    action: 'IAM.UserUpdated',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $context->request->actorUserId,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    commandId: $context->request->commandId,
                    change: AuditChange::updated(['name' => $row->name, 'email' => $row->email], ['name' => $name, 'email' => $email]),
                );

                return new CommandResult('user', $row->id);
            });
    }
}
