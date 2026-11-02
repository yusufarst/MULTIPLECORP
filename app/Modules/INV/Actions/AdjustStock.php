<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Services\LossPosting;
use App\Modules\INV\Services\StockPlaces;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV AdjustStock` (ROUTE-194; INT-194; AX-06; WF-INV-06; L-15) — "Catat Penyesuaian Stok" for a disposal or shrinkage, a decrease only: the stock-out, the cut and SF-LOSS as one action. `stock.adjust` (ADM+); the bearer company in scope, another company than the lot's also `stock.allocate_intercompany`, otherwise refused and routed (AZ-08; XL-1).
 */
final class AdjustStock
{
    public const TYPE = 'INV.AdjustStock';

    public const CAPABILITY = 'stock.adjust';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly LossPosting $losses,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, StockPicks $entry, string $kind, ?int $case, ?string $allocationReason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry, $kind, $case, $allocationReason): CommandResult {
            $context->lock($this->losses->locks($entry, $case));

            return new CommandResult('stock_movement', $this->losses->record($context, $entry, $kind, $case, $allocationReason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? StockPlaces::refusalState($outcome->code, $entry, LossPosting::KINDS[$kind] ?? '') : null);
    }
}
