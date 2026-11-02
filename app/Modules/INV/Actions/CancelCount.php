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
 * `INV CancelCount` (ROUTE-215; INT-215; NX-05; `DIR-100`) — "Batalkan Hitungan": an open count CANCELLED, its lines kept and no stock changed. `stock.count` (ADM).
 */
final class CancelCount
{
    public const TYPE = 'INV.CancelCount';

    public const CAPABILITY = 'stock.count';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly StockCounts $counts,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $count, int $product, string $reason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $count, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($count, $product, $reason): CommandResult {
            $context->lock(StockCounts::lineLocks($count, $product, null));

            $this->counts->cancel($context, $count, $reason);

            return new CommandResult('stock_count', $count);
        });
    }
}
