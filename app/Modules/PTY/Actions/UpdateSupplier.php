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
 * `PTY UpdateSupplier` (ROUTE-144; INT-144; NX-01; BR-SN-01): the supplier's identity, address and contact, the row at
 * LK-08 changed at the `lock_version` the form read (ST-01). A purchase keeps the supplier as it was at commit, its
 * own snapshot (DATABASE §14), so a change here reaches later purchases only. An archived supplier is not edited.
 */
final class UpdateSupplier
{
    public const TYPE = 'PTY.UpdateSupplier';

    public function __construct(private readonly PartyCommands $commands) {}

    /** @param  array<string, string|null>  $fields */
    public function run(string $commandId, int $actorUserId, Supplier $supplier, int $lockVersion, array $fields): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['supplier' => $supplier->public_id, 'lock_version' => $lockVersion, ...$fields],
            function (CommandContext $context) use ($supplier, $lockVersion, $fields): CommandResult {
                $context->lock((new LockSet)->add('suppliers', ['id' => $supplier->id]));
                $row = Supplier::query()->findOrFail($supplier->id);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-03', ['reason' => 'archived']);
                }

                [$before, $after] = $this->commands->changes($row->getAttributes(), $fields);

                if ($row->lock_version !== $lockVersion) {
                    throw Refused::conflict('ST-01');
                }

                if ($after !== []) {
                    $this->commands->update($context, 'suppliers', $row->id, $lockVersion, $after);
                    $this->commands->record($context, 'PTY.SupplierUpdated', 'supplier', $row->id, $row->public_id, $before, $after);
                }

                return new CommandResult('supplier', $row->id);
            });
    }
}
