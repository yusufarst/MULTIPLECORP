<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Models\MoneyFactBalance;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * CM-38's contra of a disbursement, expense or refund (AX-33; SF-CONTRA; BR-CR-02; C-44; C-45; L-46), at most the original net of prior contras, both visible. A refund's money that came back resolves its shortfall first, then
 * releases backing up to its amount: its consumed application returns to credit only here (DATABASE §11.3). A settlement's expense and a charge's are contra'd with what made them (C-23; CM-17), never alone.
 */
final class ContraPosting
{
    public function __construct(
        private readonly MoneyPosting $money,
        private readonly EvidenceGuard $evidence,
        private readonly CorrectionCases $cases,
        private readonly Corrections $corrections,
        private readonly Revalidation $revalidation,
    ) {}

    /** AX-33's set, data-dependent: the fact's guard, a purchase's header (LR-10), a tax remittance's evidence, a refund's sources, cases and projects. */
    public static function disbursementLocks(int $disbursementId): LockSet
    {
        $set = (new LockSet)->add('money_fact_balances', ['disbursement_id' => $disbursementId]);
        $row = DB::table('disbursements')->where('id', $disbursementId)->first(['purchase_id', 'tax_evidence_id']);

        if ($row === null) {
            return $set;
        }

        $purchase = Row::nullable($row, 'purchase_id');
        $evidence = Row::nullable($row, 'tax_evidence_id');
        $set = $purchase === null ? $set : $set->add('purchases', ['id' => (int) $purchase]);
        $set = $evidence === null ? $set : $set->union(EvidenceGuard::locks((int) $evidence));
        $case = MoneyFactBalance::query()->toBase()->where('disbursement_id', $disbursementId)->value('residual_case_id');
        $set = is_numeric($case) ? $set->union(CorrectionCases::locks((int) $case)) : $set;

        foreach (DB::table('payment_applications')->where('refund_disbursement_id', $disbursementId)->whereRaw("(state = 'ACTIVE' or end_reason = 'SOURCE_CONTRA')")->orderBy('id')->get(['payment_id', 'opening_customer_credit_id']) as $application) {
            $set = $set->add('application_source_balances', PaymentApplications::sourceKey($application));
        }

        return $set;
    }

    public static function expenseLocks(int $expenseId): LockSet
    {
        $set = (new LockSet)->add('money_fact_balances', ['expense_id' => $expenseId]);
        $row = DB::table('expenses')->where('id', $expenseId)->first(['project_id', 'source_bill_evidence_id']);
        $project = $row === null ? null : Row::nullable($row, 'project_id');
        $bill = $row === null ? null : Row::nullable($row, 'source_bill_evidence_id');
        $set = $project === null ? $set : $set->add('projects', ['id' => (int) $project]);
        $set = $bill === null ? $set : $set->union(EvidenceGuard::locks((int) $bill));

        foreach (DB::table('disbursements')->where('expense_id', $expenseId)->orderBy('id')->pluck('id') as $payment) {
            if (is_numeric($payment)) {
                $set = $set->add('money_fact_balances', ['disbursement_id' => (int) $payment]);
            }
        }

        return $set;
    }

