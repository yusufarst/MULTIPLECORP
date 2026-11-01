<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
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
 * `PUR RecordPurchase` (ROUTE-171; INT-171; NX-04 with AX-29's charges; WF-PUR-01; WF-PUR-02; BR-PUR-03; L-05; NM-08) — "Simpan Pembelian": no cash leaves and no stock arrives. `purchase.manage` of the project's company, or of the chosen active one (CS-03; C-54).
 */
final class RecordPurchase
{
    public const TYPE = 'PUR.RecordPurchase';

    public const CAPABILITY = 'purchase.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseRecords $records,
        private readonly PurchaseInputs $inputs,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, PurchaseEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $entry->companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($entry->companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock($this->records->locks($entry));

            return new CommandResult('purchase', $this->records->record($context, $entry, $this->inputs->rules($entry)));
        });
    }
}
