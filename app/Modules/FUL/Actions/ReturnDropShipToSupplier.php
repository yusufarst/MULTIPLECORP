<?php

namespace App\Modules\FUL\Actions;

use App\Modules\FUL\Services\DropshipPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FUL ReturnDropShipToSupplier` (ROUTE-207; INT-207; AX-10; CM-14; CM-15) — the client's return of drop-shipped goods on
 * PG-032 with its case and reason: "Retur ke Gudang", a new lot at a rack at the units' HPP contra; "Retur ke Pemasok",
 * movement-less under a purchase-return case settled through ROUTE-177. `sales_return.record` (ADM_PLUS) of the
 * project's company; a likely duplicate confirmed with its reason (BD-13; INT-015); one `command_id` replayed returns
 * once (CI-05).
 */
final class ReturnDropShipToSupplier
{
    public const TYPE = 'FUL.ReturnDropShipToSupplier';

    public const CAPABILITY = 'sales_return.record';

    public const WAREHOUSE = DropshipPosting::WAREHOUSE;

    public const SUPPLIER = DropshipPosting::SUPPLIER;

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DropshipPosting $dropship,
    ) {}

    /**
     * @param  list<array{line: int, quantity: string}>  $items
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $confirmationId, string $to, array $items, ?int $locationId, ?string $condition, string $reason, string $businessDate, bool $prefilled, array $payload, array $acknowledged = [], ?string $duplicateReason = null): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId, 'target' => $confirmationId]));
        $kind = $to === DropshipPosting::WAREHOUSE ? 'DROPSHIP_RETURN' : 'DROPSHIP_SUPPLIER_RETURN';

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $confirmationId, $to, $items, $locationId, $condition, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason): CommandResult {
            $context->lock(DropshipPosting::correctionLocks($confirmationId, array_column($items, 'line'), $to === DropshipPosting::WAREHOUSE ? $locationId : null));

            return new CommandResult('correction_case', $this->dropship->returned($context, $project, $confirmationId, $to, $items, $locationId, $condition, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'L-49' ? ['matches' => DropshipPosting::likelyDuplicates($confirmationId, $kind, ReverseDropShipConfirmation::total($items), $businessDate)] : null);
    }
}
