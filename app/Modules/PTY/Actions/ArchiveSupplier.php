<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Models\Supplier;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `PTY ArchiveSupplier` (ROUTE-144; INT-144; NX-01; WF-MD-03; J-MD-03): after its confirmation, which says what
 * continues, the supplier is no longer offered for a new purchase, while open purchases, receipts and returns
 * continue; nothing is deleted (BR-CR-06 ARCHIVE; FS-02). The row at LK-08, at the `lock_version` the page read
 * (ST-01).
 */
final class ArchiveSupplier
{
    public const TYPE = 'PTY.ArchiveSupplier';

    public function __construct(private readonly PartyCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Supplier $supplier, int $lockVersion, ?string $reason): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['supplier' => $supplier->public_id, 'lock_version' => $lockVersion, 'reason' => $reason],
            function (CommandContext $context) use ($supplier, $lockVersion, $reason): CommandResult {
                $context->lock((new LockSet)->add('suppliers', ['id' => $supplier->id]));

                if (! Supplier::query()->findOrFail($supplier->id)->is_active) {
                    throw Refused::rejected('WF-MD-03', ['reason' => 'archived']);
                }

                $this->commands->update($context, 'suppliers', $supplier->id, $lockVersion, $this->commands->archived($context, $reason));
                $this->commands->record($context, 'PTY.SupplierArchived', 'supplier', $supplier->id, $supplier->public_id, ['is_active' => true], ['is_active' => false], $reason);

                return new CommandResult('supplier', $supplier->id);
            });
    }
}
