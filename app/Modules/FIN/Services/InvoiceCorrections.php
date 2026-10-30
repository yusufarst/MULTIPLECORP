<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\DOC\Services\DocumentVoiding;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Data\InvoiceDocuments;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceBalance;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * AX-22, an invoice's void or downward revision (CM-23; CM-24; L-06; L-30; BR-CR-03; annex 5), one transaction moving the receivable,
 * source, money-fact and case guards together or the conservation CHECK refusing the commit (C-16): the typed basis a real row of the
 * company (C-56), the entry-error evidence linked to the invoice's records (DIR-092 (2)), every current reduction named in the payload
 * or a CONFLICT with the current list (ST-07), a standing write-off the Owner's alone in the same command (OD-07). An application is
 * re-targeted to the replacement, released to credit traceable to its payment, or kept where the revised value still carries it; a
 * settlement re-recorded on the replacement within what its tax there allows, kept, or left as an unresolved residual on the case that
 * blocks its closure; the replacement named here inherits the billing act, so aging never restarts (C-17). Its documents are voided or
 * revised and the Kuitansi it lowers unlinked (AX-31). Rows it does not change are left as they are, statements proportional to the
 * rows it must (QB-14).
 */
final class InvoiceCorrections
{
    /** The evidence an entry error may rest on (DIR-092 (2); CM-24). */
    public const EVIDENCE = InvoiceCorrection::EVIDENCE;

    public function __construct(
        private readonly InvoiceRecords $records,
        private readonly ReceivablePosting $receivable,
        private readonly DemandPosting $demand,
        private readonly DocumentIssuing $issuing,
        private readonly DocumentVoiding $voiding,
        private readonly DocumentChain $chain,
        private readonly DocumentSubjects $subjects,
        private readonly CorrectionCases $cases,
        private readonly PrepaymentKuitansi $kuitansi,
        private readonly Revalidation $revalidation,
    ) {}

    /**
     * AX-22's lock set, data-dependent: the project (and the case's), the case, the commercial guard, the invoice and its current
     * reductions' sources, money facts and evidence, the chains of its issued layouts.
     */
    public function locks(Invoice $invoice, InvoiceCorrection $input): LockSet
    {
        $set = (new LockSet)->add('invoices', ['id' => $invoice->id]);

        if ($invoice->project_id !== null) {
            $set = $set->add('projects', ['id' => $invoice->project_id])->add('project_commercial_balances', ['project_id' => $invoice->project_id]);
        }

        if ($input->caseId !== null) {
            $set = $set->union(CorrectionCases::locks($input->caseId));
        }

        if ($input->evidenceId !== null) {
            $set = $set->add('evidence_documents', ['id' => $input->evidenceId]);
        }

        $reductions = self::reductions($invoice->id);

        foreach ($reductions['applications'] as $row) {
            $set = $row['payment_id'] !== null ? $set->add('application_source_balances', ['payment_id' => $row['payment_id']]) : $set->add('application_source_balances', ['opening_customer_credit_id' => (int) $row['credit_id']]);
        }

        foreach ($reductions['settlements'] as $id => $row) {
            $set = $set->add('application_source_balances', ['payment_id' => $row['payment_id']])->add('money_fact_balances', ['deduction_settlement_id' => $id])->add('evidence_documents', ['id' => $row['evidence_id']]);
        }

        foreach (array_keys($reductions['writeoffs']) as $id) {
            $set = $set->add('money_fact_balances', ['receivable_writeoff_id' => $id]);
        }

        foreach ($this->chain->ofInvoice($invoice->id, [...InvoiceSubjects::LAYOUTS, InvoiceDocuments::REQUEST]) as $document) {
            $issued = $this->chain->issuedOf($document->id);
            $set = $set->union($this->chain->locks($document, $issued === null ? [] : [$issued->id]));

            if ($issued !== null) {
                $set = $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_VOIDED, $document, $issued->id, null)));