    public function contraExpense(CommandContext $context, int $expenseId, string $amount, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $row = DB::table('expenses')->where('id', $expenseId)->first(['company_id', 'project_id', 'kind', 'source_bill_evidence_id']);

        if ($row === null) {
            throw Refused::rejected('C-45', ['reason' => 'unknown']);
        }

        // C-23; CM-17: what a settlement or a charge made is corrected there.
        if (Row::text($row, 'kind') !== 'MANUAL') {
            throw Refused::rejected('C-23', ['reason' => 'not_manual']);
        }

        $moved = $this->ensureWithin(['expense_id' => $expenseId], $amount);

        // AX-33: its dependent first — money paid for it stands until that disbursement is contra'd.
        if (self::paid($expenseId)) {
            throw Refused::rejected('C-45', ['reason' => 'expense_paid']);
        }

        $company = (int) Row::text($row, 'company_id');
        $this->insert($context, $company, ['expense_id' => $expenseId], $moved, $reason, $businessDate, null, null, 1);
        $this->money->moveFact($context, ['expense_id' => $expenseId], ['contra_amount' => $moved->toString()]);
        $bill = Row::nullable($row, 'source_bill_evidence_id');

        if ($bill !== null) {
            $this->evidence->cite($context, (int) $bill, 'CHARGE_SOURCE_BILL', $moved->negated()->toString());
        }

        $project = Row::nullable($row, 'project_id');
        $handle = RowHandle::of('expenses', $expenseId);

        if ($project !== null) {
            $this->revalidation->revalidate($context, (int) $project, "FIN.ExpenseContraed:{$handle}");
        }

        // An expense has no public identifier of its own: the record it corrects through is its project's, or its company's.
        $owner = $project === null ? ['companies', $company] : ['projects', (int) $project];
        $publicId = DB::table($owner[0])->where('id', $owner[1])->value('public_id');
        $this->corrections->record($context, 'FIN.ExpenseContraed', AuthorityClass::AdmPlus, $company, 'expense', $expenseId, $handle, AuditRelation::Reverses, $project === null ? 'company' : 'project', is_string($publicId) ? $publicId : '', $reason,
            AuditChange::updated([], ['contra' => $moved->toString()]), null, ['business_date' => $businessDate]);
    }

    public function contraDisbursement(CommandContext $context, int $disbursementId, string $amount, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $row = DB::table('disbursements')->where('id', $disbursementId)->first(['company_id', 'public_id', 'kind', 'tax_evidence_id']);

        if ($row === null) {
            throw Refused::rejected('C-45', ['reason' => 'unknown']);
        }

        $moved = $this->ensureWithin(['disbursement_id' => $disbursementId], $amount);
        $company = (int) Row::text($row, 'company_id');
        $publicId = Row::text($row, 'public_id');

        if (Row::text($row, 'kind') === 'REFUND') {
            $this->contraRefund($context, $company, $disbursementId, $moved, $reason, $businessDate);
        } else {
            if (Row::text($row, 'kind') === 'INTERCOMPANY_TRANSFER_OUT') {
                $this->ensureTransferRoom($disbursementId, $moved);
            }

            $this->insert($context, $company, ['disbursement_id' => $disbursementId], $moved, $reason, $businessDate, null, null, 1);
            $this->money->moveFact($context, ['disbursement_id' => $disbursementId], ['contra_amount' => $moved->toString()]);
            $evidence = Row::nullable($row, 'tax_evidence_id');

            if ($evidence !== null) {
                $this->evidence->cite($context, (int) $evidence, 'TAX_REMITTANCE', $moved->negated()->toString());
            }
        }

        $this->corrections->record($context, 'FIN.DisbursementContraed', AuthorityClass::AdmPlus, $company, 'disbursement', $disbursementId, $publicId, AuditRelation::Reverses, 'disbursement', $publicId, $reason,
            AuditChange::updated([], ['contra' => $moved->toString(), 'kind' => Row::text($row, 'kind')]), null, ['business_date' => $businessDate]);
    }

    private static function paid(int $expenseId): bool
    {
        return DB::table('disbursements as d')->join('money_fact_balances as f', 'f.disbursement_id', '=', 'd.id')->where('d.expense_id', $expenseId)->whereColumn('f.contra_amount', '<', 'f.amount')->exists();
    }

    /**
     * A replayed refusal's reason and current figure (CI-05).
     *
     * @return array<string, string>|null
     */
    public static function replayState(Outcome $outcome, bool $expense, int $factId): ?array
    {
        $key = $expense ? ['expense_id' => $factId] : ['disbursement_id' => $factId];

        return match ($outcome->code) {
            'C-60' => ['reason' => 'residual'],
            'C-23' => ['reason' => 'not_manual'],
            'C-44' => ['reason' => 'unbacked'],
            'C-45' => self::netState($expense, $factId, $key),
            default => null,
        };
    }

