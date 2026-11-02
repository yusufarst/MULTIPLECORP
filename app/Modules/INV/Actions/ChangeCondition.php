<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Services\StockChanges;
use App\Modules\INV\Services\StockPlaces;
use App\Modules\INV\Services\UnattributedCases;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ChangeCondition` (ROUTE-195; INT-195; AX-06; WF-INV-04; NX-05) — "Ubah Kondisi": named lots or serials, *Layak pakai* → *Tidak layak pakai*, with MSG-40's reason, its evidence and IP-20's cut. `stock.condition` (ADM+) and one company in scope: physical pool state (AZ-08; CS-05); a cut also `stock.reserve`.
 */
final class ChangeCondition
{
    public const TYPE = 'INV.ChangeCondition';

    public const CAPABILITY = 'stock.condition';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly UnattributedCases $cases,
        private readonly StockChanges $service,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, StockPicks $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            // Across companies with no evidence: a pending case, never a named change (SF-UNATTRIBUTED; Part 4). A conversion sent here is refused there: disposal is `stock.adjust`'s.
            if ($entry->pending !== [] || $entry->conversions !== []) {
                $context->lock(UnattributedCases::entryLocks($entry));

                return new CommandResult('stock_movement', $this->cases->recordEntry($context, $entry, 'CONDITION_CHANGE'));
            }

            $context->lock(StockPlaces::locks($entry->productId, $entry->picks, $entry->cuts, $entry->evidence));

            return new CommandResult('stock_movement', $this->service->change($context, $entry, false));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? StockPlaces::refusalState($outcome->code, $entry, 'CONDITION_CHANGE') : null);
    }
}