                if ($document->document_type_code !== InvoiceDocuments::REQUEST) {
                    $set = $set->union($this->issuing->numberLocks($invoice->company_id, $document->document_type_code, $input->businessDate));
                }
            }
        }

        // The replacement's layout draws its number too (LK-31), the first draw this command makes after its clock.
        return $input->content === null ? $set : $set->union($this->issuing->numberLocks($invoice->company_id, $this->chain->ofInvoice($invoice->id, InvoiceSubjects::LAYOUTS)[0]->document_type_code ?? InvoiceSubjects::INVOICE, $input->businessDate));
    }

    /**
     * The invoice's current reductions, by id: ACTIVE applications and settlements and uncontra'd write-offs.
     *
     * @return array{applications: array<int, array{amount: string, payment_id: ?int, credit_id: ?int}>, settlements: array<int, array{amount: string, payment_id: int, class: string, direction: ?string, evidence_id: int}>, writeoffs: array<int, array{amount: string}>}
     */
    public static function reductions(int $invoiceId): array
    {
        $found = ['applications' => [], 'settlements' => [], 'writeoffs' => []];

        foreach (DB::table('payment_applications')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->orderBy('id')->get(['id', 'amount', 'payment_id', 'opening_customer_credit_id']) as $row) {
            $found['applications'][(int) Row::text($row, 'id')] = ['amount' => Row::text($row, 'amount'), 'payment_id' => self::id(Row::nullable($row, 'payment_id')), 'credit_id' => self::id(Row::nullable($row, 'opening_customer_credit_id'))];
        }

        foreach (DB::table('deduction_settlements')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->orderBy('id')->get(['id', 'amount', 'payment_id', 'settlement_class', 'tax_direction', 'evidence_document_id']) as $row) {
            $found['settlements'][(int) Row::text($row, 'id')] = ['amount' => Row::text($row, 'amount'), 'payment_id' => (int) Row::text($row, 'payment_id'), 'class' => Row::text($row, 'settlement_class'), 'direction' => Row::nullable($row, 'tax_direction'), 'evidence_id' => (int) Row::text($row, 'evidence_document_id')];
        }

        foreach (DB::table('receivable_writeoffs as w')->where('w.invoice_id', $invoiceId)->leftJoin('money_fact_balances as m', 'm.receivable_writeoff_id', '=', 'w.id')
            ->whereRaw('coalesce(m.contra_amount, 0) < w.amount')->orderBy('w.id')->selectRaw('w.id, w.amount - coalesce(m.contra_amount, 0) as standing')->get() as $row) {
            // What still stands: a part superseded for recovered money is already gone (L-17).
            $found['writeoffs'][(int) Row::text($row, 'id')] = ['amount' => Row::text($row, 'standing')];
        }

        return $found;
    }

    /**
     * Voids the invoice; its value leaves, or moves to the replacement named here (L-30).
     *
     * @return array{invoice: int, versions: list<int>} the invoice, and the layouts' versions the command issued, for the action to hand to the render job
     */
    public function void(CommandContext $context, Invoice $invoice, int $lockVersion, InvoiceCorrection $input): array
    {
        $versions = [];
        [$project, $balance] = $this->admitted($context, $invoice, $lockVersion, $input);
        // C-17, C-44 and C-56 read the void, its replacement and the re-targeted applications together: deferred checks, held to the commit's `set constraints all immediate` whatever an earlier statement set.
        $context->connection->statement('set constraints invoices_c17_inherits_current_act, invoice_billing_acts_c17_inherits_current_act, invoices_c56_replaced_with_replacement, payment_applications_c44_superseded_by_successor deferred');
        $billed = Row::nullable($balance, 'current_billing_act_id') !== null;
        $replacing = $input->content !== null;
        self::voidBasis($invoice, $input, $billed, $replacing);
        self::ensureEvidence($invoice, $input);
        $sales = Row::text($balance, 'sales_value');
        $now = $context->now();
        $replacement = null;
        $layout = $this->chain->ofInvoice($invoice->id, InvoiceSubjects::LAYOUTS)[0]->document_type_code ?? InvoiceSubjects::INVOICE;

        if ($input->content !== null) {
            $replacement = $this->records->create($context, $project, $invoice->client_organization_id, $input->content, $invoice->id, 'INITIAL', 2);
            $version = DB::table('invoices')->where('id', $replacement)->value('current_version_id');
            [$act, $due] = $billed ? $this->inheritAct($context, $replacement, $invoice->id, $balance) : [null, null];
            $this->records->markIssued($context, $project, $replacement, is_numeric($version) ? (int) $version : 0, 0, $input->businessDate, $input->prefilled, $act, $due, $sales);
        } else {
            $this->demand->invoiced($context, $project->id, '-'.$sales);
        }

        $moved = $this->dispose($context, $invoice, $project, $input, $replacement, $input->reason);
        $this->receivable->move($context, $invoice->id, [...$moved['old'], 'gross_value' => '-'.Row::text($balance, 'gross_value'), 'sales_value' => '-'.$sales, 'output_tax_value' => '-'.Row::text($balance, 'output_tax_value')]);

        if ($replacement !== null) {
            $this->receivable->move($context, $replacement, $moved['new']);
        }

        // The basis's row at the void, the replaced root's state moved before the deferred check reads it (C-56).
        $voided = DB::table('invoices')->where('id', $invoice->id)->where('state', 'ISSUED')->where('lock_version', $lockVersion)->update([
            'state' => 'VOIDED', 'voided_by' => $context->request->actorUserId, 'voided_at' => $now, 'voided_reason' => $input->reason, 'void_basis' => $input->basis,
            'void_correction_case_id' => $input->basis === 'RETURN' ? $input->caseId : null,
            'void_confirmation_id' => $input->basis === 'RECONFIRMATION' ? $input->confirmationId : null,
            'void_cancellation_id' => $input->basis === 'CANCELLATION' ? $input->cancellationId : null,
            'void_project_transition_id' => $input->basis === 'CANCELLATION' ? $input->transitionId : null,
            'lock_version' => $lockVersion + 1, 'updated_at' => $now,
        ]);

        if ($voided !== 1) {
            throw Refused::conflict('ST-01');
        }

        DB::table('invoice_versions')->where('id', $invoice->current_version_id)->update(['state' => 'VOIDED', 'updated_at' => $now]);

        // A voided invoice's payment request voids with it: nothing asks for money the invoice no longer claims.
        foreach ($this->chain->ofInvoice($invoice->id, [...InvoiceSubjects::LAYOUTS, InvoiceDocuments::REQUEST]) as $document) {
            $issued = $this->chain->issuedOf($document->id);

            if ($issued !== null) {
                $this->voiding->void($context, $document, $issued, $input->reason);
            }
        }

        if ($replacement !== null) {
            $versions[] = $this->issueLayout($context, $replacement, $layout, $input->businessDate, $input->prefilled);
        }

        $this->kuitansi->reconcile($context, $invoice->id, 'invoices.unlink.corrected');
        $linkage = AuditLinkage::make();

        if ($replacement !== null) {
            $publicId = DB::table('invoices')->where('id', $replacement)->value('public_id');
            $linkage = $linkage->link(AuditRelation::Supersedes, 'invoices', is_string($publicId) ? $publicId : '');
        }

        // A correction on a completed project: a material one returns it to ACTIVE (SF-REVAL; M-26).
        $this->revalidation->revalidate($context, $project->id, "FIN.InvoiceVoided:{$invoice->public_id}");
        $this->records->record($context, 'FIN.InvoiceVoided', $input->writeoffs === [] ? AuthorityClass::AdmPlus : AuthorityClass::OwnerOnly, $invoice->id, $input->reason, $input->businessDate,
            ['state' => 'VOIDED', 'basis' => $input->basis, 'dispositions' => self::named($input)], ['state' => 'ISSUED', 'sales_value' => $sales], $linkage);

        return ['invoice' => $invoice->id, 'versions' => $versions];
    }

    /**
     * Revises the invoice down: a new version of its record with its typed basis, the guard's gross lowered with the dispositions, never
     * below what stays applied (C-16); its layouts revised under their numbering rule.
     *
     * @return array{invoice: int, versions: list<int>} the invoice, and the layouts' versions the command issued, for the action to hand to the render job
     */
    public function revise(CommandContext $context, Invoice $invoice, int $lockVersion, InvoiceCorrection $input): array
    {
        $versions = [];
        [$project, $balance] = $this->admitted($context, $invoice, $lockVersion, $input);

        if ($input->content === null) {
            throw Refused::rejected('CM-23', ['reason' => 'no_content']);
        }

        self::reviseBasis($invoice, $input);
        self::ensureEvidence($invoice, $input);
        $now = $context->now();
        $previous = $invoice->current_version_id ?? 0;
        $client = DB::table('invoice_versions')->where('id', $previous)->value('client_snapshot');
        $highest = DB::table('invoice_versions')->where('invoice_id', $invoice->id)->max('version_no');
        $next = 1 + (is_numeric($highest) ? (int) $highest : 0);
        $version = DB::table('invoice_versions')->insertGetId([
            'invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'version_no' => $next, 'state' => 'DRAFT', 'kind' => $input->content['kind'], 'business_date' => $input->businessDate,
            'sales_value' => '0.00', 'output_tax_value' => '0.00', 'client_snapshot' => $client, 'revision_reason' => $input->reason, 'revision_basis' => $input->basis,
            'basis_correction_case_id' => $input->basis === 'RETURN' ? $input->caseId : null,
            'basis_confirmation_id' => $input->basis === 'RECONFIRMATION' ? $input->confirmationId : null,
            'basis_cancellation_id' => $input->basis === 'CANCELLATION' ? $input->cancellationId : null,
            'basis_project_transition_id' => $input->basis === 'CANCELLATION' ? $input->transitionId : null,
            'supersedes_version_id' => $previous,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);
        $this->records->writeContent($context, $project, $invoice->id, $version, $input->content, 1000);
        $values = DB::table('invoice_versions')->where('id', $version)->first(['sales_value', 'output_tax_value', 'gross_value']);
        $sales = is_object($values) ? Row::text($values, 'sales_value') : '0.00';
        $tax = is_object($values) ? Row::text($values, 'output_tax_value') : '0.00';
        $gross = is_object($values) ? Row::text($values, 'gross_value') : '0.00';

        if (Money::of($gross)->compareTo(Money::of(Row::text($balance, 'gross_value'))) >= 0 || ! Money::of($sales)->isPositive()) {
            throw Refused::rejected('CM-23', ['reason' => 'not_downward']);
        }

        DB::table('invoice_versions')->where('id', $previous)->update(['state' => 'SUPERSEDED', 'updated_at' => $now]);
        DB::table('invoice_versions')->where('id', $version)->update(['state' => 'ISSUED', 'updated_at' => $now]);
        $revised = DB::table('invoices')->where('id', $invoice->id)->where('state', 'ISSUED')->where('lock_version', $lockVersion)->update(['current_version_id' => $version, 'lock_version' => $lockVersion + 1, 'updated_at' => $now]);

        if ($revised !== 1) {
            throw Refused::conflict('ST-01');
        }

        $moved = $this->dispose($context, $invoice, $project, $input, null, $input->reason);
        $this->receivable->move($context, $invoice->id, [...$moved['old'],
            'gross_value' => Money::of($gross)->minus(Money::of(Row::text($balance, 'gross_value')))->toString(),
            'sales_value' => Money::of($sales)->minus(Money::of(Row::text($balance, 'sales_value')))->toString(),
            'output_tax_value' => Money::of($tax)->minus(Money::of(Row::text($balance, 'output_tax_value')))->toString(),
        ]);
        $this->demand->invoiced($context, $project->id, Money::of($sales)->minus(Money::of(Row::text($balance, 'sales_value')))->toString());

        foreach ($this->chain->ofInvoice($invoice->id, InvoiceSubjects::LAYOUTS) as $document) {
            $issued = $this->chain->issuedOf($document->id);

            if ($issued !== null) {
                $subject = DocumentSubject::of($document);
                $provider = $this->subjects->of($subject);

                if ($this->chain->draftOf($document->id) === null) {
                    $this->issuing->revisionDraft($context, $document, $issued);
                }

                $versions[] = $this->issuing->issue($context, $document, $subject, $provider, null, $input->businessDate, $input->prefilled, null, authorized: true, held: true)->id;
            }
        }

        $this->kuitansi->reconcile($context, $invoice->id, 'invoices.unlink.corrected');
        $this->revalidation->revalidate($context, $project->id, "FIN.InvoiceRevisedDown:{$invoice->public_id}");
        $this->records->record($context, 'FIN.InvoiceRevisedDown', $input->writeoffs === [] ? AuthorityClass::AdmPlus : AuthorityClass::OwnerOnly, $invoice->id, $input->reason, $input->businessDate,
            ['sales_value' => $sales, 'output_tax_value' => $tax, 'basis' => $input->basis, 'dispositions' => self::named($input)], ['sales_value' => Row::text($balance, 'sales_value'), 'output_tax_value' => Row::text($balance, 'output_tax_value')]);

        return ['invoice' => $invoice->id, 'versions' => $versions];
    }

    /**
     * The checks every correction takes under its locks: its date (ST-08; C-50), the invoice as the page read it, the payload naming exactly the current
     * reductions (ST-07), a standing write-off the Owner's (OD-07).
     *
     * @return array{0: ProjectRef, 1: object}
     */
    private function admitted(CommandContext $context, Invoice $invoice, int $lockVersion, InvoiceCorrection $input): array
    {
        BusinessDateRule::check($input->businessDate, $input->prefilled);
        $fresh = DB::table('invoices')->where('id', $invoice->id)->first(['state', 'lock_version', 'origin', 'project_id']);

        if ($fresh === null || Row::text($fresh, 'state') === 'VOIDED') {
            throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
        }

        if (Row::text($fresh, 'state') !== 'ISSUED' || Row::text($fresh, 'origin') !== 'LIVE' || $invoice->project_id === null) {
            throw Refused::rejected('CM-23', ['reason' => 'not_issued']);
        }

        if ((int) Row::text($fresh, 'lock_version') !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        $current = self::reductions($invoice->id);
        $same = fn (array $named, array $found): bool => array_diff(array_keys($named), array_keys($found)) === [] && array_diff(array_keys($found), array_keys($named)) === [];

        if (! $same($input->applications, $current['applications']) || ! $same($input->settlements, $current['settlements']) || ! $same($input->writeoffs, $current['writeoffs'])) {
            throw Refused::conflict('ST-07', ['applications' => array_map(fn (int $id): string => RowHandle::of('payment_applications', $id), array_keys($current['applications'])),
                'settlements' => array_map(fn (int $id): string => RowHandle::of('deduction_settlements', $id), array_keys($current['settlements'])),
                'writeoffs' => array_map(fn (int $id): string => RowHandle::of('receivable_writeoffs', $id), array_keys($current['writeoffs']))]);
        }

        if ($current['writeoffs'] !== [] && $context->authority()?->allows('writeoff.supersede', $invoice->company_id) !== true) {
            throw Refused::rejected('OD-07', ['reason' => 'writeoff_standing']);
        }

        $balance = InvoiceBalance::query()->toBase()->where('invoice_id', $invoice->id)->first();
        $publicId = DB::table('projects')->where('id', $invoice->project_id)->value('public_id');

        return [new ProjectRef($invoice->project_id, $invoice->company_id, is_string($publicId) ? $publicId : ''), $balance ?? throw Refused::rejected('CM-23', ['reason' => 'not_issued'])];
    }

    /** A void's basis: an unbilled entry error needs none; a billed void cites what ended the value, or names the replacement (CM-24; C-56). */
    private static function voidBasis(Invoice $invoice, InvoiceCorrection $input, bool $billed, bool $replacing): void
    {
        if ($input->basis === 'UNBILLED_ENTRY_ERROR' && $billed) {
            throw Refused::rejected('C-56', ['reason' => 'billed']);
        }

        if ($input->basis === 'ENTRY_ERROR_REPLACED' && ! $replacing) {
            throw Refused::rejected('C-17', ['reason' => 'no_replacement']);
        }

        if ($input->basis !== 'ENTRY_ERROR_REPLACED' && $replacing) {
            throw Refused::rejected('C-17', ['reason' => 'replacement_without_entry_error']);
        }

        self::basisRow($invoice, $input);
    }

    /** A revision's basis (CM-23; CM-24). */
    private static function reviseBasis(Invoice $invoice, InvoiceCorrection $input): void
    {
        if (! in_array($input->basis, ['ENTRY_ERROR', 'RETURN', 'RECONFIRMATION', 'CANCELLATION'], true)) {
            throw Refused::rejected('C-56', ['reason' => 'basis']);
        }

        self::basisRow($invoice, $input);
    }

    /** The row the basis cites: for a return, a return's case of the invoice's own project (CM-12; CM-24). */
    private static function basisRow(Invoice $invoice, InvoiceCorrection $input): void
    {
        $missing = match ($input->basis) {
            'RETURN' => $input->caseId === null || ! DB::table('correction_cases')->where('id', $input->caseId)->where('project_id', $invoice->project_id)->whereIn('case_kind', InvoiceCorrection::RETURN_CASES)->exists(),
            'RECONFIRMATION' => $input->confirmationId === null,
            'CANCELLATION' => ($input->cancellationId === null) === ($input->transitionId === null),
            default => false,
        };

        if ($missing) {
            throw Refused::rejected('C-56', ['reason' => 'basis_row']);
        }
    }

    /** DIR-092 (2): an entry error stands on evidence linked to the invoice's records. */
    private static function ensureEvidence(Invoice $invoice, InvoiceCorrection $input): void
    {
        if (in_array($input->basis, ['ENTRY_ERROR', 'ENTRY_ERROR_REPLACED'], true) && ! InvoiceEvidence::linked($invoice, $input->evidenceId)) {
            throw Refused::rejected('CM-24', ['reason' => 'evidence']);
        }
    }

    /**
     * The replacement's root act, inherited from the replaced invoice's current one with the same dates (L-30; C-17).
     *
     * @return array{0: int, 1: ?string}
     */
    private function inheritAct(CommandContext $context, int $replacement, int $replaced, object $balance): array
    {
        $source = (int) Row::text($balance, 'current_billing_act_id');
        $act = DB::table('invoice_billing_acts')->where('id', $source)->first(['billed_at', 'due_date']);
        $due = is_object($act) ? Row::nullable($act, 'due_date') : null;
        $id = DB::table('invoice_billing_acts')->insertGetId([
            'invoice_id' => $replacement, 'billed_at' => is_object($act) ? Row::text($act, 'billed_at') : null, 'due_date' => $due, 'kind' => 'INHERITED', 'inherited_from_act_id' => $source,
            'inherited_from_invoice_id' => $replaced, 'decided_by' => $context->request->actorUserId, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);

        return [$id, $due];
    }

    /**
     * Every reduction as the payload disposes it, the sources, money facts and case moved here; the invoices' own deltas returned, for
     * their one statement each.
     *
     * @return array{old: array<string, string>, new: array<string, string>}
     */
    private function dispose(CommandContext $context, Invoice $invoice, ProjectRef $project, InvoiceCorrection $input, ?int $replacement, string $reason): array
    {
        $current = self::reductions($invoice->id);
        $old = [];
        $new = [];
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $line = 0;

        foreach ($input->applications as $id => $action) {
            $row = $current['applications'][$id];
            $amount = Money::of($row['amount']);

            if ($action === InvoiceCorrection::KEEP) {
                if ($replacement !== null) {
                    throw Refused::rejected('C-16', ['reason' => 'undisposed']);
                }

                continue;
            }

            self::add($old, 'applied_amount', $amount->negated());

            if ($action === InvoiceCorrection::RETARGET) {
                if ($replacement === null) {
                    throw Refused::rejected('C-17', ['reason' => 'no_replacement']);
                }

                $line++;
                DB::table('payment_applications')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'SUPERSEDED', 'end_reason' => 'REALLOCATED', 'end_command_id' => $commandId]);
                DB::table('payment_applications')->insert(['company_id' => $invoice->company_id, 'client_organization_id' => $invoice->client_organization_id, 'payment_id' => $row['payment_id'], 'opening_customer_credit_id' => $row['credit_id'],
                    'invoice_id' => $replacement, 'target_kind' => 'INVOICE', 'amount' => $row['amount'], 'supersedes_application_id' => $id, 'business_date' => $input->businessDate, 'reason' => $reason,
                    'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $now, 'recorded_by' => $actor]);
                self::add($new, 'applied_amount', $amount);
            } else {
                DB::table('payment_applications')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'RELEASED', 'end_reason' => 'INVOICE_CORRECTED', 'end_command_id' => $commandId]);
                $this->moveSource($context, $row['payment_id'], $row['credit_id'], ['invoice_applied_amount' => $amount->negated()->toString()]);
            }
        }

        $residualCase = $input->basis === 'RETURN' ? $input->caseId : null;

        foreach ($input->settlements as $id => $action) {
            $row = $current['settlements'][$id];
            $amount = Money::of($row['amount']);
            $column = $row['class'] === 'FEE' ? 'fee_settled_amount' : 'tax_settled_amount';
            $collected = $row['class'] === 'TAX' && $row['direction'] === 'COLLECTED';

            if ($action === InvoiceCorrection::KEEP) {
                if ($replacement !== null) {
                    throw Refused::rejected('C-16', ['reason' => 'undisposed']);
                }

                continue;
            }

            self::add($old, $column, $amount->negated());

            if ($collected) {
                self::add($old, 'collected_tax_settled_amount', $amount->negated());
            }

            if ($action === InvoiceCorrection::RERECORD) {
                if ($replacement === null) {
                    throw Refused::rejected('C-17', ['reason' => 'no_replacement']);
                }

                $this->rerecordSettlement($context, $invoice->company_id, $id, $replacement, $reason, $input->businessDate, $collected, $new, ++$line);
                self::add($new, $column, $amount);

                if ($collected) {
                    self::add($new, 'collected_tax_settled_amount', $amount);
                }
            } else {
                $residualCase ??= $this->cases->open($context, AuthorityClass::AdmPlus, $invoice->company_id, 'INVOICE_CORRECTION', $reason, $project->id)->id;
                DB::table('deduction_settlements')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'RESIDUAL', 'correction_case_id' => $residualCase]);
                $this->moveSource($context, $row['payment_id'], null, ['settled_amount' => $amount->negated()->toString()]);
                $this->cases->post($context, $residualCase, ['residual_settlement_amount' => $amount->toString()], $reason);
            }
        }

        $rerecorded = Money::zero();

        foreach ($input->writeoffs as $id => $action) {
            $amount = Money::of($current['writeoffs'][$id]['amount']);
            self::add($old, 'written_off_amount', $amount->negated());
            DB::table('financial_contras')->insert(['company_id' => $invoice->company_id, 'receivable_writeoff_id' => $id, 'amount' => $amount->toString(), 'reason' => $reason, 'business_date' => $input->businessDate,
                'recorded_by' => $actor, 'command_id' => $commandId, 'command_line_no' => ++$line, 'recorded_at' => $now]);
            GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['receivable_writeoff_id' => $id], 'deltas' => ['contra_amount' => $amount->toString()]]]);

            if ($action === InvoiceCorrection::RERECORD) {
                $rerecorded = $rerecorded->plus($amount);
            }
        }

        // What is re-recorded is one new write-off of the sum: one per invoice and command (DATABASE §4.12).
        if ($rerecorded->isPositive()) {
            $target = $replacement ?? $invoice->id;
            $written = DB::table('receivable_writeoffs')->insertGetId(['company_id' => $invoice->company_id, 'invoice_id' => $target, 'amount' => $rerecorded->toString(), 'reason' => $reason,
                'outstanding_before' => $rerecorded->toString(), 'outstanding_after' => '0.00', 'decided_by' => $actor, 'business_date' => $input->businessDate, 'command_id' => $commandId, 'command_line_no' => ++$line, 'recorded_at' => $now, 'recorded_by' => $actor]);
            GuardUpdate::bear($context->connection, 'money_fact_balances', ['receivable_writeoff_id' => $written], ['company_id' => $invoice->company_id, 'fact_kind' => 'WRITEOFF', 'amount' => $rerecorded->toString(), 'recorded_at' => $now]);

            if ($target === $invoice->id) {
                self::add($old, 'written_off_amount', $rerecorded);
            } else {
                self::add($new, 'written_off_amount', $rerecorded);
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * ACTIVE → CONTRAED with a new ACTIVE row on the replacement citing the same evidence (DATABASE §11.4): the payment and the evidence
     * net of the move, a fee's expense moving once; collected tax at most the replacement's tax component, else it stays a residual.
     *
     * @param  array<string, string>  $new
     */
    private function rerecordSettlement(CommandContext $context, int $companyId, int $id, int $replacement, string $reason, string $businessDate, bool $collected, array $new, int $line): void
    {
        $row = DB::table('deduction_settlements')->where('id', $id)->first();

        if ($row === null) {
            throw Refused::rejected('C-16', ['reason' => 'undisposed']);
        }

        $amount = Row::text($row, 'amount');

        if ($collected) {
            $carried = Money::of($new['collected_tax_settled_amount'] ?? '0')->plus(Money::of($amount));
            $tax = InvoiceBalance::query()->toBase()->where('invoice_id', $replacement)->value('output_tax_value');

            if ($carried->compareTo(Money::of(is_string($tax) ? $tax : '0')) > 0) {
                throw Refused::rejected('C-22', ['reason' => 'collected_above_tax', 'amount' => $amount]);
            }
        }

        $now = $context->now();
        $commandId = $context->request->commandId;
        DB::table('deduction_settlements')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'CONTRAED']);
        DB::table('financial_contras')->insert(['company_id' => $companyId, 'deduction_settlement_id' => $id, 'amount' => $amount, 'reason' => $reason, 'business_date' => $businessDate,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $now]);
        GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['deduction_settlement_id' => $id], 'deltas' => ['contra_amount' => $amount]]]);
        $moved = DB::table('deduction_settlements')->insertGetId(['company_id' => $companyId, 'client_organization_id' => (int) Row::text($row, 'client_organization_id'), 'invoice_id' => $replacement, 'payment_id' => (int) Row::text($row, 'payment_id'),
            'settlement_class' => Row::text($row, 'settlement_class'), 'tax_type_id' => self::id(Row::nullable($row, 'tax_type_id')), 'fee_type' => Row::nullable($row, 'fee_type'), 'tax_direction' => Row::nullable($row, 'tax_direction'),
            'specific_type_key' => Row::text($row, 'specific_type_key'), 'treatment_rule_id' => self::id(Row::nullable($row, 'treatment_rule_id')), 'amount' => $amount, 'evidence_document_id' => (int) Row::text($row, 'evidence_document_id'), 'evidence_type' => Row::text($row, 'evidence_type'),
            'business_date' => $businessDate, 'state' => 'ACTIVE', 'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['deduction_settlement_id' => $moved], ['company_id' => $companyId, 'fact_kind' => 'SETTLEMENT', 'amount' => $amount, 'recorded_at' => $now]);
    }

    /**
     * @param  array<string, string>  $deltas
     *
     * @param-out array<string, string>  $deltas
     */
    private static function add(array &$deltas, string $column, Money $amount): void
    {
        $deltas[$column] = Money::of($deltas[$column] ?? '0')->plus($amount)->toString();
    }

    private static function id(?string $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /** @param  array<string, string>  $deltas */
    private function moveSource(CommandContext $context, ?int $paymentId, ?int $creditId, array $deltas): void
    {
        GuardUpdate::apply($context->connection, 'application_source_balances', [['key' => $paymentId !== null ? ['payment_id' => $paymentId] : ['opening_customer_credit_id' => (int) $creditId], 'deltas' => $deltas]]);
    }

    /** The layout of a replacement issued in the correcting command (FU-12), its number drawn last (LK-31; NM-03; C-10). */
    private function issueLayout(CommandContext $context, int $invoiceId, string $layout, string $businessDate, bool $prefilled): int
    {
        $company = DB::table('invoices')->where('id', $invoiceId)->value('company_id');
        $subject = new DocumentSubject($layout, 'STANDARD', is_numeric($company) ? (int) $company : 0, ['invoice_id' => $invoiceId]);
        $provider = $this->subjects->of($subject);
        $document = $this->issuing->openDraft($context, $subject, null);

        return $this->issuing->issue($context, $document, $subject, $provider, null, $businessDate, $prefilled, null, authorized: true, held: true, correcting: true)->id;
    }

    /** @return array<string, array<string, string>> */
    private static function named(InvoiceCorrection $input): array
    {
        return ['applications' => self::prefixed($input->applications, 'application'), 'settlements' => self::prefixed($input->settlements, 'settlement'), 'writeoffs' => self::prefixed($input->writeoffs, 'writeoff')];
    }

    /**
     * @param  array<int, string>  $rows
     * @return array<string, string>
     */
    private static function prefixed(array $rows, string $prefix): array
    {
        $named = [];

        foreach ($rows as $id => $action) {
            $named[$prefix.'_'.$id] = $action;
        }

        return $named;
    }
}
