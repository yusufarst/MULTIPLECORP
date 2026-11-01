<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;

/**
 * `PUR MarkLinePerformed` (ROUTE-180; INT-180; NX-04; AX-12; `DIR-101` F1) — "Tandai Terlaksana" on an open purchase's non-stock line, at any time, `purchase.manage`; "Batalkan Tanda Terlaksana" when it was recorded in error, `purchase.correct_cost` with its reason (IP-09). No cost is posted and no first effect arises under L-23.
 */
final class MarkLinePerformed
{
    public const TYPE = 'PUR.MarkLinePerformed';

    public const CAPABILITY = 'purchase.manage';

    public const TAKE_BACK = 'purchase.correct_cost';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PurchaseCorrections $corrections,
    ) {}

    public function run(string $commandId, int $actorUserId, Purchase $purchase, int $lineId, bool $performed, ?string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $purchase->company_id, $performed ? self::CAPABILITY : self::TAKE_BACK, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($purchase->company_id), ['purchase' => $purchase->public_id, 'line' => $lineId, 'performed' => $performed, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($purchase, $lineId, $performed, $reason): CommandResult {
            $context->lock((new LockSet)->add('purchases', ['id' => $purchase->id])->add('purchase_lines', ['id' => $lineId]));
            $this->corrections->mark($context, $purchase, $lineId, $performed, $reason);

            return new CommandResult('purchase', $purchase->id);
        });
    }
}
