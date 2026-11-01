<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\StockChanges;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV MoveLocation` (ROUTE-190; INT-190; NX-05; L-36; J-MD-05) — "Pindah Lokasi": the same units of one lot and condition leaving one rack and arriving at another, in place. `stock.move` (ADM) and one company in scope: physical pool state (AZ-08; CS-05).
 */
final class MoveLocation
{
    public const TYPE = 'INV.MoveLocation';

    public const CAPABILITY = 'stock.move';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly StockChanges $changes,
    ) {}

    /**
     * @param  list<int>  $serials
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $product, int $lot, int $from, int $to, string $condition, string $quantity, array $serials, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($product, $lot, $from, $to, $condition, $quantity, $serials, $businessDate, $prefilled, $serializedSeen, $scaleSeen): CommandResult {
            $context->lock(StockChanges::moveLocks($product, $lot, $from, $to, $condition, $serials));

            return new CommandResult('stock_movement', $this->changes->move($context, $product, $lot, $from, $to, $condition, $quantity, $serials, $businessDate, $prefilled, $serializedSeen, $scaleSeen));
        });
    }
}
