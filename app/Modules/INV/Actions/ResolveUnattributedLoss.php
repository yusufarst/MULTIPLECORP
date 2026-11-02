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
 * `INV ResolveUnattributedLoss` (ROUTE-198; INT-198; AX-37; OD-09; DIR-027) — "Tetapkan Perusahaan": the Owner's assignment of a pending case within each company's exposure and the group's bound. `stock.resolve_unattributed`, the Owner's alone.
 */
final class ResolveUnattributedLoss
{
    public const TYPE = 'INV.ResolveUnattributedLoss';

    public const CAPABILITY = 'stock.resolve_unattributed';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly UnattributedCases $cases,
    ) {}

    /**
     * @param  list<array{company: int, closes: list<array{lot: int, qty: string}>}>  $lines
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $case, array $lines, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $case, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($case, $lines, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(UnattributedCases::resolutionLocks($case, $lines, []));

            return new CommandResult('unattributed_loss_resolution', $this->cases->resolve($context, $case, 'OWNER_ATTRIBUTION', $lines, $reason, $businessDate, $prefilled));
        });
    }
}
