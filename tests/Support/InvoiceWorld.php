<?php

namespace Tests\Support;

use App\Modules\DOC\Actions\SaveDocumentDraft as SaveDocument;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\FIN\Actions\BillInvoice;
use App\Modules\FIN\Actions\CorrectBilling;
use App\Modules\FIN\Actions\IssueInvoice;
use App\Modules\FIN\Actions\IssuePrepaymentKuitansi;
use App\Modules\FIN\Actions\SaveInvoiceDraft;
use App\Modules\FIN\Actions\SetDisputeHold;
use App\Modules\FIN\Actions\SupersedeWriteOff;
use App\Modules\FIN\Actions\VoidInvoice;
use App\Modules\FIN\Actions\WriteOffReceivable;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\ReceivablePredicate;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\FIN\DeductionSettlementFactory;
use Database\Factories\FIN\PaymentFactory;
use Database\Factories\ORG\TaxRateVersionFactory;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Database\Factories\ORG\TaxTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TASK-049's world (TEST_STRATEGY §5, §6): TASK-038's company, client and service — ten units of Rp250,000, a confirmed value of Rp2,500,000 — with an actor holding the
 * invoice family, the numbering of the layouts started and, when asked, an output tax of 11%. Billing, payments, settlements and write-offs are TASK-050–TASK-052's: until
 * they exist their reductions are synthetic here, posted to the guards as those commands will post them, so AX-22's dispositions run on rows and guards that hold.
 */
final class InvoiceWorld
{
    public const CAPABILITIES = ['projects.view', 'projects.confirm', 'finance.view', 'invoice.issue', 'invoice.correct', 'documents.issue', 'documents.correct', 'payment.record', 'evidence.view', 'evidence.upload', 'billing.record', 'billing.correct', 'receivables.dispute', 'payment.apply', 'payment.reallocate', 'finance.contra'];

    public ReservationWorld $base;

    public int $actorId;

    public Company $company;

    private function __construct() {}

    public static function make(): self
    {
        $world = new self;
        $world->base = ReservationWorld::make();
        $world->company = $world->base->company;
        $world->actorId = Authorization::admin([$world->company->id], self::CAPABILITIES)->id;
        $world->numbering();

        return $world;
    }

    /** @param  list<string>  $modes */
    public function project(array $modes = ['SERVICE'], string $qty = '10', bool $confirm = true): Project
    {
        return $this->base->project($modes, $qty, $confirm, '250000');
    }

    public static function line(Project $project, int $number = 1): int
    {
        return ReservationWorld::line($project, $number);
    }

    /**
     * A later confirmation of the project's first line, its pin as the current one with the fields given; its id. Synthetic, the guard unmoved.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function repin(Project $project, array $fields): int
    {
        $line = self::line($project);
        $pin = (array) DB::table('confirmation_line_pins')->where('id', DB::table('project_lines')->where('id', $line)->value('current_pin_id'))->sole();
        $confirmation = (array) DB::table('commercial_confirmations')->where('id', $pin['commercial_confirmation_id'])->sole();
        $first = $confirmation['id'];
        unset($confirmation['id'], $pin['id']);
        $next = DB::table('commercial_confirmations')->insertGetId([...$confirmation, 'reason' => 'Konfirmasi ulang', 'supersedes_confirmation_id' => $first, 'command_id' => (string) Str::uuid()]);
        $id = DB::table('confirmation_line_pins')->insertGetId([...$pin, 'commercial_confirmation_id' => $next, 'command_id' => (string) Str::uuid(), ...$fields]);
        DB::table('project_lines')->where('id', $line)->update(['current_pin_id' => $id]);

        return $id;
    }

    public static function ref(Project $project): ProjectRef
    {
        return new ProjectRef($project->id, $project->company_id, $project->public_id);
    }

    /** The output tax of 11%, its SALES_INVOICE rule global; its code. */
    public function tax(string $rate = '11.000000', string $from = '2026-01-01'): string
    {
        return self::outputTax(null, $rate, $from);
    }

