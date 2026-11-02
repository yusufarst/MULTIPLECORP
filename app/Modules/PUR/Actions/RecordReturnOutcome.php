<?php

namespace App\Modules\PUR\Actions;

use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Services\ReturnOutcomes;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Money;

/**
 * `PUR RecordReturnOutcome` (ROUTE-177; INT-177; NX-16) — a purchase return's outcome with its reason: a replacement, a refund expected, a supplier credit evidenced, or the unrecovered share on its bearer; or the correction of an earlier one. `purchase_return.record` (ADM+) in the case's company; an unrecovered share on another company's causal project also `stock.allocate_intercompany` (XL-1), otherwise refused and routed (AZ-08).
 */
final class RecordReturnOutcome
{
    public const TYPE = 'PUR.RecordReturnOutcome';

    public const CAPABILITY = 'purchase_return.record';

    /** The outcomes ROUTE-177 takes (WF-PUR-03). */
    public const KINDS = ReturnOutcomes::KINDS;

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReturnOutcomes $outcomes,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, int $caseId, string $kind, bool $correction, ?string $qty, ?Money $amount, ?int $evidence, ?string $allocationReason, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), ['target' => $caseId, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($caseId, $kind, $correction, $qty, $amount, $evidence, $allocationReason, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(ReturnOutcomes::locks($caseId, $evidence));

            return new CommandResult('correction_case', $this->outcomes->record($context, $caseId, $kind, $correction, $qty, $amount, $evidence, $allocationReason, $reason, $businessDate, $prefilled));
        });
    }
}
