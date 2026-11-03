<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\ReturnReversal;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseRestorationOrReturn` (ROUTE-211; INT-211; AX-36; CM-37; C-55) — "Balikkan (Tercatat Keliru)" on PG-034 and PG-028: a
 * restoration, a sales return or a purchase return recorded in error taken back by its linked reversal with MSG-40's reason,
 * the same lots, serials and condition present, IP-20's cut when usable supply would fall below the reservations; a drop-ship
 * supplier return or confirmation reversal by its one exact DROPSHIP_ERROR_REVERSAL. `stock.reverse` (ADM_PLUS) of the
 * original's company; a cut also `stock.reserve`; one `command_id` replayed reverses once (CI-05).
 */
final class ReverseRestorationOrReturn
{
    public const TYPE = 'INV.ReverseRestorationOrReturn';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReturnReversal $reversal,
    ) {}

    /**
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     * @param  array<int, string>  $cuts  reservation => quantity
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, int $movementId, array $items, array $cuts, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), [...$payload, 'target' => $movementId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($movementId, $items, $cuts, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(ReturnReversal::locks($movementId, null, $items, $cuts));

            return new CommandResult('stock_movement', $this->reversal->reverse($context, $movementId, $items, $cuts, $reason, $businessDate, $prefilled));
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function runDropship(string $commandId, int $actorUserId, int $companyId, int $confirmationId, int $restorationId, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), [...$payload, 'target' => $restorationId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($confirmationId, $restorationId, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(ReturnReversal::locks(null, $confirmationId, [['restoration' => $restorationId, 'consumption' => null, 'qty' => '0', 'location' => null, 'condition' => null]], []));

            return new CommandResult('stock_restoration', $this->reversal->dropshipError($context, $confirmationId, $restorationId, $reason, $businessDate, $prefilled));
        });
    }
}
