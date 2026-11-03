<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\PaymentApplications;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
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
use Illuminate\Support\Str;

/**
 * `FIN RefundCredit` (ROUTE-256; INT-256; AX-23; WF-FIN-06; L-29; L-46; C-13; C-44) — "Kembalikan Dana" on PG-048: a REFUND disbursement — Cash-Out, never negative
 * Cash-In, never cost — from the company's account with its transfer evidence, and the one ACTIVE REFUND-target application of evidenced credit that backs it, committed
 * together, fully backed at creation. Its consumed application is final while the refund stands: only its typed exits end it (TASK-051's re-recording and contra, TASK-052's
 * refund contra). `refund.record` (ADM_PLUS) of the source's company.
 */
final class RefundCredit
{
    public const TYPE = 'FIN.RefundCredit';

    public const CAPABILITY = 'refund.record';

    /** L-29 as PG-046 flags it (`PaymentList::WITHOUT_PROOF`): its reason stands, no line cited, no proof linked. */
    private const WITHOUT_PROOF = "p.unevidenced_reason is not null and p.bank_statement_line_id is null and not exists (select 1 from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.payment_id = p.id and k.unlinked_at is null and e.evidence_type in ('PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'))";

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly MoneyPosting $money,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * AX-23's lock set: the source with its payment or opening credit (LK-26) — so its proof is not unlinked meanwhile —, the account (LK-03), the transfer evidence (LK-29)
     * and the projects of the invoices the consumed source has been applied to (LK-01).
     *
     * @param  array<string, int>  $source  `payment_id` or `opening_customer_credit_id`
     */
    public static function locks(array $source, int $accountId, int $evidenceId): LockSet
    {
        $invoices = DB::table('payment_applications')->where($source)->where('target_kind', 'INVOICE')->distinct()->pluck('invoice_id')->all();

        return PaymentApplications::invoiceLocks(array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $invoices)))
            ->union(EvidenceGuard::locks($evidenceId))->add('application_source_balances', $source)->add('company_bank_accounts', ['id' => $accountId])
            ->add(isset($source['payment_id']) ? 'payments' : 'opening_customer_credits', ['id' => $source['payment_id'] ?? $source['opening_customer_credit_id']]);
    }

    /**
     * @param  array{payment: ?int, credit: ?int, company: int, client: int, name: string}  $source
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, array $source, string $amount, int $accountId, int $evidenceId, string $reason, string $businessDate, bool $prefilled, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $source['company'], self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($source['company']), [...$payload, 'source' => $source['name']]));
        $key = $source['payment'] !== null ? ['payment_id' => $source['payment']] : ['opening_customer_credit_id' => (int) $source['credit']];

        return $this->envelope->run($request, function (CommandContext $context) use ($source, $key, $amount, $accountId, $evidenceId, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(self::locks($key, $accountId, $evidenceId));
            // DATA-DEPENDENT: an application committed meanwhile may have reached another project's invoice.
            $context->require(self::locks($key, $accountId, $evidenceId));
            BusinessDateRule::check($businessDate, $prefilled);
            $money = Money::of($amount);

            if (! $money->isPositive()) {
                throw Refused::rejected('C-13', ['reason' => 'amount']);
            }

            // L-29: only evidenced credit leaves the company; an opening credit carries its signed opening evidence.
            if ($source['payment'] !== null && DB::table('payments as p')->where('p.id', $source['payment'])->whereRaw(self::WITHOUT_PROOF)->exists()) {
                throw Refused::rejected('L-29', ['reason' => 'credit_unevidenced']);
            }

            if (! DB::table('evidence_documents')->where('id', $evidenceId)->where('company_id', $source['company'])->where('evidence_type', 'TRANSFER_PROOF')->exists() || EvidenceGuard::companyIssued([$evidenceId])) {
                throw Refused::rejected('L-29', ['reason' => 'transfer_proof']);
            }

            if (! DB::table('company_bank_accounts')->where('id', $accountId)->where('company_id', $source['company'])->whereNull('deactivated_at')->exists()) {
                throw Refused::rejected('C-14', ['reason' => 'account']);
            }

            // C-13; annex 3: no more than the source still holds of real money.
            $guard = MoneyPosting::source($key);
            $capacity = $guard === null ? '0.00' : Row::text($guard, 'capacity_amount');

            if ($money->compareTo(Money::of($capacity)) > 0) {
                throw Refused::rejected('C-13', ['reason' => 'above_capacity', 'capacity' => $capacity, 'amount' => $money->toString()]);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $command = $context->request->commandId;
            $publicId = (string) Str::uuid7();
            $refund = (int) DB::table('disbursements')->insertGetId([
                'public_id' => $publicId, 'company_id' => $source['company'], 'client_organization_id' => $source['client'], 'business_date' => $businessDate, 'amount' => $money->toString(),
                'kind' => 'REFUND', 'company_bank_account_id' => $accountId, 'command_id' => $command, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            // C-44: born fully backed — its fact's backing equals its amount, with no shortfall.
            GuardUpdate::bear($context->connection, 'money_fact_balances', ['disbursement_id' => $refund], ['company_id' => $source['company'], 'fact_kind' => 'REFUND', 'amount' => $money->toString(), 'backed_amount' => $money->toString(), 'recorded_at' => $now]);
            DB::table('payment_applications')->insert([...$key, 'company_id' => $source['company'], 'client_organization_id' => $source['client'], 'refund_disbursement_id' => $refund, 'target_kind' => 'REFUND',
                'amount' => $money->toString(), 'business_date' => $businessDate, 'reason' => $reason, 'command_id' => $command, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
            $this->money->moveSource($context, $key, ['refund_backed_amount' => $money->toString()]);
            DB::table('evidence_links')->insert(['evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $source['company'], 'role' => 'PRIMARY_PROOF', 'disbursement_id' => $refund,
                'command_id' => $command, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor]);

            $this->audit->record(
                action: 'FIN.CreditRefunded',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $actor,
                companyId: $source['company'],
                entityType: 'disbursement',
                entityId: $refund,
                entityPublicId: $publicId,
                reason: $reason,
                commandId: $command,
                change: AuditChange::created(['amount' => $money->toString(), 'source' => $source['name'], 'evidence' => RowHandle::of('evidence_documents', $evidenceId)]),
                businessDate: $businessDate,
            );

            return new CommandResult('disbursement', $refund);
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED && $outcome->code === 'C-13'
            ? ['reason' => 'above_capacity', 'capacity' => Row::text(MoneyPosting::source($key) ?? (object) ['capacity_amount' => '0.00'], 'capacity_amount')]
            : null);
    }
}
