<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\InvoiceBalance;
use App\Modules\FIN\Models\MoneyFactBalance;
use App\Modules\OPS\Services\CorrectionCases;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * SF-APPLY (AX-18's same-command applications and AX-19; BR-FIN-03; BR-FIN-04; BR-FIN-07; C-13–C-15): money of a source applied to issued invoices of its own client and company, billed or
 * unbilled, never drafts, each within its outstanding and all within the source's capacity, read under the guards' locks so the loser of a race is refused with the current figure; an application
 * that resolves a refund's shortfall moving the refund's, both sources' and the case's guards together (L-46; DATABASE §11.3). The amount is absolute and never changes. Whatever is left is the client's credit.
 */
final class PaymentApplications
{
    public function __construct(
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
        private readonly CorrectionCases $cases,
    ) {}

    /**
     * LK-01's projects and LK-25's invoices of the targets (LR-01).
     *
     * @param  list<int>  $invoiceIds
     */
    public static function invoiceLocks(array $invoiceIds): LockSet
    {
        $set = new LockSet;

        foreach (DB::table('invoices')->whereIn('id', $invoiceIds)->get(['id', 'project_id']) as $invoice) {
            $set = $set->add('invoices', ['id' => (int) Row::text($invoice, 'id')]);
            $project = Row::nullable($invoice, 'project_id');
            $set = $project === null ? $set : $set->add('projects', ['id' => (int) $project]);
        }

        return $set;
    }

    /**
     * What resolving these ended applications' shortfalls holds: the refund's fact, the ended source and the case with its projects.
     *
     * @param  list<int>  $endedIds
     */
    public static function shortfallLocks(array $endedIds): LockSet
    {
        $set = new LockSet;

        foreach (DB::table('payment_applications')->whereIn('id', $endedIds)->whereNotNull('refund_disbursement_id')->get(['payment_id', 'opening_customer_credit_id', 'refund_disbursement_id']) as $ended) {
            $refund = (int) Row::text($ended, 'refund_disbursement_id');
            $set = $set->add('money_fact_balances', ['disbursement_id' => $refund])->add('application_source_balances', self::sourceKey($ended));
            $case = MoneyFactBalance::query()->toBase()->where('disbursement_id', $refund)->value('residual_case_id');
            $set = is_numeric($case) ? $set->union(CorrectionCases::locks((int) $case)) : $set;
        }

        return $set;
    }

    /** @return array<string, int> The guard's key of an application's source. */
    public static function sourceKey(object $application): array
    {
        $payment = Row::nullable($application, 'payment_id');

        return $payment !== null ? ['payment_id' => (int) $payment] : ['opening_customer_credit_id' => (int) Row::text($application, 'opening_customer_credit_id')];
    }

    /**
     * Applies the money, the source's guard row read under its lock.
     *
     * @param  array<string, int>  $source  `payment_id` or `opening_customer_credit_id`
     * @param  array<int, string>  $applications  invoice => amount
     * @param  array<int, string>  $resolves  ended application => amount
     */
    public function apply(CommandContext $context, array $source, int $companyId, int $clientId, array $applications, array $resolves, string $businessDate, ?string $reason): void
    {
        $guard = MoneyPosting::source($source);
        $capacity = $guard === null ? '0.00' : Row::text($guard, 'capacity_amount');
        $total = Money::zero();
        $moves = [];
        $applied = Money::zero();

        self::ensureTargets($applications, $companyId, $clientId);

        foreach ($applications as $invoice => $amount) {
            $applied = $applied->plus(Money::of($amount));
            $moves[$invoice] = ['applied_amount' => Money::of($amount)->toString()];
        }

        $total = $total->plus($applied);
        $shortfalls = [];

        foreach ($resolves as $ended => $amount) {
            $shortfalls[$ended] = self::ensureShortfall($ended, $companyId, $clientId, Money::of($amount));
            $total = $total->plus(Money::of($amount));
        }

        if ($total->compareTo(Money::of($capacity)) > 0) {
            throw Refused::rejected('C-13', ['reason' => 'above_capacity', 'capacity' => $capacity, 'amount' => $total->toString()]);
        }

        $now = $context->now();
        $line = 0;
        $base = [...$source, 'company_id' => $companyId, 'client_organization_id' => $clientId, 'business_date' => $businessDate, 'reason' => $reason, 'command_id' => $context->request->commandId, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId];

        $rows = [];

        foreach ($applications as $invoice => $amount) {
            $rows[] = [...$base, 'command_line_no' => ++$line, 'invoice_id' => $invoice, 'target_kind' => 'INVOICE', 'amount' => Money::of($amount)->toString()];
        }

        if ($rows !== []) {
            DB::table('payment_applications')->insert($rows);
        }

        $backed = Money::zero();

        foreach ($resolves as $ended => $amount) {
            $moved = Money::of($amount);
            $found = $shortfalls[$ended];
            DB::table('payment_applications')->insert([...$base, 'command_line_no' => ++$line, 'refund_disbursement_id' => $found['refund'], 'target_kind' => 'REFUND', 'amount' => $moved->toString(), 'resolves_application_id' => $ended]);
            $backed = $backed->plus($moved);
            $this->money->moveSource($context, $found['source'], ['refund_shortfall_amount' => $moved->negated()->toString()]);
            $this->money->moveFact($context, ['disbursement_id' => $found['refund']], ['backed_amount' => $moved->toString(), 'shortfall_amount' => $moved->negated()->toString()]);
            $this->cases->post($context, $found['case'], ['unbacked_refund_amount' => $moved->negated()->toString()], $reason ?? 'FIN.PaymentApplied');
        }

        $this->money->moveSource($context, $source, ['invoice_applied_amount' => $applied->toString(), 'refund_backed_amount' => $backed->toString()]);
        $this->receivable->moveAll($context, $moves);
    }

    /**
     * The invoices take this money, read in two statements however many there are: each its client's and company's (C-14), issued (BR-FIN-03), its outstanding at least its amount (C-15).
     *
     * @param  array<int, string>  $amounts  invoice => amount
     */
    public static function ensureTargets(array $amounts, int $companyId, int $clientId): void
    {
        if ($amounts === []) {
            return;
        }

        $invoices = DB::table('invoices')->whereIn('id', array_keys($amounts))->get(['id', 'state', 'company_id', 'client_organization_id'])->keyBy('id');
        $balances = InvoiceBalance::query()->toBase()->whereIn('invoice_id', array_keys($amounts))->pluck('outstanding', 'invoice_id');

        foreach ($amounts as $id => $amount) {
            $invoice = $invoices->get($id);

            if (! is_object($invoice) || (int) Row::text($invoice, 'company_id') !== $companyId || (int) Row::text($invoice, 'client_organization_id') !== $clientId) {
                throw Refused::rejected('C-14', ['reason' => 'other_party']);
            }

            if (Row::text($invoice, 'state') === 'VOIDED') {
                throw Refused::rejected('C-15', ['reason' => 'voided', 'outstanding' => '0.00']);
            }

            if (Row::text($invoice, 'state') !== 'ISSUED') {
                throw Refused::rejected('BR-FIN-03', ['reason' => 'not_issued']);
            }

            $outstanding = $balances->get($id);
            $outstanding = is_scalar($outstanding) ? (string) $outstanding : '0.00';

            if (Money::of($amount)->compareTo(Money::of($outstanding)) > 0) {
                throw Refused::rejected('C-15', ['reason' => 'above_outstanding', 'outstanding' => $outstanding, 'invoice' => InvoiceSubjects::number($id) ?? '']);
            }
        }
    }

    /**
     * The refund's open shortfall this money may back: an application a source's contra ended, of the same client and company (C-14; C-44).
     *
     * @return array{refund: int, case: int, source: array<string, int>}
     */
    private static function ensureShortfall(int $endedId, int $companyId, int $clientId, Money $amount): array
    {
        $ended = DB::table('payment_applications')->where('id', $endedId)->where('end_reason', 'SOURCE_CONTRA')->whereNotNull('refund_disbursement_id')->first(['company_id', 'client_organization_id', 'refund_disbursement_id', 'payment_id', 'opening_customer_credit_id']);

        if ($ended === null || (int) Row::text($ended, 'company_id') !== $companyId || (int) Row::text($ended, 'client_organization_id') !== $clientId) {
            throw Refused::rejected('C-14', ['reason' => 'other_party']);
        }

        $refund = (int) Row::text($ended, 'refund_disbursement_id');
        $fact = MoneyFactBalance::query()->toBase()->where('disbursement_id', $refund)->first(['shortfall_amount', 'residual_case_id']);
        $source = self::sourceKey($ended);
        $carried = MoneyPosting::source($source);
        $open = $fact === null ? Money::zero() : Money::of(Row::text($fact, 'shortfall_amount'));
        $held = $carried === null ? Money::zero() : Money::of(Row::text($carried, 'refund_shortfall_amount'));
        $limit = Money::min($open, $held);

        if ($fact === null || Row::nullable($fact, 'residual_case_id') === null || $amount->compareTo($limit) > 0) {
            throw Refused::rejected('C-44', ['reason' => 'above_shortfall', 'shortfall' => $limit->toString()]);
        }

        return ['refund' => $refund, 'case' => (int) Row::text($fact, 'residual_case_id'), 'source' => $source];
    }
}
