<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Data\OtherCash;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `FIN RecordDisbursement` (ROUTE-258; INT-258; NX-10; BR-FIN-01; BR-FIN-11; BR-FIN-17; FS-04) — "Catat Pengeluaran Kas" on PG-049: the only Cash-Out, from the company's own
 * account with its evidence, against a purchase — its header locked (LR-10), so it meets a cancellation there —, an expense, or a tax payment citing its TAX_PAYMENT_PROOF
 * under the treatment rule in force; or a real inter-bank transfer out, which the receiving company's Cash-In cites within its net amount (BR-FIN-07 b; XL-5). Its money-fact
 * guard is born with it. `disbursement.record` of the company.
 */
final class RecordDisbursement
{
    public const TYPE = 'FIN.RecordDisbursement';

    public const CAPABILITY = 'disbursement.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceGuard $evidence,
        private readonly TaxConfiguration $tax,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * NX-10's set: the account (LK-03), the purchase header (LK-21; LR-10), the expense's money fact (LK-27) so its contra waits, the tax evidence's guard and each proof (LK-29).
     *
     * @param  array{purchase: ?int, expense: ?int, tax_evidence: ?int}  $reference
     * @param  list<int>  $evidence
     */
    public static function locks(int $accountId, array $reference, array $evidence): LockSet
    {
        $set = (new LockSet)->add('company_bank_accounts', ['id' => $accountId]);
        $set = $reference['purchase'] === null ? $set : $set->add('purchases', ['id' => $reference['purchase']]);
        $set = $reference['expense'] === null ? $set : $set->add('money_fact_balances', ['expense_id' => $reference['expense']]);
        $set = $reference['tax_evidence'] === null ? $set : $set->union(EvidenceGuard::locks($reference['tax_evidence']));

        foreach (array_values(array_unique($evidence)) as $document) {
            $set = $set->add('evidence_documents', ['id' => $document]);
        }

        return $set;
    }

    /**
     * @param  array{purchase: ?int, expense: ?int, tax_evidence: ?int, tax_type: ?int}  $reference
     * @param  list<int>  $evidence  the proofs of the money leaving, linked to it
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, string $kind, string $amount, int $accountId, array $reference, array $evidence, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $kind, $amount, $accountId, $reference, $evidence, $businessDate, $prefilled): CommandResult {
            $context->lock(self::locks($accountId, $reference, $evidence));
            BusinessDateRule::check($businessDate, $prefilled);
            $money = Money::of($amount);

            if (! in_array($kind, OtherCash::DISBURSEMENT_KINDS, true) || ! $money->isPositive()) {
                throw Refused::rejected('C-14', ['reason' => 'amount']);
            }

            if (DB::table('company_bank_accounts')->where('id', $accountId)->where('company_id', $companyId)->where('is_active', true)->doesntExist()) {
                throw Refused::rejected('C-32', ['reason' => 'account']);
            }

            self::ensureProof($context, $companyId, $evidence, $kind === 'TAX_REMITTANCE' && $reference['tax_evidence'] !== null);
            $columns = $this->reference($context, $companyId, $kind, $money, $reference, $businessDate);
            $now = $context->now();
            $actor = $context->request->actorUserId;
            $command = $context->request->commandId;
            $publicId = (string) Str::uuid7();
            $id = (int) DB::table('disbursements')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'business_date' => $businessDate, 'amount' => $money->toString(), 'kind' => $kind, 'company_bank_account_id' => $accountId,
                ...$columns, 'command_id' => $command, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            GuardUpdate::bear($context->connection, 'money_fact_balances', ['disbursement_id' => $id], ['company_id' => $companyId, 'fact_kind' => OtherCash::factKind($kind), 'amount' => $money->toString(), 'recorded_at' => $now]);
            $linkage = AuditLinkage::make();

            foreach (array_values(array_unique($evidence)) as $line => $document) {
                DB::table('evidence_links')->insert(['evidence_document_id' => $document, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'role' => $line === 0 ? 'PRIMARY_PROOF' : 'SUPPORTING', 'disbursement_id' => $id,
                    'command_id' => $command, 'command_line_no' => $line + 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
                self::linkPublic($linkage, 'evidence_documents', 'evidence_document', $document);
            }

            if (isset($columns['purchase_id'])) {
                self::linkPublic($linkage, 'purchases', 'purchase', $columns['purchase_id']);
            }

            if (isset($columns['tax_evidence_id'])) {
                self::linkPublic($linkage, 'evidence_documents', 'evidence_document', $columns['tax_evidence_id']);
            }

            $this->audit->record(
                action: 'FIN.DisbursementRecorded',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $actor,
                companyId: $companyId,
                entityType: 'disbursement',
                entityId: $id,
                entityPublicId: $publicId,
                commandId: $command,
                change: AuditChange::created(['amount' => $money->toString(), 'kind' => $kind, 'account' => RowHandle::of('company_bank_accounts', $accountId),
                    ...(isset($columns['expense_id']) ? ['expense' => RowHandle::of('expenses', $columns['expense_id'])] : []), ...(isset($columns['treatment_rule_id']) ? ['treatment_rule' => RowHandle::of('tax_treatment_rules', $columns['treatment_rule_id'])] : [])]),
                linkage: $linkage,
                businessDate: $businessDate,
            );

            return new CommandResult('disbursement', $id);
        });
    }

    /**
     * The kind's reference, checked under its lock: the company's purchase not cancelled, its expense still standing, its tax payment proof with room and the rule in force.
     *
     * @param  array{purchase: ?int, expense: ?int, tax_evidence: ?int, tax_type: ?int}  $reference
     * @return array{purchase_id?: int, expense_id?: int, tax_evidence_id?: int, tax_evidence_type?: string, treatment_rule_id?: int}
     */
    private function reference(CommandContext $context, int $companyId, string $kind, Money $money, array $reference, string $businessDate): array
    {
        $named = array_filter(['purchase' => $reference['purchase'], 'expense' => $reference['expense'], 'tax_evidence' => $reference['tax_evidence']], fn (?int $id): bool => $id !== null);
        $wanted = match ($kind) {
            'PURCHASE_PAYMENT' => ['purchase'],
            'EXPENSE_PAYMENT' => ['expense'],
            'TAX_REMITTANCE' => ['tax_evidence'],
            default => [],
        };

        if (array_keys($named) !== $wanted) {
            throw Refused::rejected('C-14', ['reason' => 'reference']);
        }

        if ($kind === 'PURCHASE_PAYMENT') {
            $state = DB::table('purchases')->where('id', $reference['purchase'])->where('company_id', $companyId)->value('state');

            // CS-04: another company's purchase is refused like an unknown one; LR-10: a cancellation committed first is met here.
            if (! is_string($state)) {
                throw Refused::rejected('C-14', ['reason' => 'purchase']);
            }

            if ($state === 'CANCELLED') {
                throw Refused::conflict('ST-02', ['state' => $state]);
            }

            return ['purchase_id' => (int) $reference['purchase']];
        }

        if ($kind === 'EXPENSE_PAYMENT') {
            $standing = DB::table('expenses as e')->join('money_fact_balances as f', 'f.expense_id', '=', 'e.id')->where('e.id', $reference['expense'])->where('e.company_id', $companyId)->whereColumn('f.contra_amount', '<', 'f.amount')->exists();

            if (! $standing) {
                throw Refused::rejected('C-14', ['reason' => 'expense']);
            }

            return ['expense_id' => (int) $reference['expense']];
        }

        if ($kind === 'TAX_REMITTANCE') {
            return $this->remittance($context, $companyId, $money, (int) $reference['tax_evidence'], $reference['tax_type'], $businessDate);
        }

        return [];
    }

