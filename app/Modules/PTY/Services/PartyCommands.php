<?php

namespace App\Modules\PTY\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The steps PG-054's and PG-055's NX-01 commands share (TASK-026; CONCURRENCY_IDEMPOTENCY §7.3; WF-MD-02; WF-MD-03):
 * each carries its `command_id` (CI-01) through the envelope under `parties.manage` — whose holder has a company in
 * scope (CS-05) — and no company, the masters belonging to none (CS-06); its master rows locked at LK-08, parties
 * first; an edit applied by the conditional `lock_version` update, so a stale one answers CONFLICT (ST-01); its
 * business audit written in its transaction with before and after, the individual actor and authority class ADM
 * (BR-XC-01; LG-01; AZ-10).
 */
final class PartyCommands
{
    public const CAPABILITY = 'parties.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(CommandContext): CommandResult  $action
     * @param  (Closure(Outcome): (array<string, mixed>|null))|null  $replayState  a replayed refusal's current state — a
     *                                                                              search-first refusal's matches, read again (CI-05)
     */
    public function run(string $commandId, string $type, int $actorUserId, array $payload, Closure $action, ?Closure $replayState = null): Outcome
    {
        return $this->envelope->run(new CommandRequest($commandId, $type, $actorUserId, null, self::CAPABILITY, Fingerprint::of($type, null, $payload)), $action, null, $replayState);
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
     * The columns an archive sets (BR-CR-06 ARCHIVE): the row stays, with its history, and is no longer offered.
     *
     * @return array<string, mixed>
     */
    public function archived(CommandContext $context, ?string $reason): array
    {
        return ['is_active' => false, 'deactivated_at' => $context->now(), 'deactivated_by' => $context->request->actorUserId,
            'deactivated_reason' => $reason === null || trim($reason) === '' ? null : trim($reason)];
    }

    /**
     * The row's own fields that differ from the submitted ones, before and after.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, string|null>  $fields
     * @return array{0: array<string, mixed>, 1: array<string, string|null>}
     */
    public function changes(array $current, array $fields): array
    {
        $before = [];
        $after = [];

        foreach ($fields as $field => $value) {
            if (($current[$field] ?? null) !== $value) {
                $before[$field] = $current[$field] ?? null;
                $after[$field] = $value;
            }
        }

        return [$before, $after];
    }

    /**
     * The command's business audit event, ADM (LG-01; AZ-10); its keys are its columns', projected like them (PJ-11).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(CommandContext $context, string $action, string $entityType, int $entityId, ?string $entityPublicId, array $before, array $after, ?string $reason = null): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: $entityType,
            entityId: $entityId,
            entityPublicId: $entityPublicId,
            reason: $reason === null || trim($reason) === '' ? null : trim($reason),
            commandId: $context->request->commandId,
            change: $before === [] ? AuditChange::created($after) : AuditChange::updated($before, $after),
        );
    }
}
