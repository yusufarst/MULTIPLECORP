<?php

namespace App\Modules\INV\Actions;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Services\DispatchRestoration;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV RestoreStock` (ROUTE-210; INT-210; AX-10; WF-FUL-05; CM-10; SF-RESTORE) — "Retur dari Klien" on PG-034: delivered goods
 * taken back from the client with the case's reason and evidence, usable units back into usable stock on their lot and damaged
 * ones into UNUSABLE on it, at their HPP contra with the allocation reversed, net delivered lowered, the delivery record kept.
 * `sales_return.record` (ADM_PLUS) of the project's company; the same-action reservation also `stock.reserve`; a likely
 * duplicate confirmed with its reason (BD-13; INT-015); one `command_id` replayed returns once (CI-05).
 */
final class RestoreStock
{
    public const TYPE = 'INV.RestoreStock';

    public const CAPABILITY = 'sales_return.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DispatchRestoration $restoration,
    ) {}

    /**
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @param  list<string>  $acknowledged
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, int $dispatchLineId, array $items, ?string $reserve, string $reason, ?int $evidenceId, array $acknowledged, ?string $duplicateReason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), [...$payload, 'target' => $dispatchLineId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($dispatchLineId, $items, $reserve, $reason, $evidenceId, $acknowledged, $duplicateReason, $businessDate, $prefilled, $serializedSeen, $scaleSeen): CommandResult {
            $locks = $this->restoration->locks($dispatchLineId, $items);
            $context->lock($evidenceId === null ? $locks : $locks->union(EvidenceGuard::locks($evidenceId)));

            return new CommandResult('stock_movement', $this->restoration->restore($context, $dispatchLineId, DispatchRestoration::SALES_RETURN, $items, $reserve, $reason, $acknowledged, $duplicateReason, $businessDate, $prefilled, $serializedSeen, $scaleSeen, null, $evidenceId));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? DispatchRestoration::refusalState($outcome->code, $dispatchLineId, $items, $businessDate, DispatchRestoration::SALES_RETURN) : null);
    }
}
