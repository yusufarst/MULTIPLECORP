<?php

namespace App\Modules\CAT\Services;

use App\Modules\CAT\Models\Unit;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The steps PG-056's NX-01 commands share (TASK-027; CONCURRENCY_IDEMPOTENCY §7.3; WF-MD-04): each carries its
 * `command_id` (CI-01) through the envelope under `products.edit` — whose holder has a company in scope (CS-05) — and
 * no company, the catalogue being a master (CS-06); its rows locked at LK-08 — and the product's guard at LK-13 for a
 * serial-policy or scale switch (ST-09) —; an edit applied by the conditional `lock_version` update (ST-01); its
 * business audit in its transaction with before and after, the individual actor and authority class ADM (BR-XC-01;
 * LG-01). A unit is the dictionary's by its code: the one the form names, or a new one created in the same command.
 */
final class CatalogCommands
{
    public const CAPABILITY = 'products.edit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(CommandContext): CommandResult  $action
     */
    public function run(string $commandId, string $type, int $actorUserId, array $payload, Closure $action): Outcome
    {
        return $this->envelope->run(new CommandRequest($commandId, $type, $actorUserId, null, self::CAPABILITY, Fingerprint::of($type, null, $payload)), $action);
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
     * The dictionary's unit of the code — upper-cased, white space removed —, created with its name when none has it.
     */
    public function unit(CommandContext $context, string $code, string $name): Unit
    {
        $code = mb_strtoupper((string) preg_replace('/\s+/u', '', $code));
        $unit = Unit::query()->where('code', $code)->first();

        if ($unit === null) {
            $unit = $this->insert($context, new Unit, ['code' => $code, 'name' => trim($name) === '' ? $code : trim($name)]);
            $this->record($context, 'CAT.UnitCreated', 'unit', $unit->id, null, [], ['code' => $code, 'name' => $unit->name]);
        }

        return $unit;
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $row
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function insert(CommandContext $context, Model $row, array $values, int $line = 1): Model
    {
        $row->forceFill([...$values, 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $context->now(),
            'recorded_by' => $context->request->actorUserId])->save();

        return $row;
    }

    /**
     * The command's business audit event, ADM (LG-01); a price default's keys projected by its family (PJ-11; PJ-14).
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
