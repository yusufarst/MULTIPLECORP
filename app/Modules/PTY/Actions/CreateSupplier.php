<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Models\Supplier;
use App\Modules\PTY\Services\ClientRows;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\Outcome;

/**
 * `PTY CreateSupplier` (ROUTE-143; INT-143; NX-01; WF-MD-03; J-MD-03; CAP-02): a supplier chosen directly — no
 * comparison, scoring or ranking step (AC-02; SH-01) —, its master identity only: the price history is derived per
 * company from that company's purchases, never stored here (BR-ACC-02). Also inline from PG-017, answering once
 * before the purchase form continues with the supplier chosen (IP-19). An active supplier whose name equals the new
 * one or begins with it refuses the creation with that list, as an organization's does (PF-21; IP-05).
 */
final class CreateSupplier
{
    public const TYPE = 'PTY.CreateSupplier';

    public function __construct(
        private readonly PartyCommands $commands,
        private readonly ClientRows $rows,
    ) {}

    /**
     * @param  array<string, string|null>  $fields
     * @param  list<string>  $acknowledged
     */
    public function run(string $commandId, int $actorUserId, string $name, array $fields, array $acknowledged, ?string $reason): Outcome
    {
        $values = ['name' => $name, ...$fields];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, [...$values, 'acknowledged' => $acknowledged, 'reason' => $reason], function (CommandContext $context) use ($name, $values, $acknowledged, $reason): CommandResult {
            DuplicateWarning::check('PF-21', $this->rows->sameNames('suppliers', $name), $acknowledged, $reason);

            $supplier = $this->rows->insert($context, new Supplier, $values);
            $this->commands->record($context, 'PTY.SupplierCreated', 'supplier', $supplier->id, $supplier->public_id, [], $values, $reason);

            return new CommandResult('supplier', $supplier->id);
        });
    }
}
