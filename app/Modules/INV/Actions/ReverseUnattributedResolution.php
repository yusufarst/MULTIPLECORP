<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\UnattributedCases;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseUnattributedResolution` (ROUTE-200; INT-200; AX-37; CM-27; OD-10; OD-15) — "Balikkan Penetapan": the exact reversal, once. An Owner resolution by `stock.resolve_unattributed`, an evidence-identified one by `stock.resolve_unattributed_evidence` or the Owner.
 */
final class ReverseUnattributedResolution
{
    public const TYPE = 'INV.ReverseUnattributedResolution';

    public const CAPABILITY = 'stock.resolve_unattributed';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly UnattributedCases $cases,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $resolution, string $capability, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, $capability, Fingerprint::of(self::TYPE, null, ['target' => $resolution, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($resolution, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(UnattributedCases::reversalLocks($resolution));

            return new CommandResult('stock_movement', $this->cases->reverse($context, $resolution, $reason, $businessDate, $prefilled));
        });
    }
}
