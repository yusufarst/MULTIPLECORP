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
use App\Support\SecurityLog\SecurityEventKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * `IAM ReactivateUser` (ROUTE-123; INT-123; NX-02; RG-10; AU-18; RV-05; RV-11; M-45): the account's activation — a
 * new account's first, or a deactivated one's again, whose grants the deactivation revoked (RG-10). The Owner set
 * (LK-02) is taken first for an OWNER-role account. The activation runs AU-18's test on the account's capabilities as
 * it leaves them (DIR-048 R2-01): an account it makes high-risk loses its sessions and, without confirmed TOTP, its
 * password — the unusable hash computed before the transaction — and its unused links, one RESET link issued instead
 * and shown once (AU-12: an ONBOARDING link of an account created with a high-risk role or grants replaced). Its own
 * transaction deletes the account's sessions again (RV-05), a session a racing login wrote included.
 */
final class ReactivateUser
{
    public const TYPE = 'IAM.ReactivateUser';

    /** @var array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null */
    private ?array $issued = null;

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target): Outcome
    {
        $ownerSet = $this->access->holdsOwnerRole($target->id);
        // RV-11: computed before the transaction whenever the account has no confirmed TOTP, as read without a lock.
        $unusable = $target->getAttribute('two_factor_confirmed_at') === null ? Hash::make(Str::random(64)) : null;

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['active' => true],
            function (CommandContext $context) use ($target, $ownerSet, $unusable): CommandResult {
                $this->issued = null;
                $context->lock($this->access->locks($target->id, $ownerSet));
                $row = User::query()->findOrFail($target->id);
                $actor = $context->request->actorUserId;
                $now = $context->now();

                if ($row->getAttribute('is_active') === true) {
                    throw Refused::rejected('RG-10', ['reason' => 'active']);
                }

                if ($this->access->holdsOwnerRole($row->id)) {
                    $context->require($this->access->ownerSet());
                }

                $before = $this->access->image($row->id);
                DB::table('users')->where('id', $row->id)->update([
                    'is_active' => true, 'deactivated_at' => null, 'deactivated_by' => null,
                    'lock_version' => AccountAccess::version($row) + 1, 'updated_at' => $now,
                ]);

                $this->audit->record(
                    action: 'IAM.UserReactivated',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $actor,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    commandId: $context->request->commandId,
                    change: AuditChange::updated($before, $this->access->image($row->id)),
                );

                if ($this->access->highRisk($row->id) && $row->getAttribute('two_factor_confirmed_at') === null) {
                    $this->issued = [...$this->reissue($context, $row, $unusable), 'purpose' => 'RESET'];
                }

                // RV-05: an activation deletes the account's sessions again, in its own transaction, last (LR-02).
                $this->access->endSessions($row->id);

                return new CommandResult('user', $row->id);
            });
    }

    /**
     * The RESET link the activation issued, for the Owner's one view as flash data; none on a replay (CI-05).
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null
     */
    public function issued(): ?array
    {
        return $this->issued;
    }

    /** @return array{token: string, id: int, expires_at: CarbonImmutable} */
    private function reissue(CommandContext $context, User $row, ?string $unusable): array
    {
        // A target found without TOTP under the lock when no hash was computed: refused with the current state (RV-11).
        if ($unusable === null) {
            throw Refused::rejected('RV-11', ['reason' => 'retry']);
        }

        $link = $this->access->reissue($row, $context->request->actorUserId, $unusable, $context->now(), clearTotp: false);
        $this->access->recordLink($context, $row, $link, 'RESET', 'HIGH_RISK_GRANT');

        return $link;
    }
}
