<?php

namespace App\Modules\FIN\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\FIN\Models\InvoiceBalance;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The deduction settlement and its exits (DATABASE §11.4; AX-21; AX-33; SF-SETTLE; L-28; L-45; C-15; C-18; C-22; C-23; C-60), one transaction moving the invoice, the payment's source and the evidence together or their
 * CHECKs refusing the commit. It creates no Cash-In and no application, and the gross stays. A fee is a project expense exactly once; a tax only through a rule that says so (BR-FIN-09; BR-FIN-16). ACTIVE → CONTRAED is
 * CM-22's contra, RESIDUAL → CONTRAED the resolution of what a void or a contra left (CM-24; ROUTE-263). A contra raises the outstanding, so it revalidates a completed project (SF-REVAL) and unlinks a Kuitansi whose total fell (AX-31).
 */
final class SettlementPosting
{
    public function __construct(
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
        private readonly EvidenceGuard $evidence,
        private readonly SettlementMoves $moves,
        private readonly CostPosting $cost,
        private readonly CorrectionCases $cases,
        private readonly Corrections $corrections,
        private readonly PrepaymentKuitansi $kuitansi,
        private readonly Revalidation $revalidation,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-21's set: the invoice with its project, the payment's source, the evidence (BD-08; M-37). */
    public static function locks(int $invoiceId, int $paymentId, int $evidenceId): LockSet
    {
        return PaymentApplications::invoiceLocks([$invoiceId])->add('application_source_balances', ['payment_id' => $paymentId])->union(EvidenceGuard::locks($evidenceId));
    }

    /** AX-33's set for a settlement's contra or resolution, data-dependent: its invoice, source, evidence and guards, and a resolution's case. */
    public static function contraLocks(int $settlementId): LockSet
    {
        $row = DB::table('deduction_settlements')->where('id', $settlementId)->first(['invoice_id', 'payment_id', 'evidence_document_id', 'correction_case_id']);

        if ($row === null) {
            return new LockSet;
        }

        $case = Row::nullable($row, 'correction_case_id');
        $set = self::locks((int) Row::text($row, 'invoice_id'), (int) Row::text($row, 'payment_id'), (int) Row::text($row, 'evidence_document_id'))->union(SettlementMoves::locks($settlementId));

        return $case === null ? $set : $set->union(CorrectionCases::locks((int) $case));
    }

    /**
     * The settlement recorded; its id.
     *
     * @param  array{id: int, treatment: string}|null  $treatment  a tax's rule in force on the business date
     */
    public function record(CommandContext $context, SettlementEntry $entry, int $companyId, int $clientId, ?array $treatment): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $amount = Money::of($entry->amount);

        // BD-08 first: a repeated settlement is already recorded, whatever room is left now.
        if (self::duplicate($entry->paymentId, $entry->invoiceId, $entry->class, $entry->specificType())) {
            throw Refused::conflict('BD-08', []);
        }

        $this->ensureAnchors($entry->invoiceId, $entry->paymentId, $amount, $companyId, $clientId, $entry->class, $entry->direction);
        $this->ensureEvidence($context, $entry->evidenceId, $companyId, $entry->class, $entry->direction, $amount);

        if ($entry->class === SettlementEntry::TAX && $treatment === null) {
            throw Refused::rejected('BR-FIN-16', ['reason' => 'no_rule']);
        }

        $expenseKind = $entry->class === SettlementEntry::FEE ? 'FEE_DEDUCTION' : (($treatment['treatment'] ?? null) === 'SETTLEMENT_TO_EXPENSE' ? 'TAX_TREATMENT' : null);

        if ($expenseKind !== null) {
            $this->ensureExpensable($entry->evidenceId, $amount);
        }

        $now = $context->now();
        $id = DB::table('deduction_settlements')->insertGetId([
            'company_id' => $companyId, 'client_organization_id' => $clientId, 'invoice_id' => $entry->invoiceId, 'payment_id' => $entry->paymentId, 'settlement_class' => $entry->class, 'tax_type_id' => $entry->taxTypeId,
            'fee_type' => $entry->feeType, 'tax_direction' => $entry->direction, 'specific_type_key' => $entry->specificType(), 'treatment_rule_id' => $treatment['id'] ?? null, 'amount' => $amount->toString(),
            'evidence_document_id' => $entry->evidenceId, 'evidence_type' => SettlementEntry::evidenceType($entry->class, $entry->direction), 'business_date' => $entry->businessDate, 'state' => 'ACTIVE',
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['deduction_settlement_id' => $id], ['company_id' => $companyId, 'fact_kind' => 'SETTLEMENT', 'amount' => $amount->toString(), 'recorded_at' => $now]);
        $this->receivable->move($context, $entry->invoiceId, self::legs($entry->class, $entry->direction, $amount));
        $this->money->moveSource($context, ['payment_id' => $entry->paymentId], ['settled_amount' => $amount->toString()]);
        $this->evidence->cite($context, $entry->evidenceId, $entry->role(), $amount->toString());

        if ($expenseKind !== null) {
            $this->cost->settlementExpense($context, $id, $expenseKind, SettlementMoves::projectOf($entry->invoiceId), $entry->businessDate);
            $this->evidence->cite($context, $entry->evidenceId, self::expenseRole($expenseKind), $amount->toString());
        }

        $this->audit->record(action: 'FIN.DeductionSettled', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: $companyId, entityType: 'deduction_settlement', entityId: $id, entityPublicId: RowHandle::of('deduction_settlements', $id),
            reason: $entry->reason, commandId: $context->request->commandId, change: AuditChange::created(['class' => $entry->class, 'type' => $entry->specificType(), 'amount' => $amount->toString(), 'expense' => $expenseKind]),
            metadata: ['invoice' => RowHandle::of('invoices', $entry->invoiceId), 'payment' => RowHandle::of('payments', $entry->paymentId), 'evidence' => RowHandle::of('evidence_documents', $entry->evidenceId), 'business_date' => $entry->businessDate]);

        return $id;
    }

    /** CM-22: an ACTIVE settlement contra'd, its invoice and payment legs and its citation lowered, its expense with it; re-recorded afterwards as its own command. */
    public function contra(CommandContext $context, int $settlementId, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $row = self::row($settlementId);
        $state = Row::text($row, 'state');

        if ($state === 'RESIDUAL') {
            throw Refused::rejected('C-60', ['reason' => 'residual']);
        }

        if ($state !== 'ACTIVE') {
            throw Refused::conflict('ST-02', ['state' => $state]);
        }

        $companyId = (int) Row::text($row, 'company_id');
        $invoiceId = (int) Row::text($row, 'invoice_id');
        $amount = Money::of(Row::text($row, 'amount'));
        $class = Row::text($row, 'settlement_class');
        $direction = Row::nullable($row, 'tax_direction');
        $evidence = (int) Row::text($row, 'evidence_document_id');
        $this->end($context, $row, 'ACTIVE', $reason, $businessDate, null);
        $this->receivable->move($context, $invoiceId, self::legs($class, $direction, $amount->negated()));
        $this->money->moveSource($context, ['payment_id' => (int) Row::text($row, 'payment_id')], ['settled_amount' => $amount->negated()->toString()]);
        $this->evidence->cite($context, $evidence, SettlementEntry::roleOf($class, $direction), $amount->negated()->toString());
        $this->releaseExpense($context, $companyId, $settlementId, $evidence, $reason, $businessDate, null);
        $this->kuitansi->reconcile($context, $invoiceId, 'invoices.unlink.corrected');
        $handle = RowHandle::of('deduction_settlements', $settlementId);
        $project = SettlementMoves::projectOf($invoiceId);

        if ($project !== null) {
            $this->revalidation->revalidate($context, $project, "FIN.SettlementContraed:{$handle}");
        }

        $this->corrections->record($context, 'FIN.SettlementContraed', AuthorityClass::AdmPlus, $companyId, 'deduction_settlement', $settlementId, $handle, AuditRelation::Reverses, 'payment', self::paymentPublicId((int) Row::text($row, 'payment_id')), $reason,
            AuditChange::updated(['state' => 'ACTIVE', 'amount' => $amount->toString()], ['state' => 'CONTRAED']), null, ['business_date' => $businessDate]);
    }

    /**
     * RESIDUAL → CONTRAED, once (DATABASE §11.4; C-60): re-recorded on the invoice its evidence covers by a new ACTIVE settlement in the same command, or contra'd on the issuer's evidence — the case's residual falling by its amount.
     *
     * @return int the new settlement's id, or the resolved one's where nothing is re-recorded
     */
    public function resolve(CommandContext $context, int $settlementId, ?int $invoiceId, ?int $paymentId, ?int $issuerEvidenceId, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $row = self::row($settlementId);
        $state = Row::text($row, 'state');

        if ($state === 'CONTRAED') {
            throw Refused::conflict('ST-02', ['state' => $state]);
        }

        if ($state !== 'RESIDUAL') {
            throw Refused::rejected('C-60', ['reason' => 'not_residual']);
        }

        $companyId = (int) Row::text($row, 'company_id');
        $clientId = (int) Row::text($row, 'client_organization_id');
        $caseId = (int) Row::text($row, 'correction_case_id');
        $amount = Money::of(Row::text($row, 'amount'));
        $class = Row::text($row, 'settlement_class');
        $direction = Row::nullable($row, 'tax_direction');
        $evidence = (int) Row::text($row, 'evidence_document_id');

        if ($invoiceId !== null && $paymentId !== null) {
            if (self::duplicate($paymentId, $invoiceId, $class, Row::text($row, 'specific_type_key'))) {
                throw Refused::conflict('BD-08', []);
            }

            $this->ensureAnchors($invoiceId, $paymentId, $amount, $companyId, $clientId, $class, $direction);
        } elseif ($issuerEvidenceId === null || $issuerEvidenceId === $evidence) {
            // The issuer's evidence that it was refunded or cancelled is another document than the one it cites.
            throw Refused::rejected('C-60', ['reason' => 'no_issuer_evidence']);
        }

        $this->end($context, $row, 'RESIDUAL', $reason, $businessDate, $caseId);
        $this->cases->post($context, $caseId, ['residual_settlement_amount' => $amount->negated()->toString()], $reason);
        $result = $settlementId;

        if ($invoiceId !== null && $paymentId !== null) {
            // The citation stands and the expense moves once with the new settlement: the evidence nets to nothing (§11.4).
            $result = $this->moves->successor($context, $companyId, $row, $invoiceId, $paymentId, $businessDate, 1);
            $this->receivable->move($context, $invoiceId, self::legs($class, $direction, $amount));
            $this->money->moveSource($context, ['payment_id' => $paymentId], ['settled_amount' => $amount->toString()]);
            $moved = $this->moves->contraExpense($context, $companyId, $settlementId, $reason, $businessDate, $caseId, 1);

            if ($moved !== null) {
                $this->cost->settlementExpense($context, $result, $moved['kind'], SettlementMoves::projectOf($invoiceId), $businessDate);
            }
        } else {
            $this->evidence->cite($context, $evidence, SettlementEntry::roleOf($class, $direction), $amount->negated()->toString());
            $this->releaseExpense($context, $companyId, $settlementId, $evidence, $reason, $businessDate, $caseId);
            $this->linkIssuerEvidence($context, $settlementId, (int) $issuerEvidenceId, $companyId);
        }

        $handle = RowHandle::of('deduction_settlements', $settlementId);
        $this->corrections->record($context, 'FIN.ResidualSettlementResolved', AuthorityClass::AdmPlus, $companyId, 'deduction_settlement', $settlementId, $handle, AuditRelation::Supersedes, 'payment', self::paymentPublicId((int) Row::text($row, 'payment_id')), $reason,
            AuditChange::updated(['state' => 'RESIDUAL', 'amount' => $amount->toString()], ['state' => 'CONTRAED', 're_recorded' => $result !== $settlementId]), null, ['business_date' => $businessDate, 'case' => RowHandle::of('correction_cases', $caseId)]);

        return $result;
    }

    /**
     * A replayed refusal's reason and current figure (CI-05): the recorded outcome keeps its code, so what it concerns is read again.
     *
     * @return array<string, string>|null
     */
    public static function replayState(Outcome $outcome, ?int $invoiceId, ?int $paymentId, string $amount, ?int $evidenceId, string $class, ?string $direction): ?array
    {
        $balance = $evidenceId === null ? null : DB::selectOne('select proven_amount - cited_amount as uncited, proven_amount - expensed_amount as room from evidence_balances where evidence_document_id = ?', [$evidenceId]);

        return match ($outcome->code) {
            'C-15' => $invoiceId === null ? null : (DB::table('invoices')->where('id', $invoiceId)->value('state') === 'VOIDED' ? ['reason' => 'voided', 'outstanding' => '0.00'] : PaymentApplications::outstandingRefusal([$invoiceId => $amount])),
            'C-18' => ['reason' => 'above_evidence', 'uncited' => is_object($balance) ? Row::text($balance, 'uncited') : '0.00'],
            'C-23' => ['reason' => 'above_expensed', 'room' => is_object($balance) ? Row::text($balance, 'room') : '0.00'],
            'C-22' => self::claimState($invoiceId, $paymentId, $amount, $class, $direction),
            'C-45' => ['reason' => 'contraed'],
            default => null,
        };
    }

    /** @return array<string, string> */
    private static function claimState(?int $invoiceId, ?int $paymentId, string $amount, string $class, ?string $direction): array
    {
        if (! Money::of($amount)->isPositive()) {
            return ['reason' => 'amount'];
        }

        $source = $paymentId === null ? null : MoneyPosting::source(['payment_id' => $paymentId]);
        $room = $source === null ? Money::zero() : Money::of(Row::text($source, 'claimed_deductions_amount'))->minus(Money::of(Row::text($source, 'settled_amount')));

        if ($class === SettlementEntry::TAX && $direction === SettlementEntry::COLLECTED && $invoiceId !== null && Money::of($amount)->compareTo($room) <= 0) {
            $balance = InvoiceBalance::query()->toBase()->where('invoice_id', $invoiceId)->first(['output_tax_value', 'collected_tax_settled_amount']);

            return ['reason' => 'collected_above_tax', 'tax' => $balance === null ? '0.00' : Money::of(Row::text($balance, 'output_tax_value'))->minus(Money::of(Row::text($balance, 'collected_tax_settled_amount')))->toString()];
        }

        return ['reason' => 'above_claimed', 'claimed' => $room->toString()];
    }

    /**
     * The invoice-balance columns a settlement moves.
     *
     * @return array<string, string>
     */
    public static function legs(string $class, ?string $direction, Money $amount): array
    {
        return [
            $class === SettlementEntry::FEE ? 'fee_settled_amount' : 'tax_settled_amount' => $amount->toString(),
            ...($class === SettlementEntry::TAX && $direction === SettlementEntry::COLLECTED ? ['collected_tax_settled_amount' => $amount->toString()] : []),
        ];
    }

    /** One ACTIVE settlement per payment, invoice, class and specific type (BD-08). */
    private static function duplicate(int $paymentId, int $invoiceId, string $class, string $key): bool
    {
        return DB::table('deduction_settlements')->where('payment_id', $paymentId)->where('invoice_id', $invoiceId)->where('settlement_class', $class)->where('specific_type_key', $key)->where('state', 'ACTIVE')->exists();
    }

    /** The refusals by name, before the CHECKs would hold them: same client and company, the invoice issued with room, the payment standing with its claim, collected tax within the invoice's. */
    private function ensureAnchors(int $invoiceId, int $paymentId, Money $amount, int $companyId, int $clientId, string $class, ?string $direction): void
    {
        if (! $amount->isPositive()) {
            throw Refused::rejected('C-22', ['reason' => 'amount']);
        }

        PaymentApplications::ensureTargets([$invoiceId => $amount->toString()], $companyId, $clientId);
        $source = MoneyPosting::source(['payment_id' => $paymentId]);

        if ($source === null || (int) Row::text($source, 'company_id') !== $companyId || (int) Row::text($source, 'client_organization_id') !== $clientId) {
            throw Refused::rejected('C-14', ['reason' => 'other_party']);
        }

        if (! Money::of(Row::text($source, 'net_amount'))->isPositive()) {
            throw Refused::rejected('C-45', ['reason' => 'contraed']);
        }

        $room = Money::of(Row::text($source, 'claimed_deductions_amount'))->minus(Money::of(Row::text($source, 'settled_amount')));

        if ($amount->compareTo($room) > 0) {
            throw Refused::rejected('C-22', ['reason' => 'above_claimed', 'claimed' => $room->toString()]);
        }

        if ($class === SettlementEntry::TAX && $direction === SettlementEntry::COLLECTED) {
            $balance = InvoiceBalance::query()->toBase()->where('invoice_id', $invoiceId)->first(['output_tax_value', 'collected_tax_settled_amount']);
            $left = $balance === null ? Money::zero() : Money::of(Row::text($balance, 'output_tax_value'))->minus(Money::of(Row::text($balance, 'collected_tax_settled_amount')));

            if ($amount->compareTo($left) > 0) {
                throw Refused::rejected('C-22', ['reason' => 'collected_above_tax', 'tax' => $left->toString()]);
            }
        }
    }

    /** The evidence of the same company and of the type the class takes, with room (C-18; C-32; C-58). */
    private function ensureEvidence(CommandContext $context, int $evidenceId, int $companyId, string $class, ?string $direction, Money $amount): void
    {
        $row = DB::table('evidence_documents')->where('id', $evidenceId)->first(['company_id', 'evidence_type']);

        if ($row === null || (int) Row::text($row, 'company_id') !== $companyId) {
            throw Refused::rejected('C-32', ['reason' => 'evidence_company']);
        }

        if (Row::text($row, 'evidence_type') !== SettlementEntry::evidenceType($class, $direction)) {
            throw Refused::rejected('C-58', ['reason' => 'evidence_type']);
        }

        $uncited = $this->evidence->uncited($context, $evidenceId);

        if ($amount->compareTo(Money::of($uncited)) > 0) {
            throw Refused::rejected('C-18', ['reason' => 'above_evidence', 'uncited' => $uncited]);
        }
    }

    /** A fee or a tax expensed at most its evidence, however often its settlement is re-recorded (C-23). */
    private function ensureExpensable(int $evidenceId, Money $amount): void
    {
        $balance = DB::selectOne('select proven_amount - expensed_amount as room from evidence_balances where evidence_document_id = ?', [$evidenceId]);
        $room = is_object($balance) ? Row::text($balance, 'room') : '0.00';

        if ($amount->compareTo(Money::of($room)) > 0) {
            throw Refused::rejected('C-23', ['reason' => 'above_expensed', 'room' => $room]);
        }
    }

    /** The settlement's end: its state once, its contra and money-fact guard. */
    private function end(CommandContext $context, object $row, string $from, string $reason, string $businessDate, ?int $caseId): void
    {
        $id = (int) Row::text($row, 'id');
        $amount = Row::text($row, 'amount');

        if (DB::table('deduction_settlements')->where('id', $id)->where('state', $from)->update(['state' => 'CONTRAED']) !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'CONTRAED']);
        }

