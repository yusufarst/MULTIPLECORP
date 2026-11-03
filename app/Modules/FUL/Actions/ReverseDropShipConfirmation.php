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
use Brick\Math\BigDecimal;

/**
 * `FUL ReverseDropShipConfirmation` (ROUTE-206; INT-206; CM-27; AX-10 with LK-24) — a drop-ship confirmation recorded in
 * error reversed on PG-032 with its reason: the HPP contra'd, the purchase line's confirmed quantity and posted values
 * lowered, each serial back to NEVER_RECEIVED, the delivered part corrected first (CM-08). `fulfillment.correct`
 * (ADM_PLUS) of the project's company; its one `command_id` replayed reverses once (CI-05).
 */
final class ReverseDropShipConfirmation
{
    public const TYPE = 'FUL.ReverseDropShipConfirmation';

    public const CAPABILITY = 'fulfillment.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DropshipPosting $dropship,
    ) {}

    /**
     * @param  list<array{line: int, quantity: string}>  $items
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $confirmationId, array $items, string $reason, string $businessDate, bool $prefilled, array $payload, array $acknowledged = [], ?string $duplicateReason = null): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId, 'target' => $confirmationId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $confirmationId, $items, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason): CommandResult {
            $context->lock(DropshipPosting::correctionLocks($confirmationId, array_column($items, 'line'), null));

            return new CommandResult('dropship_confirmation', $this->dropship->reverse($context, $project, $confirmationId, $items, $reason, $businessDate, $prefilled, $acknowledged, $duplicateReason));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'L-49' ? ['matches' => DropshipPosting::likelyDuplicates($confirmationId, 'DROPSHIP_CONFIRMATION_REVERSAL', self::total($items), $businessDate)] : null);
    }

    /** @param  list<array{line: int, quantity: string}>  $items */
    public static function total(array $items): string
    {
        $total = BigDecimal::zero();

        foreach ($items as $item) {
            $total = $total->plus(is_numeric($item['quantity']) ? $item['quantity'] : '0');
        }

        return (string) $total;
    }
}
