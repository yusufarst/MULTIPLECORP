<?php

namespace App\Modules\OPS\Actions;

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

/**
 * `OPS RecordLegacyArchiveItem` (AX-28; WF-MIG-01; BR-XD-06; DATABASE §24): an opening import row's identified archive
 * item — its company, kind, legacy reference and description, with its ARCHIVE file of that company when one was
 * uploaded — kept as history, never a current effect. Driven only.
 */
final class RecordLegacyArchiveItem
{
    public const TYPE = 'OPS.RecordLegacyArchiveItem';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param  array{batch: int, kind: string, reference: string, description: string|null, file: int|null}  $entry */
    public function run(string $commandId, int $actorUserId, int $companyId, array $entry): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $entry));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $entry): CommandResult {
            $context->lock(new LockSet);
            $id = (int) $context->connection->table('legacy_archive_items')->insertGetId([
                'company_id' => $companyId, 'archive_kind' => $entry['kind'], 'legacy_reference' => $entry['reference'], 'description' => $entry['description'],
                'file_object_id' => $entry['file'], 'import_batch_id' => $entry['batch'],
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->audit->record(
                action: 'OPS.LegacyArchiveItemRecorded',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'legacy_archive_item',
                entityId: $id,
                commandId: $context->request->commandId,
                change: AuditChange::created(['kind' => $entry['kind'], 'reference' => $entry['reference']]),
            );

            return new CommandResult('legacy_archive_item', $id);
        });
    }
}
