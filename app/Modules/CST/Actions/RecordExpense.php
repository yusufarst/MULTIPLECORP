<?php

namespace App\Modules\CST\Actions;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Services\Revalidation;
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
use App\Support\Money;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;

/**
 * `CST RecordExpense` (ROUTE-257; INT-257; NX-10; CALC-09; CALC-11; L-43; C-20; C-23) — "Catat Beban" on PG-049: the actual cost of its causal project where one exists, otherwise the
 * company's, with its evidence; a source bill it cites directly raising that bill's cited amount, so one bill caps its charges and expenses together; never a category only a
 * settlement or a charge posts. A cost on a completed project returns it to *Aktif* (SF-REVAL). `expense.record` of the company.
 */
final class RecordExpense
{
    public const TYPE = 'CST.RecordExpense';

    public const CAPABILITY = 'expense.record';

    /** The categories only a settlement or a charge posts (C-23; BR-PUR-05). */
    public const RESERVED = ['FEE_DEDUCTION', 'TAX_TREATMENT', 'PURCHASE_CHARGE'];

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CostPosting $cost,
        private readonly Revalidation $revalidation,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * NX-10's lock set: the project (LK-01), the source bill's guard and each proof (LK-29).
     *
     * @param  list<int>  $evidence
     */
    public static function locks(?int $projectId, ?int $sourceBillId, array $evidence): LockSet
    {
        $set = $projectId === null ? new LockSet : (new LockSet)->add('projects', ['id' => $projectId]);

        foreach (array_values(array_unique([...($sourceBillId === null ? [] : [$sourceBillId]), ...$evidence])) as $document) {
            $set = $set->union(EvidenceGuard::locks($document));
        }

        return $set;
    }

    /**
     * @param  list<int>  $evidence  the expense's proofs, linked to it
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, ?int $projectId, string $category, string $amount, string $businessDate, bool $prefilled, ?int $sourceBillId, array $evidence, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $projectId, $category, $amount, $businessDate, $prefilled, $sourceBillId, $evidence): CommandResult {
            $context->lock(self::locks($projectId, $sourceBillId, $evidence));
            BusinessDateRule::check($businessDate, $prefilled);
            $money = Money::of($amount);
            $category = trim($category);

            // C-23: the fee and tax categories are the settlement's, a charge's its purchase's.
            if (in_array(strtoupper($category), self::RESERVED, true)) {
                throw Refused::rejected('C-23', ['reason' => 'category']);
            }

            if (! $money->isPositive() || $category === '') {
                throw Refused::rejected('C-14', ['reason' => 'amount']);
            }

            if ($projectId !== null && ! DB::table('projects')->where('id', $projectId)->where('company_id', $companyId)->whereIn('state', ['ACTIVE', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'])->exists()) {
                throw Refused::rejected('C-14', ['reason' => 'project']);
            }

            $authority = $context->authority();

            if ($sourceBillId !== null && (! DB::table('evidence_documents')->where('id', $sourceBillId)->where('company_id', $companyId)->where('evidence_type', 'SUPPLIER_BILL')->exists()
                || $authority === null || ! EvidenceTypes::mayView($authority, 'SUPPLIER_BILL', $companyId))) {
                throw Refused::rejected('C-14', ['reason' => 'bill']);
            }

            // L-45: an expense carries its evidence — the bill it cites, or a proof of the company the actor may see (PJ-08).
            if ($evidence === [] && $sourceBillId === null) {
                throw Refused::rejected('L-45', ['reason' => 'evidence']);
            }

            foreach ($evidence as $document) {
                $type = DB::table('evidence_documents')->where('id', $document)->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->value('evidence_type');

                if (! is_string($type) || $authority === null || ! EvidenceTypes::mayView($authority, $type, $companyId)) {
                    throw Refused::rejected('C-14', ['reason' => 'evidence']);
                }

                // L-43; C-20: an amount-bearing document counts only through its citation, so a bill is never a charge's and, uncited, an expense's too.
                if (EvidenceTypes::isAmountBearing($type)) {
                    throw Refused::rejected('L-43', ['reason' => 'amount_bearing']);
                }
            }

            $id = $this->cost->manualExpense($context, $companyId, $projectId, $category, $money, $businessDate, $sourceBillId);
            $now = $context->now();
            $linkage = AuditLinkage::make();

            foreach (array_values(array_unique($evidence)) as $line => $document) {
                DB::table('evidence_links')->insert(['evidence_document_id' => $document, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'role' => $line === 0 ? 'PRIMARY_PROOF' : 'SUPPORTING', 'expense_id' => $id,
                    'command_id' => $context->request->commandId, 'command_line_no' => $line + 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
            }

            // BR-XC-01: the project it costs, the bill it cites and its evidence, by their public identifiers.
            foreach ([['projects', 'project', $projectId], ['evidence_documents', 'evidence_document', $sourceBillId], ...array_map(fn (int $document): array => ['evidence_documents', 'evidence_document', $document], $evidence)] as [$table, $type, $row]) {
                $publicId = $row === null ? null : DB::table($table)->where('id', $row)->value('public_id');
                $linkage = is_string($publicId) ? $linkage->link(AuditRelation::Affects, $type, $publicId) : $linkage;
            }

            $handle = RowHandle::of('expenses', $id);

            if ($projectId !== null) {
                $this->revalidation->revalidate($context, $projectId, "CST.ExpenseRecorded:{$handle}");
            }

            $this->audit->record(
                action: 'CST.ExpenseRecorded',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'expense',
                entityId: $id,
                entityPublicId: null,
                reason: null,
                commandId: $context->request->commandId,
                change: AuditChange::created(['amount' => $money->toString(), 'category' => $category, 'company_level' => $projectId === null]),
                linkage: $linkage,
                businessDate: $businessDate,
            );

            return new CommandResult('expense', $id);
        });
    }
}
