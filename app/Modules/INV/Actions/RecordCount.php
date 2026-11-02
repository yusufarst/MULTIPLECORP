<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\ReceivingRecords;
use App\Modules\INV\Services\StockCounts;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV RecordCount` (ROUTE-192; INT-192; NX-05; IP-19; SF-SCAN; L-48) — one counted line, one request and one answer. `stock.count` (ADM).
 */
final class RecordCount
{
    public const TYPE = 'INV.RecordCount';

    public const CAPABILITY = 'stock.count';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly StockCounts $counts,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $count, int $product, int $location, string $condition, string $qty, ?string $serial, ?int $lot, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $count, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($count, $product, $location, $condition, $qty, $serial, $lot): CommandResult {
            $context->lock(StockCounts::lineLocks($count, $product, $serial === null ? null : ReceivingRecords::serialKey($serial)));

            return new CommandResult('stock_count_line', $this->counts->line($context, $count, $location, $condition, $qty, $serial, $lot)['line']);
        });
    }
}
