<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Queries\PurchaseInputs;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PUR RecordOpeningPurchase` (AX-28; WF-MIG-01; DATABASE §24): an opening import row's open purchase — of origin
 * OPENING, keyed to its row, its remaining quantities as ordered quantities, a project purchase's lines on the open
 * project's lines — through the ordinary purchase records; its legacy number kept when it fits the company's
 * PURCHASE scheme and lies below its period's committed seed, REJECTED at or above it (NM-08), any other number drawn
 * from its date's period, REJECTED where that period may create no counter (C-10; `DIR-079` CB-01). Driven only.
 */
final class RecordOpeningPurchase
{
    public const TYPE = 'PUR.RecordOpeningPurchase';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseRecords $records,
        private readonly PurchaseInputs $inputs,
    ) {}

    /** @param  array<string, mixed>  $payload  the import row's payload, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $importRow, ?string $legacyNumber, PurchaseEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $entry->companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, [...$payload, 'import_row' => $importRow]));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry, $importRow, $legacyNumber): CommandResult {
            $context->lock($this->records->locks($entry));

            return new CommandResult('purchase', $this->records->record($context, $entry, $this->inputs->rules($entry), ['import_row' => $importRow, 'number' => $legacyNumber]));
        });
    }
}
