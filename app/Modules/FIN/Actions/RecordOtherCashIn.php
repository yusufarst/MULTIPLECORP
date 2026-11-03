<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Data\OtherCash;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\IAM\Data\CrossCompanyLink;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PUR\Services\ReturnOutcomes;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
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
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN RecordOtherCashIn` (ROUTE-259; INT-259; NX-10; L-08; BR-FIN-07; BR-FIN-10) — "Catat Kas Masuk Lain" on PG-049: money actually received into the company's own account,
 * not from a client — received cashback and other income as income; a supplier refund for a returned, cancelled or overpaid purchase as restitution, with a return case's
 * RETURN_REFUNDED adjustment and refunded figure (DATABASE §6.1); a transfer in citing another company's transfer-out within its net amount, both companies in scope (XL-5);
 * other restitution. Its statement-line citation is set here once (C-19); its money-fact guard is born with it. `cashin.record` of the company.
 */
final class RecordOtherCashIn
{
    public const TYPE = 'FIN.RecordOtherCashIn';

    public const CAPABILITY = 'cashin.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceGuard $evidence,
        private readonly MoneyPosting $money,
        private readonly ReturnOutcomes $returns,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * NX-10's set: the account (LK-03), the purchase header (LK-21), the return case with its projects and line (LK-01, LK-10), the cited transfer-out's fact (LK-27), the
     * statement line and each proof (LK-29).
     *
     * @param  array{purchase: ?int, case: ?int, transfer: ?int, line: ?int}  $reference
     * @param  list<int>  $evidence
     */
    public static function locks(int $accountId, array $reference, array $evidence): LockSet
    {
        $set = (new LockSet)->add('company_bank_accounts', ['id' => $accountId]);
        $set = $reference['case'] === null ? $set : $set->union(ReturnOutcomes::locks($reference['case'], null));
        $set = $reference['purchase'] === null ? $set : $set->add('purchases', ['id' => $reference['purchase']]);
        $set = $reference['transfer'] === null ? $set : $set->add('money_fact_balances', ['disbursement_id' => $reference['transfer']]);
        $set = $reference['line'] === null ? $set : $set->union(EvidenceGuard::lineLocks($reference['line']));

        foreach (array_values(array_unique($evidence)) as $document) {
            $set = $set->add('evidence_documents', ['id' => $document]);
        }

        return $set;
    }

    /**
     * @param  array{purchase: ?int, case: ?int, transfer: ?int, line: ?int}  $reference
     * @param  list<int>  $evidence  the proofs of the money received, linked to it
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, string $category, string $amount, int $accountId, array $reference, array $evidence, ?string $note, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $category, $amount, $accountId, $reference, $evidence, $note, $businessDate, $prefilled): CommandResult {
            $context->lock(self::locks($accountId, $reference, $evidence));
            BusinessDateRule::check($businessDate, $prefilled);
            $money = Money::of($amount);

            if (! in_array($category, OtherCash::CATEGORIES, true) || ! $money->isPositive()) {
                throw Refused::rejected('C-14', ['reason' => 'amount']);
            }

            if (DB::table('company_bank_accounts')->where('id', $accountId)->where('company_id', $companyId)->where('is_active', true)->doesntExist()) {
                throw Refused::rejected('C-32', ['reason' => 'account']);
            }

            self::ensureReference($category, $reference);
            $this->ensureLine($companyId, $accountId, $reference['line'], $money);
            self::ensureProof($context, $companyId, $evidence, $reference['line'] !== null);
            $linkage = AuditLinkage::make();
            $purchase = $reference['purchase'];
            $case = $reference['case'];
            $reason = $note === null || trim($note) === '' ? 'Pengembalian dana pemasok' : trim($note);

            if ($category === 'SUPPLIER_REFUND') {
                // CS-04: another company's purchase is refused like an unknown one.
                if ($purchase !== null && DB::table('purchases')->where('id', $purchase)->where('company_id', $companyId)->doesntExist()) {
                    throw Refused::rejected('C-14', ['reason' => 'purchase']);
                }

                if ($case !== null) {
                    $ofCase = $this->returns->refundReceived($context, $case, $companyId, $money, $reason);

                    if ($purchase !== null && $purchase !== $ofCase) {
                        throw Refused::rejected('C-14', ['reason' => 'case']);
                    }

                    $purchase = $ofCase;
                    $casePublicId = DB::table('correction_cases')->where('id', $case)->value('public_id');
                    $linkage = is_string($casePublicId) ? $linkage->correctionCase($casePublicId) : $linkage;
                }
            }

            if ($category === 'INTERCOMPANY_TRANSFER_IN') {
                $this->ensureTransfer($context, $companyId, (int) $reference['transfer'], $money);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $command = $context->request->commandId;
            $id = (int) DB::table('other_cash_receipts')->insertGetId([
                'company_id' => $companyId, 'business_date' => $businessDate, 'amount' => $money->toString(), 'category' => $category, 'income_class' => OtherCash::incomeClass($category),
                'company_bank_account_id' => $accountId, 'bank_statement_line_id' => $reference['line'], 'purchase_id' => $purchase, 'correction_case_id' => $case,
                'transfer_from_disbursement_id' => $reference['transfer'], 'transfer_from_disbursement_kind' => $reference['transfer'] === null ? null : 'INTERCOMPANY_TRANSFER_OUT',
                'command_id' => $command, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            GuardUpdate::bear($context->connection, 'money_fact_balances', ['other_cash_receipt_id' => $id], ['company_id' => $companyId, 'fact_kind' => 'CASH_IN', 'amount' => $money->toString(), 'recorded_at' => $now]);

            if ($reference['transfer'] !== null) {
                $this->money->moveFact($context, ['disbursement_id' => $reference['transfer']], ['transfer_cited_amount' => $money->toString()]);
            }

            if ($reference['line'] !== null) {
                $this->evidence->moveLines($context, [$reference['line'] => ['cited_amount' => $money->toString()]]);
            }

            foreach (array_values(array_unique($evidence)) as $line => $document) {
                DB::table('evidence_links')->insert(['evidence_document_id' => $document, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'role' => $line === 0 ? 'PRIMARY_PROOF' : 'SUPPORTING', 'other_cash_receipt_id' => $id,
                    'command_id' => $command, 'command_line_no' => $line + 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
                $publicId = DB::table('evidence_documents')->where('id', $document)->value('public_id');
                $linkage = is_string($publicId) ? $linkage->link(AuditRelation::Affects, 'evidence_document', $publicId) : $linkage;
            }

            $purchasePublicId = $purchase === null ? null : DB::table('purchases')->where('id', $purchase)->value('public_id');
            $linkage = is_string($purchasePublicId) ? $linkage->link(AuditRelation::Affects, 'purchase', $purchasePublicId) : $linkage;

            $this->audit->record(
                action: 'FIN.OtherCashInRecorded',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $actor,
                companyId: $companyId,
                entityType: 'other_cash_receipt',
                entityId: $id,
                reason: $note === null || trim($note) === '' ? null : trim($note),
                commandId: $command,
                // PJ-11: the other company's transfer-out is named by a handle, never its own identifier.
                change: AuditChange::created(['amount' => $money->toString(), 'category' => $category, 'account' => RowHandle::of('company_bank_accounts', $accountId),
                    ...($reference['transfer'] === null ? [] : ['transfer' => RowHandle::of('disbursements', $reference['transfer'])]), ...($reference['line'] === null ? [] : ['line' => RowHandle::of('bank_statement_lines', $reference['line'])])]),
                linkage: $linkage,
                businessDate: $businessDate,
            );

            return new CommandResult('other_cash_receipt', $id);
        });
    }

    /**
     * Each category's references and no other: a supplier refund names its purchase or its return case, a transfer in its transfer-out.
     *
     * @param  array{purchase: ?int, case: ?int, transfer: ?int, line: ?int}  $reference
     */
    private static function ensureReference(string $category, array $reference): void
    {
        $refund = $category === 'SUPPLIER_REFUND';
        $transfer = $category === 'INTERCOMPANY_TRANSFER_IN';

        if (($refund && $reference['purchase'] === null && $reference['case'] === null) || (! $refund && ($reference['purchase'] !== null || $reference['case'] !== null))
            || ($transfer !== ($reference['transfer'] !== null))) {
            throw Refused::rejected('C-14', ['reason' => 'reference']);
        }
    }

    /** C-19: the line ACTIVE on this account, with room for this money. */
    private function ensureLine(int $companyId, int $accountId, ?int $lineId, Money $money): void
    {
        if ($lineId === null) {
            return;
        }

        $line = DB::table('bank_statement_lines')->where('id', $lineId)->where('company_id', $companyId)->first(['state', 'company_bank_account_id']);

        if ($line === null || Row::text($line, 'state') !== 'ACTIVE' || (int) Row::text($line, 'company_bank_account_id') !== $accountId) {
            throw Refused::rejected('C-19', ['reason' => 'line']);
        }

        $uncited = Money::of($this->evidence->lineUncited($lineId));

        if ($money->compareTo($uncited) > 0) {
            throw Refused::rejected('C-19', ['reason' => 'above_line', 'uncited' => $uncited->toString(), 'amount' => $money->toString()]);
        }
    }

    /** XL-5; BR-FIN-07 b: another company's transfer-out, both companies in scope for the actor, the money within what it has not yet backed. */
    private function ensureTransfer(CommandContext $context, int $companyId, int $transferId, Money $money): void
    {
        $fact = MoneyPosting::transferOut($transferId);
        $other = $fact === null ? 0 : (int) Row::text($fact, 'company_id');
        $authority = $context->authority();

        if ($fact === null || $other === $companyId || $authority === null || ! CrossCompanyLink::mayCiteTransfer($authority, self::CAPABILITY, $companyId, $other)) {
            throw Refused::rejected('XL-5', ['reason' => 'transfer_scope']);
        }

        $left = Money::of(Row::text($fact, 'amount'))->minus(Money::of(Row::text($fact, 'contra_amount')))->minus(Money::of(Row::text($fact, 'transfer_cited_amount')));

        if ($money->compareTo($left) > 0) {
            throw Refused::rejected('BR-FIN-07', ['reason' => 'transfer_capacity', 'left' => $left->toString()]);
        }
    }

    /**
     * L-29: the money received is proven — by the cited statement line, or a payment or transfer proof of the company the actor may see, never a rendition it issued itself.
     *
     * @param  list<int>  $evidence
     */
    private static function ensureProof(CommandContext $context, int $companyId, array $evidence, bool $line): void
    {
        if ($evidence === [] && ! $line) {
            throw Refused::rejected('L-29', ['reason' => 'proof']);
        }

        $authority = $context->authority();

        foreach ($evidence as $document) {
            $type = DB::table('evidence_documents')->where('id', $document)->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->value('evidence_type');

            if (! is_string($type) || ! in_array($type, OtherCash::PROOF, true) || $authority === null || ! EvidenceTypes::mayView($authority, $type, $companyId)) {
                throw Refused::rejected('L-29', ['reason' => 'proof']);
            }
        }

        if ($evidence !== [] && EvidenceGuard::companyIssued($evidence)) {
            throw Refused::rejected('L-29', ['reason' => 'company_issued']);
        }
    }
}
