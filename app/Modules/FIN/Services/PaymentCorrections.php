<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Data\SourcePosition;
use App\Modules\FIN\Models\MoneyFactBalance;
use App\Modules\FIN\Models\Payment;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The corrections of a payment fact, one transaction moving every guard together or their CHECKs refusing the commit: CM-21's reallocation (AX-19; L-46; L-28) — the old application ended
 * once, its money on the new target and, for a part, its rest where it was, the payment total unchanged, the settlements anchored to it moved or kept in the same action — and CM-19's contra
 * (AX-20; SF-CONTRA; ST-07; C-44; C-45): a `financial_contras` row lowering the fact's net money with the disposition of everything it backs, invoice applications released or moved to the
 * re-recorded payment, a refund's backing moved only to that payment or, where the source can truly no longer back it, a typed shortfall on the PAYMENT_CORRECTION case the command opens. No disposition
 * frees capacity real money does not support. What raises an outstanding revalidates a completed project (SF-REVAL) and unlinks a Kuitansi whose total fell (AX-31).
 */
final class PaymentCorrections
{
    public function __construct(
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
        private readonly SettlementMoves $settlements,
        private readonly CorrectionCases $cases,
        private readonly Corrections $corrections,
        private readonly PrepaymentKuitansi $kuitansi,
        private readonly Revalidation $revalidation,
        private readonly EvidenceGuard $evidence,
    ) {}

    /**
     * AX-19's set for a move: the old and new invoices with their projects, the source, the application, the anchored settlements' rows and guards.
     *
     * @param  list<int>  $settlementIds
     */
    public static function reallocationLocks(int $paymentId, int $applicationId, int $targetInvoiceId, array $settlementIds): LockSet
    {
        $old = DB::table('payment_applications')->where('id', $applicationId)->value('invoice_id');
        $set = PaymentApplications::invoiceLocks(array_values(array_filter([is_numeric($old) ? (int) $old : null, $targetInvoiceId])))
            ->add('application_source_balances', ['payment_id' => $paymentId])
            ->add('payment_applications', ['id' => $applicationId]);

        foreach ($settlementIds as $settlement) {
            $set = $set->add('deduction_settlements', ['id' => $settlement])->add('money_fact_balances', ['deduction_settlement_id' => $settlement]);
        }

        return $set;
    }

