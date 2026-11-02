<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\CountEntry;
use App\Modules\INV\Services\CountApplication;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ApplyCountVariance` (ROUTE-193; INT-193; AX-06; WF-INV-05; `DIR-100`; `DIR-101`) — "Terapkan Selisih". `stock.count_apply` (ADM+); a cut `stock.reserve`; a loss on another company than the lot's `stock.allocate_intercompany` with a grant on the bearer; a registration count `products.edit`.
 */
final class ApplyCountVariance
{
    public const TYPE = 'INV.ApplyCountVariance';

    public const CAPABILITY = 'stock.count_apply';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CountApplication $application,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, CountEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $entry->countId, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock($this->application->locks($entry));

            return new CommandResult('stock_count', $this->application->apply($context, $entry));
        });
    }
}