    /** The output tax, its rule one company's or global; its code. */
    public static function outputTax(?int $companyId, string $rate = '11.000000', string $from = '2026-01-01'): string
    {
        $type = TaxTypeFactory::new()->createOne(['category' => 'OUTPUT_TAX', 'code' => 'PPN-'.Str::upper(Str::random(8))]);
        TaxRateVersionFactory::new()->createOne(['tax_type_id' => $type->id, 'rate_percent' => $rate, 'base_numerator' => 1, 'base_denominator' => 1, 'effective' => "[{$from},)"]);
        TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $type->id, 'company_id' => $companyId, 'transaction_context' => 'SALES_INVOICE', 'treatment' => 'OUTPUT_EXCLUDED_FROM_REVENUE', 'effective' => "[{$from},)"]);

        return $type->code;
    }

    /** The layouts' numbering for the current year, go-live before it (NM-03). */
    public function numbering(): void
    {
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");

        foreach ([[SequenceType::Doc06, 'INV'], [SequenceType::Doc04, 'NOTA'], [SequenceType::Doc05, 'KW'], [SequenceType::Doc13, 'SPP']] as [$type, $prefix]) {
            Numbering::scheme($this->company, $type, "{$year}-01-01", 'YEAR', "{$prefix}/{tahun}/{nomor:4}");
            Numbering::started($this->company, $type, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        }
    }

    /**
     * The content of a value-only invoice, or of an itemized one of `quantity` of the first line.
     *
     * @return array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}
     */
    public static function valueOnly(string $value, ?string $date = null): array
    {
        return ['kind' => 'VALUE_ONLY', 'business_date' => $date ?? BusinessDate::today(), 'lines' => [['description' => 'Termin pekerjaan', 'value' => $value]], 'taxes' => []];
    }

    /** A DRAFT value-only invoice through ROUTE-241's action; its id. */
    public function draft(Project $project, string $value, ?string $date = null): int
    {
        $outcome = app(SaveInvoiceDraft::class)->run((string) Str::uuid(), $this->actorId, self::ref($project), null, null, self::valueOnly($value, $date), ['value' => $value, 'date' => $date]);

        return $outcome->kind === Outcome::COMMITTED ? (int) $outcome->resultId : throw new RuntimeException("The draft was {$outcome->kind} {$outcome->code}.");
    }

    /** An issued value-only invoice through ROUTE-241's and ROUTE-242's actions; its id. */
    public function issued(Project $project, string $value, string $layout = 'DOC-06'): int
    {
        $id = $this->draft($project, $value);
        $word = self::issue($this->actorId, $project->id, $id, $layout);

        return $word === 'COMMITTED' ? $id : throw new RuntimeException("The issue was {$word}.");
    }

    /** ROUTE-242's action as its own command, answered as a word, by identifiers only. */
    public static function issue(int $actorUserId, int $projectId, int $invoiceId, string $layout = 'DOC-06', ?string $date = null): string
    {
        $project = Project::query()->findOrFail($projectId);
        $invoice = Invoice::query()->findOrFail($invoiceId);

        return self::word(app(IssueInvoice::class)->run((string) Str::uuid(), $actorUserId, self::ref($project), $invoice, $invoice->lock_version, $layout, $date ?? BusinessDate::today(), false));
    }

    /** A billing act as TASK-050 will write it: the guard's current act and due date (WF-FIN-02). */
    public static function bill(int $invoiceId, string $billedAt, ?string $dueDate, ?int $actorUserId = null): int
    {
        $actor = $actorUserId ?? (int) DB::table('invoices')->where('id', $invoiceId)->value('recorded_by');
        $act = DB::table('invoice_billing_acts')->insertGetId(['invoice_id' => $invoiceId, 'billed_at' => $billedAt, 'due_date' => $dueDate, 'kind' => 'BILLING', 'decided_by' => $actor, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $actor]);
        DB::table('invoice_balances')->where('invoice_id', $invoiceId)->update(['current_billing_act_id' => $act, 'current_due_date' => $dueDate]);

        return $act;
    }

    /** A payment of the invoice's client with its source guard, as TASK-051 will post it. */
    public static function payment(int $invoiceId, string $amount, string $claimed = '0.00', ?int $recoveryFor = null, ?int $clientId = null): Payment
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $payment = PaymentFactory::new()->createOne(['company_id' => $invoice->company_id, 'client_organization_id' => $clientId ?? $invoice->client_organization_id, 'amount' => $amount, 'recovery_for_invoice_id' => $recoveryFor, 'business_date' => BusinessDate::today()]);
        DB::table('application_source_balances')->insert(['payment_id' => $payment->id, 'company_id' => $invoice->company_id, 'client_organization_id' => $clientId ?? $invoice->client_organization_id, 'gross_amount' => $amount, 'claimed_deductions_amount' => $claimed, 'recorded_at' => now()]);

        return $payment;
    }

    /** An application on the invoice, its guards moved as AX-18 will move them; its id. */
    public static function apply(int $invoiceId, Payment $payment, string $amount): int
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $id = DB::table('payment_applications')->insertGetId(['company_id' => $invoice->company_id, 'client_organization_id' => $invoice->client_organization_id, 'payment_id' => $payment->id, 'invoice_id' => $invoiceId, 'target_kind' => 'INVOICE', 'amount' => $amount, 'business_date' => BusinessDate::today(),
            'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $invoice->recorded_by]);
        DB::table('invoice_balances')->where('invoice_id', $invoiceId)->update(['applied_amount' => DB::raw("applied_amount + {$amount}")]);
        DB::table('application_source_balances')->where('payment_id', $payment->id)->update(['invoice_applied_amount' => DB::raw("invoice_applied_amount + {$amount}")]);

        return $id;
    }

    /** A settlement on the invoice, its guards moved as AX-21 will move them; its id. */
    public static function settle(int $invoiceId, Payment $payment, string $amount, string $class = 'FEE', ?string $direction = null, ?int $taxTypeId = null): int
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $evidenceType = $class === 'FEE' ? 'FEE_ADVICE' : ($direction === 'COLLECTED' ? 'TAX_COLLECTION_PROOF' : 'TAX_WITHHOLDING_PROOF');
        $evidence = DeductionSettlementFactory::evidence($invoice->company_id, $evidenceType);
        DB::table('evidence_balances')->updateOrInsert(['evidence_document_id' => $evidence->id], ['proven_amount' => $amount, 'cited_amount' => $amount, 'recorded_at' => now()]);
        $id = DeductionSettlementFactory::new()->createOne(['payment_id' => $payment->id, 'company_id' => $invoice->company_id, 'client_organization_id' => $invoice->client_organization_id, 'invoice_id' => $invoiceId, 'settlement_class' => $class,
            'fee_type' => $class === 'FEE' ? 'BIAYA_TRANSFER' : null, 'tax_type_id' => $taxTypeId, 'tax_direction' => $direction, 'specific_type_key' => $class === 'FEE' ? 'BIAYA_TRANSFER' : "{$taxTypeId}:{$direction}", 'treatment_rule_id' => $taxTypeId === null ? null : DB::table('tax_treatment_rules')->where('tax_type_id', $taxTypeId)->value('id'), 'amount' => $amount, 'evidence_document_id' => $evidence->id, 'evidence_type' => $evidenceType, 'business_date' => BusinessDate::today()])->id;
        DB::table('money_fact_balances')->insert(['company_id' => $invoice->company_id, 'deduction_settlement_id' => $id, 'fact_kind' => 'SETTLEMENT', 'amount' => $amount, 'recorded_at' => now()]);
        $column = $class === 'FEE' ? 'fee_settled_amount' : 'tax_settled_amount';
        DB::table('invoice_balances')->where('invoice_id', $invoiceId)->update([$column => DB::raw("{$column} + {$amount}"), 'collected_tax_settled_amount' => DB::raw('collected_tax_settled_amount + '.($direction === 'COLLECTED' ? $amount : '0'))]);
        DB::table('application_source_balances')->where('payment_id', $payment->id)->update(['settled_amount' => DB::raw("settled_amount + {$amount}")]);

        return $id;
    }

    /** A standing write-off on the invoice, its guards moved as AX-24 will move them; its id. */
    public static function writeOff(int $invoiceId, string $amount, int $ownerId): int
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $id = DB::table('receivable_writeoffs')->insertGetId(['company_id' => $invoice->company_id, 'invoice_id' => $invoiceId, 'amount' => $amount, 'reason' => 'Tidak tertagih', 'outstanding_before' => $amount, 'outstanding_after' => '0.00', 'decided_by' => $ownerId, 'business_date' => BusinessDate::today(),
            'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $ownerId]);
        DB::table('money_fact_balances')->insert(['company_id' => $invoice->company_id, 'receivable_writeoff_id' => $id, 'fact_kind' => 'WRITEOFF', 'amount' => $amount, 'recorded_at' => now()]);
        DB::table('invoice_balances')->where('invoice_id', $invoiceId)->update(['written_off_amount' => DB::raw("written_off_amount + {$amount}")]);

        return $id;
    }

    /** An application as AX-18 runs it: LK-25 and LK-26 held in its own command, for a race with a correction (M-37). */
    public static function applyInCommand(int $actorUserId, int $invoiceId, int $paymentId, string $amount): string
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $request = new CommandRequest((string) Str::uuid(), 'FIN.SyntheticApplication', $actorUserId, $invoice->company_id, 'payment.record', Fingerprint::of('FIN.SyntheticApplication', 'test', ['invoice' => $invoiceId, 'amount' => $amount]));
        $outcome = app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($invoice, $invoiceId, $paymentId, $amount): CommandResult {
            $context->lock((new LockSet)->add('invoices', ['id' => $invoiceId])->add('application_source_balances', ['payment_id' => $paymentId]));

            if (DB::table('invoices')->where('id', $invoiceId)->value('state') !== 'ISSUED') {
                throw Refused::rejected('C-15', ['reason' => 'void']);
            }

            $id = DB::table('payment_applications')->insertGetId(['company_id' => $invoice->company_id, 'client_organization_id' => $invoice->client_organization_id, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'target_kind' => 'INVOICE', 'amount' => $amount, 'business_date' => BusinessDate::today(),
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            GuardUpdate::apply($context->connection, 'invoice_balances', [['key' => ['invoice_id' => $invoiceId], 'deltas' => ['applied_amount' => $amount]]]);
            GuardUpdate::apply($context->connection, 'application_source_balances', [['key' => ['payment_id' => $paymentId], 'deltas' => ['invoice_applied_amount' => $amount]]]);

            return new CommandResult('payment_application', $id);
        });

        return self::word($outcome);
    }

    public static function handle(string $table, int $id): string
    {
        return RowHandle::of($table, $id);
    }

    public static function publicId(int $invoiceId): string
    {
        return (string) DB::table('invoices')->where('id', $invoiceId)->value('public_id');
    }

    /** Runs a closure inside a transaction until a session waits on it, for the race tests. */
    public static function hold(Closure $command, string $point): string
    {
        return ReservationWorld::hold($command, $point);
    }

    /** A payment recorded for a written-off invoice, unapplied: credit linked to it (WF-FIN-05; QS-12), as TASK-051 will record it; its row. */
    public static function recovery(int $invoiceId, string $amount): Payment
    {
        return self::payment($invoiceId, $amount, '0.00', $invoiceId);
    }

    /** ROUTE-243's action as its own command, for the race tests; its word. */
    public static function billInCommand(int $actorUserId, int $invoiceId, string $billedAt, string $dueDate): string
    {
        return self::word(app(BillInvoice::class)->run((string) Str::uuid(), $actorUserId, Invoice::query()->findOrFail($invoiceId), $billedAt, $dueDate, false));
    }

    /** ROUTE-244's action as its own command, for the race tests; its word. */
    public static function correctBillingInCommand(int $actorUserId, int $invoiceId, int $evidenceId, string $billedAt, string $dueDate): string
    {
        return self::word(app(CorrectBilling::class)->run((string) Str::uuid(), $actorUserId, Invoice::query()->findOrFail($invoiceId), $evidenceId, $billedAt, $dueDate, false, 'Tanggal salah catat'));
    }

    /** ROUTE-246's set as its own command, for the race tests; its word. */
    public static function holdInCommand(int $actorUserId, int $invoiceId): string
    {
        return self::word(app(SetDisputeHold::class)->run((string) Str::uuid(), $actorUserId, Invoice::query()->findOrFail($invoiceId), 'Klien membantah'));
    }

    /** ROUTE-247's action as its own command, for the race tests; its word. */
    public static function writeOffInCommand(int $ownerId, int $invoiceId, string $amount, string $businessDate): string
    {
        return self::word(app(WriteOffReceivable::class)->run((string) Str::uuid(), $ownerId, Invoice::query()->findOrFail($invoiceId), $amount, 'Klien tidak dapat ditagih', null, $businessDate, false));
    }

    /** ROUTE-248's action as its own command, for the race tests; its word. */
    public static function supersedeInCommand(int $ownerId, int $invoiceId, int $writeoffId, int $paymentId, string $amount, string $businessDate): string
    {
        return self::word(app(SupersedeWriteOff::class)->run((string) Str::uuid(), $ownerId, Invoice::query()->findOrFail($invoiceId), $writeoffId, $paymentId, $amount, 'Klien membayar kembali', $businessDate, false));
    }

    /** A project already closed, whatever it still holds open (L-10), its state and transition as TASK-055 will record them. */
    public static function close(int $projectId, int $actorUserId): void
    {
        $company = DB::table('projects')->where('id', $projectId)->value('company_id');
        $from = DB::table('projects')->where('id', $projectId)->value('state');
        DB::table('projects')->where('id', $projectId)->update(['state' => 'COMPLETED_NORMAL', 'lock_version' => DB::raw('lock_version + 1')]);
        ProjectStateTransition::factory()->create(['project_id' => $projectId, 'from_state' => $from, 'to_state' => 'COMPLETED_NORMAL', 'transition_kind' => 'COMPLETE_NORMAL', 'decided_by' => $actorUserId, 'company_id' => $company]);
    }

    /**
     * A completion's reading of FIN's predicates under the project's LK-01, standing in for TASK-055's AX-25 (M-26): an unmet one REJECTED C-39, else the project completed; its word.
     */
    public static function completeInCommand(int $actorUserId, int $projectId): string
    {
        $project = Project::query()->findOrFail($projectId);
        $request = new CommandRequest((string) Str::uuid(), 'PRJ.SyntheticCompletion', $actorUserId, $project->company_id, 'finance.view', Fingerprint::of('PRJ.SyntheticCompletion', 'test', ['project' => $projectId]));

        return self::word(app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($project, $projectId, $actorUserId): CommandResult {
            $context->lock((new LockSet)->add('projects', ['id' => $projectId]));

            foreach (app(ReceivablePredicate::class)->states([$projectId], true)[$projectId] ?? [] as $state) {
                if (! $state->met()) {
                    throw Refused::rejected('C-39', ['reason' => 'predicate']);
                }
            }

            $from = DB::table('projects')->where('id', $projectId)->value('state');
            DB::table('projects')->where('id', $projectId)->update(['state' => 'COMPLETED_NORMAL', 'lock_version' => DB::raw('lock_version + 1')]);
            ProjectStateTransition::factory()->create(['project_id' => $projectId, 'from_state' => $from, 'to_state' => 'COMPLETED_NORMAL', 'transition_kind' => 'COMPLETE_NORMAL', 'decided_by' => $actorUserId, 'company_id' => $project->company_id]);

            return new CommandResult('project', $projectId);
        }));
    }

    /** A registered evidence linked to the project, as ROUTE-* LinkEvidence leaves it; its row. */
    public static function linkedEvidence(int $companyId, int $projectId): EvidenceDocument
    {
        $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $companyId, 'evidence_type' => 'OTHER']);
        DB::table('evidence_links')->insert(['evidence_document_id' => $evidence->id, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'role' => 'SUPPORTING', 'project_id' => $projectId, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('projects')->where('id', $projectId)->value('recorded_by')]);

        return $evidence;
    }

    /** A void of an unbilled entry error through its own command, for the race tests; its word. */
    public static function voidInCommand(int $actorUserId, int $invoiceId): string
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);

        return self::word(app(VoidInvoice::class)->run((string) Str::uuid(), $actorUserId, $invoice, $invoice->lock_version, new InvoiceCorrection('UNBILLED_ENTRY_ERROR', 'Salah catat', BusinessDate::today(), false), ['invoice' => $invoiceId]));
    }

    /** A save of the draft at the lock it was read at, for the race tests; its word. */
    public static function saveInCommand(int $actorUserId, int $projectId, int $invoiceId, int $lock, string $value): string
    {
        $project = Project::query()->findOrFail($projectId);

        return self::word(app(SaveInvoiceDraft::class)->run((string) Str::uuid(), $actorUserId, self::ref($project), Invoice::query()->findOrFail($invoiceId), $lock, self::valueOnly($value), ['value' => $value]));
    }

    /** ROUTE-221's draft of a pre-payment Kuitansi with the amount entered; the document's id. */
    public static function prepareKuitansi(int $actorUserId, int $invoiceId, string $amount): int
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $outcome = app(SaveDocument::class)->prepare((string) Str::uuid(), $actorUserId, new DocumentSubject('DOC-05', 'PRE_PAYMENT', $invoice->company_id, ['invoice_id' => $invoiceId]), $amount);

        return $outcome->kind === Outcome::COMMITTED ? (int) $outcome->resultId : throw new RuntimeException("prepare {$outcome->kind} {$outcome->code}");
    }

    /** ROUTE-249's action as its own command; its word. */
    public static function issueKuitansi(int $actorUserId, int $invoiceId, int $documentId, string $date): string
    {
        $draft = DB::table('document_versions')->where('document_id', $documentId)->where('state', 'DRAFT')->sole();

        return self::word(app(IssuePrepaymentKuitansi::class)->run((string) Str::uuid(), $actorUserId, Invoice::query()->findOrFail($invoiceId), $documentId, (int) $draft->lock_version, (string) $draft->public_id, $date, false, null, 'Proses pembayaran klien'));
    }

    public static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
