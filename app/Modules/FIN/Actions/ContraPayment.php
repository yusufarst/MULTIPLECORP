<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\PaymentCorrections;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FIN ContraPayment` (ROUTE-254; INT-254; AX-20; CM-19; SF-CONTRA; SF-CASE; ST-07; M-14; FX-04) — "Batalkan Catatan Pembayaran (Kontra)" with MSG-40's reason (IP-09): the contra and the forced disposition of every
 * active application and settlement in one command, a stale list a CONFLICT with the current one; both facts stay visible, the case opened, the correct payment recorded afterwards as its own command. `finance.contra` (ADM+).
 */
final class ContraPayment
{
    public const TYPE = 'FIN.ContraPayment';

    public const CAPABILITY = 'finance.contra';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PaymentCorrections $corrections,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, Payment $payment, ContraEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $payment->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($payment->company_id), [...$payload, 'payment' => $payment->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($payment, $entry): CommandResult {
            $context->lock($this->corrections->contraLocks($payment, $entry->rerecordedId));
            // Data-dependent (§5 steps 3–4): what stands now, and the citation a correction may have moved, read again under the locks.
            $fresh = $payment->fresh() ?? $payment;
            $context->require($this->corrections->contraLocks($fresh, $entry->rerecordedId));
            $this->corrections->contra($context, $fresh, $entry);

            return new CommandResult('payment', $fresh->id);
        }, null, fn (Outcome $outcome): ?array => PaymentCorrections::replayState($outcome, $payment->id));
    }
}
