<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Services\StockChanges;
use App\Modules\INV\Services\StockPlaces;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV Reinspect` (ROUTE-195; INT-195; NX-05; WF-INV-04) — "Periksa Ulang": the explicit decision returning UNUSABLE units to usable, with its reason. `stock.condition` (ADM+) and one company in scope (AZ-08; CS-05).
 */
final class Reinspect
{
    public const TYPE = 'INV.Reinspect';

    public const CAPABILITY = 'stock.condition';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly StockChanges $service,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, StockPicks $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock(StockPlaces::locks($entry->productId, $entry->picks, $entry->cuts, $entry->evidence));

            return new CommandResult('stock_movement', $this->service->change($context, $entry, true));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? StockPlaces::refusalState($outcome->code, $entry, 'CONDITION_CHANGE') : null);
    }
}