        DB::table('financial_contras')->insert(['company_id' => (int) Row::text($row, 'company_id'), 'deduction_settlement_id' => $id, 'amount' => $amount, 'reason' => $reason, 'business_date' => $businessDate, 'correction_case_id' => $caseId,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now()]);
        $this->money->moveFact($context, ['deduction_settlement_id' => $id], ['contra_amount' => $amount]);
    }

    private function releaseExpense(CommandContext $context, int $companyId, int $settlementId, int $evidence, string $reason, string $businessDate, ?int $caseId): void
    {
        $released = $this->moves->contraExpense($context, $companyId, $settlementId, $reason, $businessDate, $caseId, 1);

        if ($released !== null) {
            $this->evidence->cite($context, $evidence, self::expenseRole($released['kind']), Money::of($released['amount'])->negated()->toString());
        }
    }

    private static function expenseRole(string $kind): string
    {
        return $kind === 'FEE_DEDUCTION' ? 'FEE_DEDUCTION_EXPENSE' : 'TAX_TREATMENT_EXPENSE';
    }

    /** The issuer's evidence that the deduction was refunded or cancelled, linked to the settlement it ends (DATABASE §11.4; §4.10's arc). */
    private function linkIssuerEvidence(CommandContext $context, int $settlementId, int $evidenceId, int $companyId): void
    {
        $company = DB::table('evidence_documents')->where('id', $evidenceId)->value('company_id');

        if (! is_numeric($company) || (int) $company !== $companyId) {
            throw Refused::rejected('C-32', ['reason' => 'evidence_company']);
        }

        DB::table('evidence_links')->insert(['evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'deduction_settlement_id' => $settlementId, 'role' => 'SUPPORTING',
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
    }

    /** A settlement has no public identifier of its own: the record it corrects through is its payment's (L-28). */
    private static function paymentPublicId(int $paymentId): string
    {
        $id = DB::table('payments')->where('id', $paymentId)->value('public_id');

        return is_string($id) ? $id : '';
    }

    private static function row(int $settlementId): object
    {
        return DB::table('deduction_settlements')->where('id', $settlementId)->first() ?? throw Refused::rejected('C-60', ['reason' => 'unknown']);
    }
}
