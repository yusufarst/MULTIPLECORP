<?php

namespace App\Modules\ORG\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The steps PG-059's and PG-062's NX-01 commands share (TASK-025; CONCURRENCY_IDEMPOTENCY §7.3; OD-03; OD-04): each one
 * carries its `command_id` (CI-01) through the envelope, its rows locked in phase B — the company at LK-06, a bank
 * account at LK-07, the configuration rows at LK-08 —, an edit applied by the conditional `lock_version` update so a
 * stale one answers CONFLICT (ST-01), and its business audit written in its transaction with before and after, the
 * Owner as actor and authority class OWNER_ONLY (LG-01; AZ-10).
 */
final class CompanyCommands
{
    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(CommandContext): CommandResult  $action
     */
    public function run(string $commandId, string $type, int $actorUserId, string $capability, ?int $companyId, ?string $companyPublicId, array $payload, Closure $action): Outcome
    {
        return $this->envelope->run(new CommandRequest($commandId, $type, $actorUserId, $companyId, $capability, Fingerprint::of($type, $companyPublicId, $payload)), $action);
    }

    /**
     * Every company row at LK-06, in id order: a legal identity is checked under them, so two creations or changes of
     * one NPWP — which no unique key holds — run one after the other and the second sees the first (BD-16). The
     * workspace holds a handful of legal entities.
     */
    public function allCompanies(): LockSet
    {
        $set = new LockSet;

        foreach (DB::table('companies')->orderBy('id')->pluck('id') as $id) {
            $set = $set->add('companies', ['id' => is_numeric($id) ? (int) $id : 0]);
        }

        return $set;
    }

    /**
     * ST-01: the row changes only at the version the form read, in one conditional statement.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(CommandContext $context, string $table, int $id, int $lockVersion, array $values): void
    {
        $changed = DB::table($table)->where('id', $id)->where('lock_version', $lockVersion)
            ->update([...$values, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }
    }

    /**
     * The command's business audit event, OWNER_ONLY (LG-01; AZ-10), never a file's contents (LG-03).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(CommandContext $context, string $action, ?int $companyId, string $entityType, int $entityId, ?string $entityPublicId, array $before, array $after, ?string $reason = null): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::OwnerOnly,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: $entityType,
            entityId: $entityId,
            entityPublicId: $entityPublicId,
            reason: $reason === null || trim($reason) === '' ? null : trim($reason),
            commandId: $context->request->commandId,
            change: $before === [] ? AuditChange::created($after) : AuditChange::updated($before, $after),
        );
    }
}
