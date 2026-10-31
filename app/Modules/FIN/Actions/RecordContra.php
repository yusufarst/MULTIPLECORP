<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Services\ContraPosting;
use App\Modules\FIN\Services\SettlementPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FIN RecordContra` (ROUTE-260; INT-260; AX-33; SF-CONTRA; CM-22; CM-38; L-46; M-14) — "Batalkan Potongan (Kontra)" and "Batalkan Catatan (Kontra)" with MSG-40's reason (IP-09): the contra of a settlement, a disbursement,
 * refund or expense, at most the original net of prior contras, what depends on it disposed in the same action and both records visible. `finance.contra` (ADM+) of the fact's company. Data-dependent (§5).
 */
final class RecordContra
{
    public const TYPE = 'FIN.RecordContra';

    public const CAPABILITY = 'finance.contra';

    public const SETTLEMENT = 'potongan';

    public const DISBURSEMENT = 'pengeluaran';

    public const EXPENSE = 'beban';

    public const KINDS = [self::SETTLEMENT, self::DISBURSEMENT, self::EXPENSE];

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SettlementPosting $settlements,
        private readonly ContraPosting $contras,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, string $kind, int $factId, ?string $amount, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($kind, $factId, $amount, $reason, $businessDate, $prefilled): CommandResult {
            $locks = fn () => match ($kind) {
                self::SETTLEMENT => SettlementPosting::contraLocks($factId),
                self::DISBURSEMENT => ContraPosting::disbursementLocks($factId),
                default => ContraPosting::expenseLocks($factId),
            };
            $context->lock($locks());
            // Data-dependent (§5 steps 3–4): the dependents read again under the locks.
            $context->require($locks());

            match ($kind) {
                self::SETTLEMENT => $this->settlements->contra($context, $factId, $reason, $businessDate, $prefilled),
                self::DISBURSEMENT => $this->contras->contraDisbursement($context, $factId, (string) $amount, $reason, $businessDate, $prefilled),
                default => $this->contras->contraExpense($context, $factId, (string) $amount, $reason, $businessDate, $prefilled),
            };

            return new CommandResult(match ($kind) {
                self::SETTLEMENT => 'deduction_settlement',
                self::DISBURSEMENT => 'disbursement',
                default => 'expense',
            }, $factId);
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? ContraPosting::replayState($outcome, $kind === self::EXPENSE, $factId) : null);
    }
}
