<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Services\StockPlaces;
use App\Modules\INV\Services\SupplierReturns;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReturnToSupplier` (ROUTE-176; INT-176; AX-11) — "Retur ke Pemasok": the return case with its reason, units of one lot of the purchase out of stock, their cost to SUPPLIER_RETURN, the cut in the same action. `purchase_return.record` (ADM+) in the purchase's company (CS-03); a cut also `stock.reserve`, another company's project's cut refused and routed (AZ-08; IP-20).
 */
final class ReturnToSupplier
{
    public const TYPE = 'INV.ReturnToSupplier';

    public const CAPABILITY = 'purchase_return.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SupplierReturns $returns,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, int $purchaseId, StockPicks $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['target' => $purchaseId, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchaseId, $entry): CommandResult {
            $context->lock(SupplierReturns::locks($purchaseId, $entry));

            return new CommandResult('correction_case', $this->returns->record($context, $purchaseId, $entry));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? StockPlaces::refusalState($outcome->code, $entry, 'PURCHASE_RETURN') : null);
    }
}