    /**
     * BR-FIN-17; L-43: the TAX_PAYMENT_PROOF of the company cited for the amount within what it proves (C-18), under the remittance rule in force for the tax type — remitted output
     * tax never becomes cost. A rule turning the remittance into an expense has no expense row the schema admits (`expenses_c58_evidence_by_kind`): refused, never posted otherwise.
     *
     * @return array{tax_evidence_id: int, tax_evidence_type: string, treatment_rule_id: int}
     */
    private function remittance(CommandContext $context, int $companyId, Money $money, int $evidenceId, ?int $taxTypeId, string $businessDate): array
    {
        if (DB::table('evidence_documents')->where('id', $evidenceId)->where('company_id', $companyId)->where('evidence_type', 'TAX_PAYMENT_PROOF')->doesntExist()) {
            throw Refused::rejected('C-58', ['reason' => 'tax_evidence']);
        }

        $rule = $taxTypeId === null ? null : $this->tax->treatmentRule($companyId, 'TAX_REMITTANCE', $taxTypeId, $businessDate);

        if ($rule === null) {
            throw Refused::rejected('BR-FIN-17', ['reason' => 'no_rule']);
        }

        if ($rule['treatment'] !== 'REMITTANCE_NOT_COST') {
            throw Refused::rejected('BR-FIN-17', ['reason' => 'treatment']);
        }

        $uncited = Money::of($this->evidence->uncited($context, $evidenceId));

        if ($money->compareTo($uncited) > 0) {
            throw Refused::rejected('C-18', ['reason' => 'above_proven', 'uncited' => $uncited->toString(), 'amount' => $money->toString()]);
        }

        $this->evidence->cite($context, $evidenceId, 'TAX_REMITTANCE', $money->toString());

        return ['tax_evidence_id' => $evidenceId, 'tax_evidence_type' => 'TAX_PAYMENT_PROOF', 'treatment_rule_id' => $rule['id']];
    }

    /**
     * L-29: the money leaving is proven — a payment or transfer proof of the company the actor may see, never a rendition it issued itself; a remittance's tax payment proof is its own.
     *
     * @param  list<int>  $evidence
     */
    private static function ensureProof(CommandContext $context, int $companyId, array $evidence, bool $taxProof): void
    {
        if ($evidence === [] && ! $taxProof) {
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

    private static function linkPublic(AuditLinkage $linkage, string $table, string $entityType, int $id): void
    {
        $publicId = DB::table($table)->where('id', $id)->value('public_id');

        if (is_string($publicId)) {
            $linkage->link(AuditRelation::Affects, $entityType, $publicId);
        }
    }
}
