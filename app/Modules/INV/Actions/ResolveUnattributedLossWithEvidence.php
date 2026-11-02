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
 * `INV ResolveUnattributedLossWithEvidence` (ROUTE-199; INT-199; AX-37; OD-10; DIR-027) — "Tetapkan dengan Bukti": a resolution only when the server verifies every condition (a)–(f), the company derived from the lots and never chosen. `stock.resolve_unattributed_evidence` (ADM+).
 */
final class ResolveUnattributedLossWithEvidence
{
    public const TYPE = 'INV.ResolveUnattributedLossWithEvidence';

    public const CAPABILITY = 'stock.resolve_unattributed_evidence';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly UnattributedCases $cases,
    ) {}

    /**
     * @param  list<array{lot: int, qty: string}>  $closes
     * @param  list<int>  $evidence
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $case, array $closes, array $evidence, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $case, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($case, $closes, $evidence, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(UnattributedCases::resolutionLocks($case, [['company' => null, 'closes' => $closes]], $evidence));

            $company = $this->cases->ensureEvidence($context, $case, $closes, $evidence, $reason);

            return new CommandResult('unattributed_loss_resolution', $this->cases->resolve($context, $case, 'EVIDENCE_IDENTIFIED', [['company' => $company, 'closes' => $closes]], $reason, $businessDate, $prefilled));
        });
    }
}
