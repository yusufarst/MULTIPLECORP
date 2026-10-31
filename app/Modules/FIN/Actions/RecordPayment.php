<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Data\PaymentEntry;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\PaymentApplications;
use App\Modules\FIN\Services\PaymentDuplicates;
use App\Modules\FIN\Services\PaymentRecords;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
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
 * `FIN RecordPayment` (ROUTE-251; INT-251; AX-18; WF-FIN-03; L-22; L-29; J-FIN-03; S2-J06) — "Catat Pembayaran" after MSG-39's confirmation (IP-08): one immutable Cash-In fact per real receipt, its source guard
 * born, the statement line it cites moving that line's guard (C-19; BD-03), its same-command applications through SF-APPLY, the rest left as customer credit, never profit (BR-FIN-05). Retry is the command's identity
 * (CI-05; M-08–M-10); a likely duplicate is REJECTED with its matches until the second submission of an account holding `warnings.override` names each and gives its reason (IP-05; BD-02; M-12; QS-21).
 * Without proof it stands on its reason, flagged *Tanpa Bukti* (L-29). A recovery, a re-recording and a real transfer from another company carry their typed links (WF-FIN-05; CM-19; XL-5). A residual command: no
 * reopening of a completed project (WF-PRJ-03; L-11). `payment.record` with `finance.view`, covering the applications.
 */
final class RecordPayment
{
    public const TYPE = 'FIN.RecordPayment';