    /**
     * Moves `$amount` of the application to the target.
     *
     * @param  array<int, bool>  $settlements  settlement => moved with it (true), kept with the command's reason (false)
     */
    public function reallocate(CommandContext $context, Payment $payment, int $applicationId, int $targetInvoiceId, string $amount, array $settlements, string $businessDate, bool $prefilled, string $reason): int
    {
        $application = DB::table('payment_applications')->where('id', $applicationId)->where('payment_id', $payment->id)->first();

        if ($application === null) {
            throw Refused::rejected('C-14', ['reason' => 'other_party']);
        }

        // A refund-consumed application is final while its refund stands (L-46).
        if (Row::text($application, 'target_kind') === 'REFUND') {
            throw Refused::rejected('L-46', ['reason' => 'refund_consumed']);
        }

        if (Row::text($application, 'state') !== 'ACTIVE') {
            throw Refused::conflict('ST-02', ['state' => Row::text($application, 'state')]);
        }

        BusinessDateRule::check($businessDate, $prefilled);
        $oldInvoice = (int) Row::text($application, 'invoice_id');
        $moved = Money::of($amount);
        $whole = Money::of(Row::text($application, 'amount'));

        if ($oldInvoice === $targetInvoiceId) {
            throw Refused::rejected('CM-21', ['reason' => 'same_invoice']);
        }

        if (! $moved->isPositive() || $moved->compareTo($whole) > 0) {
            throw Refused::rejected('C-13', ['reason' => 'above_application', 'application' => $whole->toString()]);
        }

        $current = SettlementMoves::anchored($payment->id, $oldInvoice);

        if (array_diff(array_keys($current), array_keys($settlements)) !== [] || array_diff(array_keys($settlements), array_keys($current)) !== []) {
            throw Refused::conflict('ST-07', ['settlements' => array_map(fn (int $id): string => RowHandle::of('deduction_settlements', $id), array_keys($current))]);
        }

        $old = [];
        $new = [];
        $carried = Money::zero();

        foreach ($settlements as $id => $moving) {
            if ($moving) {
                $carried = $carried->plus(Money::of($current[$id]['amount']));
            }
        }

        PaymentApplications::ensureTargets([$targetInvoiceId => $moved->plus($carried)->toString()], $payment->company_id, $payment->client_organization_id);
        // C-44 reads the ended application with its successor: deferred, held to the commit's `set constraints all immediate` whatever an earlier statement set.
        $context->connection->statement('set constraints payment_applications_c44_superseded_by_successor deferred');
        self::addMove($old, 'applied_amount', $moved->negated());
        self::addMove($new, 'applied_amount', $moved);
        $commandId = $context->request->commandId;
        $now = $context->now();
        $ended = DB::table('payment_applications')->where('id', $applicationId)->where('state', 'ACTIVE')->update(['state' => 'SUPERSEDED', 'end_reason' => 'REALLOCATED', 'end_command_id' => $commandId]);

        if ($ended !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
        }

        $base = ['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'payment_id' => $payment->id, 'target_kind' => 'INVOICE', 'supersedes_application_id' => $applicationId,
            'business_date' => $businessDate, 'reason' => $reason, 'command_id' => $commandId, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId];
        DB::table('payment_applications')->insert([...$base, 'invoice_id' => $targetInvoiceId, 'amount' => $moved->toString(), 'command_line_no' => 1]);

        if ($moved->compareTo($whole) < 0) {
            DB::table('payment_applications')->insert([...$base, 'invoice_id' => $oldInvoice, 'amount' => $whole->minus($moved)->toString(), 'command_line_no' => 2]);
        }

        $line = 0;

        foreach ($settlements as $id => $moving) {
            if (! $moving) {
                continue;
            }

            $row = $current[$id];
            $this->settlements->rerecord($context, $payment->company_id, $id, $targetInvoiceId, $payment->id, $reason, $businessDate, ++$line);
            self::addSettled($old, $row, Money::of($row['amount'])->negated());
            self::addSettled($new, $row, Money::of($row['amount']));
        }

        $this->receivable->moveAll($context, [$oldInvoice => $old, $targetInvoiceId => $new]);
        $this->kuitansi->reconcile($context, $oldInvoice, 'invoices.unlink.corrected');
        $this->revalidate($context, [$oldInvoice], "FIN.ApplicationReallocated:{$payment->public_id}");
        $handle = RowHandle::of('payment_applications', $applicationId);
        $this->corrections->record($context, 'FIN.ApplicationReallocated', AuthorityClass::AdmPlus, $payment->company_id, 'payment', $payment->id, $payment->public_id, AuditRelation::Supersedes, 'payment', $payment->public_id, $reason,
            AuditChange::updated(['application' => $handle, 'invoice' => self::invoiceName($oldInvoice), 'amount' => $whole->toString()], ['invoice' => self::invoiceName($targetInvoiceId), 'amount' => $moved->toString(), 'settlements_moved' => count(array_filter($settlements)), 'settlements_kept' => count($settlements) - count(array_filter($settlements))]));

        return $payment->id;
    }

    /**
     * AX-20's set, data-dependent: every invoice the source is applied to or settles and its project, the source and the re-recorded one, the money facts of its refunds and settlements, the line it cites, the transfer it cites.
     */
    public function contraLocks(Payment $payment, ?int $rerecordedId): LockSet
    {
        $set = (new LockSet)->add('payments', ['id' => $payment->id])->add('application_source_balances', ['payment_id' => $payment->id]);
        $invoices = [];

        foreach (self::activeApplications($payment->id) as $row) {
            if ($row['invoice'] !== null) {
                $invoices[] = $row['invoice'];
            }

            if ($row['refund'] !== null) {
                $set = $set->add('money_fact_balances', ['disbursement_id' => $row['refund']]);
                $case = MoneyFactBalance::query()->toBase()->where('disbursement_id', $row['refund'])->value('residual_case_id');
                $set = is_numeric($case) ? $set->union(CorrectionCases::locks((int) $case)) : $set;
            }
        }

        foreach (SettlementMoves::anchored($payment->id) as $id => $row) {
            $invoices[] = $row['invoice'];
            $set = $set->add('money_fact_balances', ['deduction_settlement_id' => $id]);
        }

        $set = $set->union(PaymentApplications::invoiceLocks(array_values(array_unique($invoices))));

        if ($rerecordedId !== null) {
            $set = $set->add('application_source_balances', ['payment_id' => $rerecordedId]);
        }

        if ($payment->bank_statement_line_id !== null) {
            $set = $set->union(EvidenceGuard::lineLocks($payment->bank_statement_line_id));
        }

        return $payment->transfer_from_disbursement_id === null ? $set : $set->add('money_fact_balances', ['disbursement_id' => $payment->transfer_from_disbursement_id]);
    }

