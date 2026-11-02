<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\StockCounts;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV OpenCount` (ROUTE-191; INT-191; NX-05; L-14; `DIR-101`) — "Mulai Hitungan" and "Hitung Ulang": a count of one product at a rack and condition, or a registration count of every place holding it, its scope versions stored as the counted basis. `stock.count` (ADM) and one company in scope: physical pool state (CS-05).
 */
final class OpenCount
{
    public const TYPE = 'INV.OpenCount';

    public const CAPABILITY = 'stock.count';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly StockCounts $counts,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $product, ?int $location, ?string $condition, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($product, $location, $condition): CommandResult {
            $context->lock(StockCounts::openLocks($product, $location));

            return new CommandResult('stock_count', $location === null || $condition === null ? $this->counts->openRegistration($context, $product) : $this->counts->open($context, $product, $location, $condition));
        });
    }
}