    public const CAPABILITY = 'payment.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly MoneyPosting $money,
        private readonly PaymentApplications $applications,
        private readonly PaymentRecords $records,
        private readonly EvidenceGuard $evidence,
        private readonly Corrections $corrections,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, PaymentEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $entry->companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($entry->companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock($this->locks($entry));
            BusinessDateRule::check($entry->businessDate, $entry->prefilled);
            $this->ensureDestination($entry);
            $overridden = self::duplicates($context, $entry);
            $reason = self::ensureProof($entry);
            self::ensureLinks($context, $entry);
            $claimed = Money::zero();

            foreach ($entry->claimed as $deduction) {
                $claimed = $claimed->plus(Money::of($deduction['amount']));
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $commandId = $context->request->commandId;
            $amount = Money::of($entry->amount)->toString();
            $payment = new Payment;
            $payment->forceFill([
                'company_id' => $entry->companyId, 'client_organization_id' => $entry->clientId, 'business_date' => $entry->businessDate, 'amount' => $amount, 'method' => $entry->method,
                'company_bank_account_id' => $entry->accountId, 'bank_statement_line_id' => $entry->lineId, 'payer_intermediary' => $entry->intermediary, 'reference' => $entry->reference, 'unevidenced_reason' => $reason,
                'recovery_for_invoice_id' => $entry->recoveryFor, 'rerecords_payment_id' => $entry->rerecords, 'transfer_from_disbursement_id' => $entry->transferFrom, 'transfer_from_disbursement_kind' => $entry->transferFrom === null ? null : 'INTERCOMPANY_TRANSFER_OUT',
                'command_id' => $commandId, 'command_line_no' => 1, 'recorded_by' => $actor,
            ])->save();
            $this->money->bearPayment($context, $payment->id, $entry->companyId, $entry->clientId, $amount, $claimed->toString());

            foreach ($entry->claimed as $line => $deduction) {
                DB::table('payment_claimed_deductions')->insert(['payment_id' => $payment->id, 'deduction_class' => $deduction['class'], 'tax_type_id' => $deduction['tax_type'], 'fee_type' => $deduction['fee_type'], 'claimed_amount' => Money::of($deduction['amount'])->toString(),
                    'command_id' => $commandId, 'command_line_no' => $line + 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
            }

            foreach ($entry->evidence as $line => $evidence) {
                DB::table('evidence_links')->insert(['evidence_document_id' => $evidence, 'evidence_scope' => 'COMPANY', 'company_id' => $entry->companyId, 'role' => $line === 0 ? 'PRIMARY_PROOF' : 'SUPPORTING', 'payment_id' => $payment->id,
                    'command_id' => $commandId, 'command_line_no' => $line + 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
            }

            if ($entry->lineId !== null) {
                $this->evidence->moveLines($context, [$entry->lineId => ['cited_amount' => $amount]]);
            }

            if ($entry->transferFrom !== null) {
                $this->money->moveFact($context, ['disbursement_id' => $entry->transferFrom], ['transfer_cited_amount' => $amount]);
            }

            $this->applications->apply($context, ['payment_id' => $payment->id], $entry->companyId, $entry->clientId, $entry->applications, $entry->resolves, $entry->businessDate, null);
            $this->audit($context, $payment, $entry, $overridden, $amount, $claimed);

            return new CommandResult('payment', $payment->id);
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'L-22' && $entry->accountId !== null
            ? ['matches' => PaymentDuplicates::matches($entry->accountId, Money::of($entry->amount)->toString(), $entry->businessDate, $entry->rerecords), 'override' => $this->capabilities->for($actorUserId)->allows('warnings.override', $entry->companyId)]
            : null);
    }

    /** AX-18's set: the account at LK-07 (BD-02's anchor), the line and proofs at LK-29 (BD-03), the targets, a resolved shortfall's guards and case, a cited transfer-out's fact, a re-recorded payment's case. */
    private function locks(PaymentEntry $entry): LockSet
    {
        $set = PaymentApplications::invoiceLocks(array_keys($entry->applications))->union(PaymentApplications::shortfallLocks(array_keys($entry->resolves)));

        if ($entry->accountId !== null) {
            $set = $set->add('company_bank_accounts', ['id' => $entry->accountId]);
        }

        if ($entry->lineId !== null) {
            $set = $set->union(EvidenceGuard::lineLocks($entry->lineId));
        }

        foreach ($entry->evidence as $evidence) {
            $set = $set->add('evidence_documents', ['id' => $evidence]);
        }

        if ($entry->transferFrom !== null) {
            $set = $set->add('money_fact_balances', ['disbursement_id' => $entry->transferFrom]);
        }

        $case = $entry->rerecords === null ? null : self::caseOf($entry->rerecords);

        return $case === null ? $set : $set->union(CorrectionCases::locks($case));
    }

    /** The account the company's own and active, the method needing one; the line ACTIVE on that account with room for this money (C-32; C-19; L-22). */
    private function ensureDestination(PaymentEntry $entry): void
    {
        if ($entry->accountId === null) {
            if ($entry->method !== 'CASH' || $entry->lineId !== null) {
                throw Refused::rejected('C-32', ['reason' => 'account']);
            }

            return;
        }

        if (DB::table('company_bank_accounts')->where('id', $entry->accountId)->where('company_id', $entry->companyId)->where('is_active', true)->doesntExist()) {
            throw Refused::rejected('C-32', ['reason' => 'account']);
        }

        if ($entry->lineId === null) {
            return;
        }

        $line = DB::table('bank_statement_lines')->where('id', $entry->lineId)->where('company_id', $entry->companyId)->first(['state', 'company_bank_account_id', 'amount']);

        if ($line === null || Row::text($line, 'state') !== 'ACTIVE' || (int) Row::text($line, 'company_bank_account_id') !== $entry->accountId) {
            throw Refused::rejected('C-19', ['reason' => 'line']);
        }

        $uncited = Money::of(Row::text($line, 'amount'))->minus(Money::of($this->evidence->lineCited($entry->lineId)));

        if (Money::of($entry->amount)->compareTo($uncited) > 0) {
            throw Refused::rejected('C-19', ['reason' => 'above_line', 'uncited' => $uncited->toString(), 'amount' => Money::of($entry->amount)->toString()]);
        }
    }

    /**
     * BD-02 under the account's lock: refused with the current matches unless the actor may override and names each with a reason (IP-05; UXS-16). A cash payment names no account, so the check does not reach it.
     *
     * @return list<string> what the override acknowledges
     */
    private static function duplicates(CommandContext $context, PaymentEntry $entry): array
    {
        if ($entry->accountId === null) {
            return [];
        }

        $matches = PaymentDuplicates::matches($entry->accountId, Money::of($entry->amount)->toString(), $entry->businessDate, $entry->rerecords);

        if ($matches === []) {
            return [];
        }

        $ids = array_column($matches, 'id');
        $override = $context->authority()?->allows('warnings.override', $entry->companyId) === true;

        if (! $override || array_diff($ids, $entry->acknowledged) !== [] || $entry->overrideReason === null || trim($entry->overrideReason) === '') {
            throw Refused::rejected('L-22', ['matches' => $matches, 'override' => $override]);
        }

        return $ids;
    }

    /** L-29: proof, or the reason there is none. A cited statement line is proof; a document the company issued itself never is. Returns the reason recorded. */
    private static function ensureProof(PaymentEntry $entry): ?string
    {
        foreach ($entry->evidence as $evidence) {
            $row = DB::table('evidence_documents')->where('id', $evidence)->where('company_id', $entry->companyId)->whereIn('evidence_type', PaymentEntry::PROOF)->exists();

            if (! $row) {
                throw Refused::rejected('L-29', ['reason' => 'proof']);
            }
        }

        if ($entry->evidence !== [] && EvidenceGuard::companyIssued($entry->evidence)) {
            throw Refused::rejected('L-29', ['reason' => 'company_issued']);
        }

        if ($entry->evidence !== [] || $entry->lineId !== null) {
            return null;
        }

        if ($entry->unevidencedReason === null || trim($entry->unevidencedReason) === '') {
            throw Refused::rejected('L-29', ['reason' => 'no_proof']);
        }

        return trim($entry->unevidencedReason);
    }

    /** A recovery names a written-off invoice of this client and company; a re-recording a payment of this company, once; a transfer-out another company's, within its cap, both in scope (WF-FIN-05; CM-19; XL-5). */
    private static function ensureLinks(CommandContext $context, PaymentEntry $entry): void
    {
        if ($entry->recoveryFor !== null) {
            $written = DB::table('receivable_writeoffs')->where('invoice_id', $entry->recoveryFor)->where('company_id', $entry->companyId)->exists();
            $party = DB::table('invoices')->where('id', $entry->recoveryFor)->where('company_id', $entry->companyId)->where('client_organization_id', $entry->clientId)->exists();

            if (! $written || ! $party) {
                throw Refused::rejected('L-17', ['reason' => 'not_written_off']);
            }
        }

        if ($entry->rerecords !== null) {
            if (DB::table('payments')->where('id', $entry->rerecords)->where('company_id', $entry->companyId)->doesntExist()) {
                throw Refused::rejected('C-59', ['reason' => 'rerecords']);
            }

            if (DB::table('payments')->where('rerecords_payment_id', $entry->rerecords)->exists()) {
                throw Refused::conflict('C-59', ['reason' => 'already_rerecorded']);
            }
        }

        if ($entry->transferFrom === null) {
            return;
        }

        $fact = MoneyPosting::transferOut($entry->transferFrom);
        $other = $fact === null ? 0 : (int) Row::text($fact, 'company_id');

        if ($fact === null || $other === $entry->companyId || $context->authority()?->scope->includes($other) !== true) {
            throw Refused::rejected('XL-5', ['reason' => 'transfer_scope']);
        }

        $left = Money::of(Row::text($fact, 'amount'))->minus(Money::of(Row::text($fact, 'contra_amount')))->minus(Money::of(Row::text($fact, 'transfer_cited_amount')));

        if (Money::of($entry->amount)->compareTo($left) > 0) {
            throw Refused::rejected('BR-FIN-07', ['reason' => 'transfer_capacity', 'left' => $left->toString()]);
        }
    }

    /** The case a re-recorded payment's contra opened: its step is done once the real payment is recorded (SF-CASE; CM-19). */
    private static function caseOf(int $paymentId): ?int
    {
        $case = DB::table('financial_contras')->where('payment_id', $paymentId)->whereNotNull('correction_case_id')->orderByDesc('id')->value('correction_case_id');

        return is_numeric($case) ? (int) $case : null;
    }

    /** @param  list<string>  $overridden */
    private function audit(CommandContext $context, Payment $payment, PaymentEntry $entry, array $overridden, string $amount, Money $claimed): void
    {
        $linkage = AuditLinkage::make();
        $after = ['amount' => $amount, 'method' => $entry->method, 'claimed_deductions' => $claimed->toString(), 'applications' => count($entry->applications), ...($entry->lineId === null ? [] : ['line' => RowHandle::of('bank_statement_lines', $entry->lineId)])];

        foreach ($entry->evidence as $evidence) {
            $publicId = DB::table('evidence_documents')->where('id', $evidence)->value('public_id');
            $linkage->link(AuditRelation::Affects, 'evidence_document', is_string($publicId) ? $publicId : '');
        }

        $metadata = [];

        if ($entry->recoveryFor !== null) {
            $metadata['recovery_for'] = DB::table('invoices')->where('id', $entry->recoveryFor)->value('public_id');
        }

        if ($overridden !== []) {
            $metadata['matches'] = $overridden;
        }

        // An overridden duplicate commits under its acknowledged variant, ADM_PLUS, for QS-21 and the Owner's review (AZ-10; QS-14).
        $this->records->record($context, $overridden === [] ? 'FIN.PaymentRecorded' : 'FIN.PaymentDuplicateOverridden', $overridden === [] ? AuthorityClass::Adm : AuthorityClass::AdmPlus, $payment->id,
            $overridden === [] ? $payment->unevidenced_reason : $entry->overrideReason, $entry->businessDate, $after, [], $linkage, $metadata);

        if ($entry->rerecords !== null) {
            $original = DB::table('payments')->where('id', $entry->rerecords)->value('public_id');
            $case = self::caseOf($entry->rerecords);
            $casePublicId = $case === null ? null : DB::table('correction_cases')->where('id', $case)->value('public_id');
            $this->corrections->record($context, 'FIN.PaymentReRecorded', AuthorityClass::Adm, $entry->companyId, 'payment', $payment->id, $payment->public_id, AuditRelation::Supersedes, 'payment', is_string($original) ? $original : '',
                'CM-19', AuditChange::created(['rerecords' => is_string($original) ? $original : '']), is_string($casePublicId) ? $casePublicId : null, [], is_string($casePublicId) ? 'payment_rerecord' : null);
        }
    }
}