    /** @return array{case: int, contra: int} */
    public function contra(CommandContext $context, Payment $payment, ContraEntry $entry): array
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $source = ['payment_id' => $payment->id];
        $guard = MoneyPosting::source($source) ?? throw Refused::rejected('C-45', ['reason' => 'no_source']);
        $net = Money::of(Row::text($guard, 'net_amount'));
        $amount = Money::of($entry->amount);

        if (! $amount->isPositive() || $amount->compareTo($net) > 0) {
            throw Refused::rejected('C-45', ['reason' => 'above_net', 'net' => $net->toString()]);
        }

        $applications = self::activeApplications($payment->id);
        $settlements = SettlementMoves::anchored($payment->id);

        // ST-07: the payload covers exactly what stands now.
        if (array_diff(array_keys($applications), array_keys($entry->applications)) !== [] || array_diff(array_keys($entry->applications), array_keys($applications)) !== []
            || array_diff(array_keys($settlements), array_keys($entry->settlements)) !== [] || array_diff(array_keys($entry->settlements), array_keys($settlements)) !== []) {
            throw Refused::conflict('ST-07', ['applications' => array_map(fn (int $id): string => RowHandle::of('payment_applications', $id), array_keys($applications)),
                'settlements' => array_map(fn (int $id): string => RowHandle::of('deduction_settlements', $id), array_keys($settlements))]);
        }

        $rerecorded = $entry->rerecordedId === null ? null : DB::table('payments')->where('id', $entry->rerecordedId)->where('rerecords_payment_id', $payment->id)->first(['id', 'client_organization_id']);

        if ($entry->rerecordedId !== null && ($rerecorded === null || (int) Row::text($rerecorded, 'client_organization_id') !== $payment->client_organization_id)) {
            throw Refused::rejected('L-46', ['reason' => 'not_rerecorded']);
        }

