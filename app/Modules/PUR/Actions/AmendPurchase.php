<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Queries\PurchaseInputs;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PUR AmendPurchase` (ROUTE-172; INT-172; NX-04; L-23; ST-01) — the goods lines edited in place before the purchase's first effect; afterwards REJECTED, an addition taking a supplementary purchase and a cost error CM-17. `purchase.manage` of the purchase's company.
 */
final class AmendPurchase
{
    public const TYPE = 'PUR.AmendPurchase';

    public const CAPABILITY = 'purchase.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseRecords $records,
        private readonly PurchaseInputs $inputs,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, Purchase $purchase, int $lockVersion, PurchaseEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), [...$payload, 'purchase' => $purchase->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $lockVersion, $entry): CommandResult {
            $context->lock(PurchaseRecords::amendLocks($purchase->id));
            $this->records->amend($context, $purchase, $lockVersion, $entry, $this->inputs->rules($entry));

            return new CommandResult('purchase', $purchase->id);
        });
    }
}