    /**
     * @param  array<string, int>  $key
     * @return array<string, string>
     */
    private static function netState(bool $expense, int $factId, array $key): array
    {
        if ($expense && self::paid($factId)) {
            return ['reason' => 'expense_paid'];
        }

        $guard = MoneyFactBalance::query()->toBase()->where($key)->first(['amount', 'contra_amount', 'transfer_cited_amount']);
        $net = $guard === null ? Money::zero() : Money::of(Row::text($guard, 'amount'))->minus(Money::of(Row::text($guard, 'contra_amount')));
        $cited = $guard === null ? Money::zero() : Money::of(Row::text($guard, 'transfer_cited_amount'));

        return $cited->isPositive() ? ['reason' => 'transfer_cited', 'net' => $net->minus($cited)->toString()] : ['reason' => 'above_net', 'net' => $net->toString()];
    }

    /**
     * Shortfall first, each row naming the ended application it resolves; the rest released from the backing, newest first (L-46; C-44; M-14).
     */
    private function contraRefund(CommandContext $context, int $company, int $refundId, Money $amount, string $reason, string $businessDate): void
    {
        $line = 0;
        $left = $amount;
        $fact = ['contra_amount' => $amount];
        $fact['shortfall_amount'] = Money::zero();
        $fact['backed_amount'] = Money::zero();
        $fact['released_amount'] = Money::zero();
        $guard = MoneyFactBalance::query()->toBase()->where('disbursement_id', $refundId)->first(['shortfall_amount', 'residual_case_id']);
        $shortfall = $guard === null ? Money::zero() : Money::of(Row::text($guard, 'shortfall_amount'));
        $case = $guard === null ? null : Row::nullable($guard, 'residual_case_id');

        foreach (DB::table('payment_applications')->where('refund_disbursement_id', $refundId)->where('end_reason', 'SOURCE_CONTRA')->orderBy('id')->get(['id', 'amount', 'payment_id', 'opening_customer_credit_id']) as $ended) {
            if (! $left->isPositive() || ! $shortfall->isPositive()) {
                break;
            }

            $source = PaymentApplications::sourceKey($ended);
            $held = MoneyPosting::source($source);
            $part = Money::min(Money::min($left, $shortfall), Money::min(Money::of(Row::text($ended, 'amount')), $held === null ? Money::zero() : Money::of(Row::text($held, 'refund_shortfall_amount'))));

            if (! $part->isPositive()) {
                continue;
            }

            $this->insert($context, $company, ['disbursement_id' => $refundId], $part, $reason, $businessDate, (int) Row::text($ended, 'id'), $case === null ? null : (int) $case, ++$line);
            $this->money->moveSource($context, $source, ['refund_shortfall_amount' => $part->negated()->toString()]);
            $fact['shortfall_amount'] = $fact['shortfall_amount']->plus($part);
            $left = $left->minus($part);
            $shortfall = $shortfall->minus($part);
        }

        $resolved = $fact['shortfall_amount'];

        if ($left->isPositive()) {
            $contra = $this->insert($context, $company, ['disbursement_id' => $refundId], $left, $reason, $businessDate, null, $case === null ? null : (int) $case, ++$line);
            $this->release($context, $refundId, $contra, $left, $reason, $businessDate, $line);
            $fact['backed_amount'] = $left;
            $fact['released_amount'] = $left;
        }

        $this->money->moveFact($context, ['disbursement_id' => $refundId], [
            'contra_amount' => $amount->toString(),
            'shortfall_amount' => $resolved->negated()->toString(),
            'backed_amount' => $fact['backed_amount']->negated()->toString(),
            'released_amount' => $fact['released_amount']->toString(),
        ]);

        if ($resolved->isPositive() && $case !== null) {
            $this->cases->post($context, (int) $case, ['unbacked_refund_amount' => $resolved->negated()->toString()], $reason);
        }
    }

