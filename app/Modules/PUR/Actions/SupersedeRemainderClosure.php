<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `PUR SupersedeRemainderClosure` (CM-27; DATABASE §4.6 `supersedes_id`) — the exact inverse posting of AX-32 for a closure recorded in error, both rows kept. ROUTE-169's row names no closure yet (GAP-072): no control reaches this action until that row exists (RC-06). `decisions.supersede` of the purchase's company.
 */
final class SupersedeRemainderClosure
{
    public const TYPE = 'PUR.SupersedeRemainderClosure';

    public const CAPABILITY = 'decisions.supersede';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseCorrections $corrections,
    ) {}

    public function run(string $commandId, int $actorUserId, Purchase $purchase, int $closureId, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), ['purchase' => $purchase->public_id, 'closure' => $closureId, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $closureId, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(PurchaseRecords::amendLocks($purchase->id));
            $this->corrections->supersedeClosure($context, $purchase, $closureId, $reason, $businessDate, $prefilled);

            return new CommandResult('purchase', $purchase->id);
        });
    }
}
