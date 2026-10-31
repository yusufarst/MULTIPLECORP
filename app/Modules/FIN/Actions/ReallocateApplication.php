<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\PaymentApplications;
use App\Modules\FIN\Services\PaymentCorrections;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FIN ReallocateApplication` (ROUTE-253; INT-253; AX-19; CM-21; annex 4; L-28; L-46; M-28) — "Pindahkan Penerapan" with MSG-40's reason (IP-09): the old application superseded, never deleted, a new one ACTIVE, the
 * payment total unchanged; a refund-consumed application offers no move; the settlements anchored to the old (payment, invoice) moved or kept with reason in the same action. `payment.reallocate` (ADM+).
 */
final class ReallocateApplication
{
    public const TYPE = 'FIN.ReallocateApplication';

    public const CAPABILITY = 'payment.reallocate';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PaymentCorrections $corrections,
    ) {}

    /**
     * @param  array<int, bool>  $settlements  settlement => moved with it (true), kept (false)
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, Payment $payment, int $applicationId, int $targetInvoiceId, string $amount, array $settlements, string $businessDate, bool $prefilled, string $reason, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $payment->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($payment->company_id), [...$payload, 'payment' => $payment->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($payment, $applicationId, $targetInvoiceId, $amount, $settlements, $businessDate, $prefilled, $reason): CommandResult {
            $context->lock(PaymentCorrections::reallocationLocks($payment->id, $applicationId, $targetInvoiceId, array_keys($settlements)));

            return new CommandResult('payment', $this->corrections->reallocate($context, $payment, $applicationId, $targetInvoiceId, $amount, $settlements, $businessDate, $prefilled, $reason));
        }, null, fn (Outcome $outcome): ?array => $outcome->code === 'C-15' ? PaymentApplications::outstandingRefusal([$targetInvoiceId => $amount]) : null);
    }
}