    /** The backing released to credit up to the amount, newest first: whole rows RELEASED, a part superseded by what stays. */
    private function release(CommandContext $context, int $refundId, int $contra, Money $amount, string $reason, string $businessDate, int $line): void
    {
        $left = $amount;
        $commandId = $context->request->commandId;

        foreach (DB::table('payment_applications')->where('refund_disbursement_id', $refundId)->where('state', 'ACTIVE')->orderByDesc('id')->get() as $row) {
            if (! $left->isPositive()) {
                break;
            }

            $whole = Money::of(Row::text($row, 'amount'));
            $part = Money::min($left, $whole);
            $partial = $part->compareTo($whole) < 0;
            $id = (int) Row::text($row, 'id');
            $source = PaymentApplications::sourceKey($row);
            $ended = DB::table('payment_applications')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => $partial ? 'SUPERSEDED' : 'RELEASED', 'end_reason' => 'REFUND_CONTRA', 'end_contra_id' => $contra, 'end_command_id' => $commandId]);

            if ($ended !== 1) {
                throw Refused::conflict('ST-07', ['reason' => 'backing_moved']);
            }

            if ($partial) {
                DB::table('payment_applications')->insert([...$source, 'company_id' => (int) Row::text($row, 'company_id'), 'client_organization_id' => (int) Row::text($row, 'client_organization_id'), 'refund_disbursement_id' => $refundId, 'target_kind' => 'REFUND',
                    'amount' => $whole->minus($part)->toString(), 'supersedes_application_id' => $id, 'business_date' => $businessDate, 'reason' => $reason, 'command_id' => $commandId, 'command_line_no' => ++$line, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            }

            $this->money->moveSource($context, $source, ['refund_backed_amount' => $part->negated()->toString(), 'refund_released_amount' => $part->toString()]);
            $left = $left->minus($part);
        }

        if ($left->isPositive()) {
            throw Refused::rejected('C-44', ['reason' => 'unbacked']);
        }
    }

    /**
     * Σ contras ≤ the original (C-45).
     *
     * @param  array<string, int>  $key
     */
    private function ensureWithin(array $key, string $amount): Money
    {
        $moved = Money::of($amount);
        $guard = MoneyFactBalance::query()->toBase()->where($key)->first(['amount', 'contra_amount']);
        $net = $guard === null ? Money::zero() : Money::of(Row::text($guard, 'amount'))->minus(Money::of(Row::text($guard, 'contra_amount')));

        if (! $moved->isPositive() || $moved->compareTo($net) > 0) {
            throw Refused::rejected('C-45', ['reason' => 'above_net', 'net' => $net->toString()]);
        }

        return $moved;
    }

    /** A transfer-out backs Cash-In up to its net amount only (BR-FIN-07 b). */
    private function ensureTransferRoom(int $disbursementId, Money $amount): void
    {
        $guard = MoneyFactBalance::query()->toBase()->where('disbursement_id', $disbursementId)->first(['amount', 'contra_amount', 'transfer_cited_amount']);
        $room = $guard === null ? Money::zero() : Money::of(Row::text($guard, 'amount'))->minus(Money::of(Row::text($guard, 'contra_amount')))->minus(Money::of(Row::text($guard, 'transfer_cited_amount')));

        if ($amount->compareTo($room) > 0) {
            throw Refused::rejected('C-45', ['reason' => 'transfer_cited', 'net' => $room->toString()]);
        }
    }

    /** @param  array<string, int>  $original */
    private function insert(CommandContext $context, int $company, array $original, Money $amount, string $reason, string $businessDate, ?int $resolves, ?int $caseId, int $line): int
    {
        return DB::table('financial_contras')->insertGetId([...$original, 'company_id' => $company, 'amount' => $amount->toString(), 'resolves_application_id' => $resolves, 'reason' => $reason, 'business_date' => $businessDate, 'correction_case_id' => $caseId,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $context->now()]);
    }
}
