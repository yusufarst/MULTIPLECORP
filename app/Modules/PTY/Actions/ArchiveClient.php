<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Data\PartyFields;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `PTY ArchiveClient` (ROUTE-142; INT-142; NX-01; WF-MD-02; BR-CR-06 ARCHIVE): after its confirmation, which says what
 * continues (J-MD-02), an organization, a unit, an address or a PIC is no longer offered for a new project, while
 * its open projects continue and issued outputs keep the address as used (BR-SN-02). Nothing is deleted — a master
 * with live history is archived, never removed (FS-02). The row under its organization at LK-08, at the
 * `lock_version` the page read (ST-01).
 */
final class ArchiveClient
{
    public const TYPE = 'PTY.ArchiveClient';

    public function __construct(private readonly PartyCommands $commands) {}

    /** @param  'organization'|'unit'|'address'|'contact'  $kind */
    public function run(string $commandId, int $actorUserId, string $kind, int $organizationId, int $rowId, int $lockVersion, ?string $reason): Outcome
    {
        $table = PartyFields::TABLES[$kind];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['kind' => $kind, 'row' => $rowId, 'lock_version' => $lockVersion, 'reason' => $reason],
            function (CommandContext $context) use ($kind, $table, $organizationId, $rowId, $lockVersion, $reason): CommandResult {
                $context->lock((new LockSet)->add('client_organizations', ['id' => $organizationId])->add($table, ['id' => $rowId]));
                $row = (array) DB::table($table)->where('id', $rowId)->first();

                if (($row['is_active'] ?? false) !== true) {
                    throw Refused::rejected('WF-MD-02', ['reason' => 'archived']);
                }

                $this->commands->update($context, $table, $rowId, $lockVersion, $this->commands->archived($context, $reason));
                $publicId = $row['public_id'] ?? null;
                $this->commands->record($context, 'PTY.ClientArchived', 'client_'.$kind, $rowId, is_string($publicId) ? $publicId : null, ['is_active' => true], ['is_active' => false], $reason);

                return new CommandResult('client_'.$kind, $rowId);
            });
    }
}
