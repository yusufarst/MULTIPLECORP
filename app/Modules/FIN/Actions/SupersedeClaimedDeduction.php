<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\PaymentRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN SupersedeClaimedDeduction` (ROUTE-262; INT-262; NX-08; DATABASE §11.4; L-28; C-22) — a wrong remittance-advice figure corrected by a new claimed-deduction row citing the old, with its reason, never by a contra of the
 * payment and never below what is already settled against it (the source's CHECK). A row superseded since the page read it is a CONFLICT. `cash.correct` (ADM+).
 */
final class SupersedeClaimedDeduction
{
    public const TYPE = 'FIN.SupersedeClaimedDeduction';

    public const CAPABILITY = 'cash.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly MoneyPosting $money,
        private readonly PaymentRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, Payment $payment, int $deductionId, string $amount, string $reason): Outcome
    {
        $handle = RowHandle::of('payment_claimed_deductions', $deductionId);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $payment->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($payment->company_id), ['payment' => $payment->public_id, 'deduction' => $handle, 'amount' => $amount, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($payment, $deductionId, $amount, $reason, $handle): CommandResult {
            $context->lock((new LockSet)->add('payments', ['id' => $payment->id])->add('application_source_balances', ['payment_id' => $payment->id]));
            $row = DB::table('payment_claimed_deductions')->where('id', $deductionId)->where('payment_id', $payment->id)->first();

            if ($row === null || DB::table('payment_claimed_deductions')->where('supersedes_claimed_deduction_id', $deductionId)->exists()) {
                throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
            }

            $before = Money::of(Row::text($row, 'claimed_amount'));
            $after = Money::of($amount);

            if ($after->isEqualTo($before)) {
                throw Refused::rejected('C-22', ['reason' => 'unchanged']);
            }

            $source = MoneyPosting::source(['payment_id' => $payment->id]);
            $settled = $source === null ? '0.00' : Row::text($source, 'settled_amount');
            $claimed = $source === null ? '0.00' : Row::text($source, 'claimed_deductions_amount');

            if (Money::of($claimed)->minus($before)->plus($after)->compareTo(Money::of($settled)) < 0) {
                throw Refused::rejected('C-22', ['reason' => 'below_settled', 'settled' => $settled]);
            }

            $id = DB::table('payment_claimed_deductions')->insertGetId(['payment_id' => $payment->id, 'deduction_class' => Row::text($row, 'deduction_class'), 'tax_type_id' => Row::nullable($row, 'tax_type_id'), 'fee_type' => Row::nullable($row, 'fee_type'), 'claimed_amount' => $after->toString(),
                'supersedes_claimed_deduction_id' => $deductionId, 'reason' => $reason, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            $this->money->moveSource($context, ['payment_id' => $payment->id], ['claimed_deductions_amount' => $after->minus($before)->toString()]);
            $this->records->record($context, 'FIN.ClaimedDeductionSuperseded', AuthorityClass::AdmPlus, $payment->id, $reason, null, ['claimed' => $after->toString(), 'deduction' => $handle], ['claimed' => $before->toString(), 'deduction' => $handle]);

            return new CommandResult('payment_claimed_deduction', $id);
        });
    }
}