        // The end state first, so the refusal names the rule the CHECKs would hold it to.
        $plan = $this->planContra($payment, $guard, $amount, $applications, $settlements, $entry);
        $projects = self::projects($plan['invoices']);
        // C-44 reads each ended application with its successor: deferred, held to the commit's `set constraints all immediate` whatever an earlier statement set.
        $context->connection->statement('set constraints payment_applications_c44_superseded_by_successor deferred');
        $commandId = $context->request->commandId;
        $actor = $context->request->actorUserId;
        $now = $context->now();
        $case = $this->cases->open($context, AuthorityClass::AdmPlus, $payment->company_id, 'PAYMENT_CORRECTION', $entry->reason, count($projects) === 1 ? $projects[0] : null, null, null, $entry->rerecordedId === null ? ['payment_rerecord'] : []);
        $contra = DB::table('financial_contras')->insertGetId(['company_id' => $payment->company_id, 'payment_id' => $payment->id, 'amount' => $amount->toString(), 'reason' => $entry->reason, 'business_date' => $entry->businessDate, 'correction_case_id' => $case->id,
            'recorded_by' => $actor, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now]);
        $base = ['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'business_date' => $entry->businessDate, 'reason' => $entry->reason, 'command_id' => $commandId, 'recorded_at' => $now, 'recorded_by' => $actor];
        $line = 0;
        $facts = [];
        $invoiceMoves = [];
        $residual = Money::zero();

        foreach ($entry->applications as $id => $disposition) {
            $row = $applications[$id];

            if ($disposition['action'] === ContraEntry::KEEP) {
                continue;
            }

            $whole = Money::of($row['amount']);
            $part = $disposition['amount'] === null ? $whole : Money::of($disposition['amount']);
            $target = $row['invoice'] !== null ? ['invoice_id' => $row['invoice'], 'target_kind' => 'INVOICE'] : ['refund_disbursement_id' => $row['refund'], 'target_kind' => 'REFUND'];
            $partial = $part->compareTo($whole) < 0;

            if ($disposition['action'] === ContraEntry::MOVE) {
                DB::table('payment_applications')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'SUPERSEDED', 'end_reason' => 'RE_RECORDED', 'end_command_id' => $commandId]);
                DB::table('payment_applications')->insert([...$base, ...$target, 'payment_id' => $entry->rerecordedId, 'amount' => $part->toString(), 'supersedes_application_id' => $id, 'command_line_no' => ++$line]);
            } else {
                DB::table('payment_applications')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => $partial ? 'SUPERSEDED' : 'RELEASED', 'end_reason' => 'SOURCE_CONTRA', 'end_contra_id' => $contra, 'end_command_id' => $commandId]);
            }

            if ($partial) {
                DB::table('payment_applications')->insert([...$base, ...$target, 'payment_id' => $payment->id, 'amount' => $whole->minus($part)->toString(), 'supersedes_application_id' => $id, 'command_line_no' => ++$line]);
            }

            if ($disposition['action'] === ContraEntry::RELEASE && $row['invoice'] !== null) {
                self::addMove($invoiceMoves[$row['invoice']], 'applied_amount', $part->negated());
            }

            if ($disposition['action'] === ContraEntry::SHORTFALL && $row['refund'] !== null) {
                $facts[$row['refund']] = ($facts[$row['refund']] ?? Money::zero())->plus($part);
            }
        }

        foreach ($entry->settlements as $id => $action) {
            $row = $settlements[$id];
            $part = Money::of($row['amount']);

            if ($action === ContraEntry::MOVE) {
                $this->settlements->rerecord($context, $payment->company_id, $id, $row['invoice'], (int) $entry->rerecordedId, $entry->reason, $entry->businessDate, ++$line);
            }

            if ($action === ContraEntry::RESIDUAL) {
                DB::table('deduction_settlements')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'RESIDUAL', 'correction_case_id' => $case->id]);
                self::addSettled($invoiceMoves[$row['invoice']], $row, $part->negated());
                $residual = $residual->plus($part);
            }
        }

        $this->money->moveSource($context, ['payment_id' => $payment->id], $plan['source']);

        if ($entry->rerecordedId !== null) {
            $this->money->moveSource($context, ['payment_id' => $entry->rerecordedId], $plan['rerecorded']);
        }

        $posted = [];

        foreach ($facts as $refund => $part) {
            $held = MoneyFactBalance::query()->toBase()->where('disbursement_id', $refund)->value('residual_case_id');
            $carrier = is_numeric($held) ? (int) $held : $case->id;

            if (! is_numeric($held)) {
                $context->connection->table('money_fact_balances')->where('disbursement_id', $refund)->update(['residual_case_id' => $carrier]);
            }

            $this->money->moveFact($context, ['disbursement_id' => $refund], ['backed_amount' => $part->negated()->toString(), 'shortfall_amount' => $part->toString()]);
            $posted[$carrier] = ($posted[$carrier] ?? Money::zero())->plus($part);
        }

        foreach ($posted as $carrier => $part) {
            $this->cases->post($context, $carrier, ['unbacked_refund_amount' => $part->toString()], $entry->reason);
        }

        if ($residual->isPositive()) {
            $this->cases->post($context, $case->id, ['residual_settlement_amount' => $residual->toString()], $entry->reason);
        }

        $this->receivable->moveAll($context, array_filter($invoiceMoves, fn (array $moves): bool => $moves !== []));

        if ($payment->bank_statement_line_id !== null) {
            $this->evidence->moveLines($context, [$payment->bank_statement_line_id => ['cited_amount' => $amount->negated()->toString()]]);
        }

        if ($payment->transfer_from_disbursement_id !== null) {
            $this->money->moveFact($context, ['disbursement_id' => $payment->transfer_from_disbursement_id], ['transfer_cited_amount' => $amount->negated()->toString()]);
        }

        foreach ($plan['invoices'] as $invoice) {
            $this->kuitansi->reconcile($context, $invoice, 'invoices.unlink.corrected');
        }

        $this->revalidate($context, array_keys(array_filter($invoiceMoves, fn (array $moves): bool => $moves !== [])), "FIN.PaymentContraed:{$payment->public_id}");
        $this->corrections->record($context, 'FIN.PaymentContraed', AuthorityClass::AdmPlus, $payment->company_id, 'payment', $payment->id, $payment->public_id, AuditRelation::Reverses, 'payment', $payment->public_id, $entry->reason,
            AuditChange::updated(['net_amount' => $net->toString()], ['net_amount' => $net->minus($amount)->toString(), 'contra' => $amount->toString(), 'dispositions' => self::named($entry)]), $case->public_id, ['business_date' => $entry->businessDate], $entry->rerecordedId === null ? null : 'payment_rerecord');

        return ['case' => $case->id, 'contra' => $contra];
    }

    /**
     * The payment's ACTIVE applications, by id.
     *
     * @return array<int, array{amount: string, invoice: ?int, refund: ?int}>
     */
    public static function activeApplications(int $paymentId): array
    {
        $found = [];

        foreach (DB::table('payment_applications')->where('payment_id', $paymentId)->where('state', 'ACTIVE')->orderBy('id')->get(['id', 'amount', 'invoice_id', 'refund_disbursement_id']) as $row) {
            $invoice = Row::nullable($row, 'invoice_id');
            $refund = Row::nullable($row, 'refund_disbursement_id');
            $found[(int) Row::text($row, 'id')] = ['amount' => Row::text($row, 'amount'), 'invoice' => $invoice === null ? null : (int) $invoice, 'refund' => $refund === null ? null : (int) $refund];
        }

        return $found;
    }

    /**
     * What the dispositions do to the sources, checked against C-13, C-44 and C-45 as the CHECKs would: invoice applications are disposed before refund backing may become a shortfall.
     *
     * @param  array<int, array{amount: string, invoice: ?int, refund: ?int}>  $applications
     * @param  array<int, array{amount: string, invoice: int, class: string, direction: ?string}>  $settlements
     * @return array{source: array<string, string>, rerecorded: array<string, string>, invoices: list<int>}
     */
    private function planContra(Payment $payment, object $guard, Money $amount, array $applications, array $settlements, ContraEntry $entry): array
    {
        $invoiceApplied = Money::of(Row::text($guard, 'invoice_applied_amount'));
        $refundBacked = Money::of(Row::text($guard, 'refund_backed_amount'));
        $shortfall = Money::of(Row::text($guard, 'refund_shortfall_amount'));
        $settled = Money::of(Row::text($guard, 'settled_amount'));
        $movedApplied = Money::zero();
        $movedRefund = Money::zero();
        $movedSettled = Money::zero();
        $invoices = [];

        foreach ($entry->applications as $id => $disposition) {
            $row = $applications[$id];
            $action = $disposition['action'];

            if ($action === ContraEntry::KEEP) {
                continue;
            }

            $whole = Money::of($row['amount']);
            $part = $disposition['amount'] === null ? $whole : Money::of($disposition['amount']);

            if (! $part->isPositive() || $part->compareTo($whole) > 0) {
                throw Refused::rejected('C-13', ['reason' => 'above_application', 'application' => $whole->toString()]);
            }

            if ($action === ContraEntry::MOVE && $entry->rerecordedId === null) {
                throw Refused::rejected('L-46', ['reason' => 'not_rerecorded']);
            }

            if ($row['invoice'] !== null) {
                if ($action === ContraEntry::SHORTFALL) {
                    throw Refused::rejected('C-44', ['reason' => 'not_refund']);
                }

                $invoiceApplied = $invoiceApplied->minus($part);
                $invoices[$row['invoice']] = true;
                $movedApplied = $action === ContraEntry::MOVE ? $movedApplied->plus($part) : $movedApplied;

                continue;
            }

            // A refund's backing never simply returns to the source: only to the re-recorded payment, or a typed shortfall (L-46).
            if ($action === ContraEntry::RELEASE) {
                throw Refused::rejected('L-46', ['reason' => 'refund_consumed']);
            }

            $refundBacked = $refundBacked->minus($part);

            if ($action === ContraEntry::MOVE) {
                $movedRefund = $movedRefund->plus($part);
            } else {
                $shortfall = $shortfall->plus($part);
            }
        }

        foreach ($entry->settlements as $id => $action) {
            $row = $settlements[$id];

            if ($action === ContraEntry::KEEP) {
                continue;
            }

            if ($action === ContraEntry::MOVE && $entry->rerecordedId === null) {
                throw Refused::rejected('L-46', ['reason' => 'not_rerecorded']);
            }

            $invoices[$row['invoice']] = true;
            $settled = $settled->minus(Money::of($row['amount']));
            $movedSettled = $action === ContraEntry::MOVE ? $movedSettled->plus(Money::of($row['amount'])) : $movedSettled;
        }

        $position = new SourcePosition(Money::of(Row::text($guard, 'gross_amount')), Money::of(Row::text($guard, 'contra_amount'))->plus($amount), $invoiceApplied, $refundBacked, $shortfall, $settled);
        $breach = $position->breach();

        if ($breach !== null) {
            throw Refused::rejected($breach['rule'], $breach['state']);
        }

        $rerecorded = ['invoice_applied_amount' => $movedApplied->toString(), 'refund_backed_amount' => $movedRefund->toString(), 'settled_amount' => $movedSettled->toString()];

        if ($entry->rerecordedId !== null) {
            $held = MoneyPosting::source(['payment_id' => $entry->rerecordedId]);
            $room = $held === null ? Money::zero() : Money::of(Row::text($held, 'capacity_amount'));

            if ($movedApplied->plus($movedRefund)->compareTo($room) > 0) {
                throw Refused::rejected('C-13', ['reason' => 'above_capacity', 'capacity' => $room->toString(), 'amount' => $movedApplied->plus($movedRefund)->toString()]);
            }

            $claims = $held === null ? Money::zero() : Money::of(Row::text($held, 'claimed_deductions_amount'))->minus(Money::of(Row::text($held, 'settled_amount')));

            if ($movedSettled->compareTo($claims) > 0) {
                throw Refused::rejected('C-22', ['reason' => 'above_claimed', 'claimed' => $claims->toString()]);
            }
        }

        foreach ($applications as $row) {
            if ($row['invoice'] !== null) {
                $invoices[$row['invoice']] = true;
            }
        }

        return [
            'source' => [
                'contra_amount' => $amount->toString(),
                'invoice_applied_amount' => $invoiceApplied->minus(Money::of(Row::text($guard, 'invoice_applied_amount')))->toString(),
                'refund_backed_amount' => $refundBacked->minus(Money::of(Row::text($guard, 'refund_backed_amount')))->toString(),
                'refund_shortfall_amount' => $shortfall->minus(Money::of(Row::text($guard, 'refund_shortfall_amount')))->toString(),
                'settled_amount' => $settled->minus(Money::of(Row::text($guard, 'settled_amount')))->toString(),
            ],
            'rerecorded' => $rerecorded,
            'invoices' => array_keys($invoices),
        ];
    }

    /** @param  list<int>  $invoices */
    private function revalidate(CommandContext $context, array $invoices, string $reference): void
    {
        foreach (self::projects($invoices) as $project) {
            $this->revalidation->revalidate($context, $project, $reference);
        }
    }

    /**
     * @param  list<int>  $invoices
     * @return list<int>
     */
    private static function projects(array $invoices): array
    {
        $projects = [];

        foreach (DB::table('invoices')->whereIn('id', $invoices)->whereNotNull('project_id')->distinct()->orderBy('project_id')->pluck('project_id') as $project) {
            if (is_numeric($project)) {
                $projects[] = (int) $project;
            }
        }

        return $projects;
    }

    /**
     * @param  array<string, string>|null  $deltas
     *
     * @param-out array<string, string>  $deltas
     */
    private static function addMove(?array &$deltas, string $column, Money $amount): void
    {
        $deltas ??= [];
        $deltas[$column] = Money::of($deltas[$column] ?? '0')->plus($amount)->toString();
    }

    /**
     * @param  array<string, string>|null  $deltas
     * @param  array{amount: string, invoice: int, class: string, direction: ?string}  $settlement
     *
     * @param-out array<string, string>  $deltas
     */
    private static function addSettled(?array &$deltas, array $settlement, Money $amount): void
    {
        self::addMove($deltas, $settlement['class'] === 'FEE' ? 'fee_settled_amount' : 'tax_settled_amount', $amount);

        if ($settlement['class'] === 'TAX' && $settlement['direction'] === 'COLLECTED') {
            self::addMove($deltas, 'collected_tax_settled_amount', $amount);
        }
    }

    private static function invoiceName(int $invoiceId): string
    {
        return InvoiceSubjects::number($invoiceId) ?? RowHandle::of('invoices', $invoiceId);
    }

    /** @return array<string, array<string, string>> */
    private static function named(ContraEntry $entry): array
    {
        $applications = [];
        $settlements = [];

        foreach ($entry->applications as $id => $disposition) {
            $applications['application_'.$id] = $disposition['action'].($disposition['amount'] === null ? '' : ' '.$disposition['amount']);
        }

        foreach ($entry->settlements as $id => $action) {
            $settlements['settlement_'.$id] = $action;
        }

        return ['applications' => $applications, 'settlements' => $settlements];
    }
}
