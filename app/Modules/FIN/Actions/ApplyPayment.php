<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\PaymentApplications;
use App\Modules\FIN\Services\PaymentRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use App\Support\Search\Row;

/**
 * `FIN ApplyPayment` (ROUTE-252; INT-252; AX-19; SF-APPLY; BR-FIN-03; BR-FIN-07; M-14) — "Terapkan" on money left unapplied, a payment's or an opening credit's under the same laws, the same route PG-048's
 * "Terapkan ke Invoice" serves: each target within its outstanding, all within the source's capacity, its own client and company only; an application may back a refund's shortfall (L-46). A residual
 * command: no reopening of a completed project (WF-PRJ-03; L-11). `payment.apply` of the source's company.
 */
final class ApplyPayment
{
    public const TYPE = 'FIN.ApplyPayment';

    public const CAPABILITY = 'payment.apply';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly PaymentApplications $applications,
        private readonly PaymentRecords $records,
    ) {}

    /**
     * @param  array{payment: ?int, credit: ?int, company: int, client: int, name: string}  $source
     * @param  array<int, string>  $applications  invoice => amount
     * @param  array<int, string>  $resolves  ended application => amount
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, array $source, array $applications, array $resolves, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $source['company'], self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($source['company']), [...$payload, 'source' => $source['name']]));
        $key = $source['payment'] !== null ? ['payment_id' => $source['payment']] : ['opening_customer_credit_id' => (int) $source['credit']];

        return $this->envelope->run($request, function (CommandContext $context) use ($source, $key, $applications, $resolves, $businessDate, $prefilled): CommandResult {
            $context->lock(PaymentApplications::invoiceLocks(array_keys($applications))->union(PaymentApplications::shortfallLocks(array_keys($resolves)))->union((new LockSet)->add('application_source_balances', $key)));
            BusinessDateRule::check($businessDate, $prefilled);
            $this->applications->apply($context, $key, $source['company'], $source['client'], $applications, $resolves, $businessDate, null);

            $after = ['invoices' => array_map(fn (int $invoice): string => RowHandle::of('invoices', $invoice), array_keys($applications)), 'amounts' => array_values($applications), 'refund_shortfalls' => count($resolves)];

            if ($source['payment'] !== null) {
                $this->records->record($context, 'FIN.PaymentApplied', AuthorityClass::Adm, $source['payment'], null, $businessDate, $after);

                return new CommandResult('payment', $source['payment']);
            }

            $this->records->recordCredit($context, (int) $source['credit'], $source['company'], $businessDate, $after);

            return new CommandResult('opening_customer_credit', (int) $source['credit']);
        }, null, fn (Outcome $outcome): ?array => match ($outcome->kind === Outcome::REJECTED ? $outcome->code : null) {
            'C-13' => ['reason' => 'above_capacity', 'capacity' => Row::text(MoneyPosting::source($key) ?? (object) ['capacity_amount' => '0.00'], 'capacity_amount')],
            'C-15' => PaymentApplications::outstandingRefusal($applications),
            default => null,
        });
    }
}
