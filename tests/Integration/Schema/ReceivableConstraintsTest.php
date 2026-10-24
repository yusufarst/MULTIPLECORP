<?php

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceBillingAct;
use App\Modules\FIN\Models\InvoiceTaxComponent;
use App\Modules\FIN\Models\InvoiceVersion;
use App\Modules\FIN\Models\InvoiceVersionLine;
use App\Modules\FIN\Models\ReceivableDisputeHold;
use App\Modules\FIN\Models\ReceivableWriteoff;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\LineScopeCancellation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Modules\PTY\Models\ClientOrganization;
use Database\Factories\OPS\ImportRowFactory;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-009), criterion 2 for DATABASE §4.12's invoice and receivable tables and §11.2: an invoice is its
// project's client's, an opening receivable once per import row (C-32; C-47); it moves forward only and a
// void states exactly its typed basis, an entry-error void committing only with the replacement whose root
// act inherits the replaced invoice's current act (C-17; C-41; C-56; L-30); versions form one chain, a
// downward revision typed and an issued version immutable (C-56; BR-CR-06); lines and tax change only while
// their version is a DRAFT (§19; §28); the receivable guard keeps its reductions within its gross (C-15; C-16;
// C-22); one open dispute per invoice (C-42); a write-off states its outstanding (AX-24).

uses(RefreshDatabase::class);

/** Issues the invoice, as its first version's issue does (§11.2). */
function issueInvoice(Invoice $invoice): void
{
    DB::table('invoices')->where('id', $invoice->id)->update(['state' => 'ISSUED']);
}

/**
 * Voids the invoice with its basis, as the void command does (§11.2).
 *
 * @param  array<string, int>  $reference
 */
function voidInvoice(Invoice $invoice, string $basis, array $reference = []): void
{
    DB::table('invoices')->where('id', $invoice->id)->update([
        'state' => 'VOIDED', 'voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Faktur dibatalkan', 'void_basis' => $basis, ...$reference,
    ]);
}

it('invoices a project\'s own client, an opening receivable once per import row of its company (C-32; C-47; BR-FIN-14)', function () {
    $invoice = Invoice::factory()->create();
    $opening = fn (array $attributes = []): Invoice => Invoice::factory()->create([
        'company_id' => $invoice->company_id, 'origin' => 'OPENING', 'project_id' => null, 'aging_basis' => 'MIGRASI',
        'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $invoice->company_id])->id, ...$attributes,
    ]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'invoices_c32_project_client_foreign', fn () => Invoice::factory()->create([
        'company_id' => $invoice->company_id, 'project_id' => $invoice->project_id, 'client_organization_id' => ClientOrganization::factory()->create()->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'invoices_project_unless_opening', fn () => Invoice::factory()->create(['project_id' => null]));
    Refusal::assert(Refusal::CHECK, 'invoices_c47_opening_import_row', fn () => $opening(['import_row_id' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoices_c47_import_row_foreign', fn () => $opening(['import_row_id' => ImportRowFactory::new()->createOne(['company_id' => Company::factory()->create()->id])->id]));
    Refusal::assert(Refusal::UNIQUE, 'invoices_import_row_id_unique', fn () => $opening(['import_row_id' => $opening()->import_row_id]));
    // An opening receivable has no project whose confirmation, cancellation or transition a void could cite.
    Refusal::assert(Refusal::CHECK, 'invoices_c56_project_basis', function () use ($opening): void {
        voidInvoice($opening(), 'RECONFIRMATION', ['void_confirmation_id' => 987_654]);
    });

    expect($opening()->aging_basis)->toBe('MIGRASI');
});

it('voids an invoice forward only, with exactly the typed basis of its company or project (C-56; CM-23, CM-24; L-26)', function () {
    $invoice = Invoice::factory()->create();
    issueInvoice($invoice);
    $own = CorrectionCase::factory()->create(['company_id' => $invoice->company_id])->id;
    $row = fn () => DB::table('invoices')->where('id', $invoice->id);

    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis_reference', fn () => voidInvoice($invoice, 'RETURN'));
    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis_reference', fn () => voidInvoice($invoice, 'RETURN', [
        'void_correction_case_id' => $own, 'void_cancellation_id' => LineScopeCancellation::factory()->create(['project_line_id' => ProjectLineFactory::newOn($invoice->project_id)])->id,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoices_c56_void_case_foreign', fn () => voidInvoice($invoice, 'RETURN', ['void_correction_case_id' => CorrectionCase::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoices_c56_void_confirmation_foreign', fn () => voidInvoice($invoice, 'RECONFIRMATION', [
        'void_confirmation_id' => CommercialConfirmation::factory()->create(['project_id' => Project::factory()->create(['company_id' => $invoice->company_id])->id])->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis_exists', fn () => voidInvoice($invoice, 'CANCELLATION', [
        'void_project_transition_id' => ProjectStateTransition::factory()->create(['project_id' => $invoice->project_id])->id,
    ]));
    InvoiceBillingAct::factory()->create(['invoice_id' => $invoice->id]);
    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis_exists', fn () => voidInvoice($invoice, 'UNBILLED_ENTRY_ERROR'));
    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis', fn () => $row()->update([
        'state' => 'VOIDED', 'voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Tanpa dasar',
    ]));
    Refusal::assert(Refusal::CHECK, 'invoices_forward_only', fn () => $row()->update(['state' => 'DRAFT']));
    Refusal::assert(Refusal::CHECK, 'invoices_forward_only', fn () => Invoice::factory()->create([
        'state' => 'VOIDED', 'voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Dibatalkan sejak awal', 'void_basis' => 'ENTRY_ERROR_REPLACED',
    ]));
    // A cancellation of another project of the company.
    Refusal::assert(Refusal::CHECK, 'invoices_c56_void_basis_exists', fn () => voidInvoice($invoice, 'CANCELLATION', [
        'void_cancellation_id' => LineScopeCancellation::factory()->create(['project_line_id' => ProjectLineFactory::newOn(Project::factory()->create(['company_id' => $invoice->company_id])->id)])->id,
    ]));

    voidInvoice($invoice, 'RETURN', ['void_correction_case_id' => $own]);

    Refusal::assert(Refusal::CHECK, 'invoices_forward_only', fn () => $row()->update(['voided_reason' => 'Alasan lain']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table invoices', fn () => $row()->update(['client_organization_id' => ClientOrganization::factory()->create()->id]));

    $cancelled = Invoice::factory()->create();
    issueInvoice($cancelled);
    voidInvoice($cancelled, 'CANCELLATION', ['void_project_transition_id' => ProjectStateTransition::factory()->create([
        'project_id' => $cancelled->project_id, 'from_state' => 'ACTIVE', 'to_state' => 'CANCELLED', 'transition_kind' => 'CANCEL', 'reason' => 'Klien membatalkan proyek',
    ])->id]);

    expect($row()->value('void_basis'))->toBe('RETURN')
        ->and(Invoice::query()->whereKey($cancelled->id)->value('state'))->toBe('VOIDED');
});

it('commits an entry-error void only with its replacement, whose root act inherits the replaced invoice\'s current act (C-17; C-41; C-56; L-30)', function () {
    $replaced = Invoice::factory()->create();
    issueInvoice($replaced);
    $root = InvoiceBillingAct::factory()->create(['invoice_id' => $replaced->id]);
    $current = InvoiceBillingAct::factory()->create(['invoice_id' => $replaced->id, 'kind' => 'CORRECTION', 'supersedes_act_id' => $root->id, 'due_date' => '2026-11-20', 'reason' => 'Jatuh tempo salah ketik']);
    $replacement = fn (): Invoice => Invoice::factory()->create([
        'company_id' => $replaced->company_id, 'client_organization_id' => $replaced->client_organization_id, 'project_id' => $replaced->project_id, 'replaces_invoice_id' => $replaced->id,
    ]);
    $inherit = fn (Invoice $by, InvoiceBillingAct $act, array $attributes = []): InvoiceBillingAct => InvoiceBillingAct::factory()->create([
        'invoice_id' => $by->id, 'kind' => 'INHERITED', 'inherited_from_act_id' => $act->id, 'inherited_from_invoice_id' => $replaced->id,
        'billed_at' => $act->billed_at, 'due_date' => $act->due_date, ...$attributes,
    ]);
    $atCommit = fn () => DB::statement('set constraints invoices_c56_replaced_with_replacement, invoices_c17_inherits_current_act, invoice_billing_acts_c17_inherits_current_act immediate');

    Refusal::assert(Refusal::CHECK, 'invoices_c56_replaced_with_replacement', function () use ($replaced, $atCommit): void {
        voidInvoice($replaced, 'ENTRY_ERROR_REPLACED');
        $atCommit();
    });
    // A replacement with no root act, with a fresh one, or inheriting a superseded act.
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c17_inherits_current_act', function () use ($replacement, $atCommit): void {
        $replacement();
        $atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c17_inherits_current_act', function () use ($replacement, $atCommit): void {
        InvoiceBillingAct::factory()->create(['invoice_id' => $replacement()->id]);
        $atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c17_inherits_current_act', function () use ($replacement, $inherit, $root, $atCommit): void {
        $inherit($replacement(), $root);
        $atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c41_dates', fn () => $inherit($replacement(), $current, ['due_date' => '2026-12-31']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_billing_acts_c17_replaced_invoice_foreign', fn () => $inherit(Invoice::factory()->create(), $current));

    $by = $replacement();
    $inherit($by, $current);
    voidInvoice($replaced, 'ENTRY_ERROR_REPLACED');
    $atCommit();

    // Its void final, the replaced invoice takes no later act that would leave the inherited one superseded.
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c41_not_on_void', fn () => InvoiceBillingAct::factory()->create([
        'invoice_id' => $replaced->id, 'kind' => 'CORRECTION', 'supersedes_act_id' => $current->id, 'reason' => 'Koreksi sesudah batal',
    ]));

    expect(Invoice::query()->whereKey($replaced->id)->value('void_basis'))->toBe('ENTRY_ERROR_REPLACED')
        ->and(InvoiceBillingAct::query()->where('invoice_id', $by->id)->sole()->inherited_from_act_id)->toBe($current->id);
});

it('bills an invoice once by a root act and a linear correction chain, an opening act undated only when aged MIGRASI (C-41; CM-25; BR-FIN-14)', function () {
    $root = InvoiceBillingAct::factory()->create();
    $correction = fn (array $attributes = []): InvoiceBillingAct => InvoiceBillingAct::factory()->create([
        'invoice_id' => $root->invoice_id, 'kind' => 'CORRECTION', 'supersedes_act_id' => $root->id, 'reason' => 'Tanggal tagih salah ketik', ...$attributes,
    ]);
    $company = Company::factory()->create()->id;
    $opening = fn (string $aging): Invoice => Invoice::factory()->create([
        'company_id' => $company, 'origin' => 'OPENING', 'project_id' => null, 'aging_basis' => $aging, 'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $company])->id,
    ]);
    $openingAct = fn (Invoice $invoice): InvoiceBillingAct => InvoiceBillingAct::factory()->create([
        'invoice_id' => $invoice->id, 'kind' => 'OPENING', 'due_date' => null, 'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $company])->id,
    ]);

    Refusal::assert(Refusal::UNIQUE, 'invoice_billing_acts_c41_one_root', fn () => InvoiceBillingAct::factory()->create(['invoice_id' => $root->invoice_id]));
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c41_correction_supersedes', fn () => $correction(['supersedes_act_id' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_billing_acts_c41_supersedes_foreign', fn () => $correction(['supersedes_act_id' => InvoiceBillingAct::factory()->create()->id]));
    // An act without a due date: refused on an invoice aged by its due date, and on one aged MIGRASI unless it is the OPENING act.
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c41_dates', fn () => $correction(['due_date' => null]));

    $correction();

    Refusal::assert(Refusal::UNIQUE, 'invoice_billing_acts_supersedes_act_id_unique', fn () => $correction());
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c41_dates', fn () => $openingAct($opening('DUE_DATE')));
    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_c47_opening_import_row', fn () => InvoiceBillingAct::factory()->create(['invoice_id' => $opening('MIGRASI')->id, 'kind' => 'OPENING']));

    Refusal::assert(Refusal::CHECK, 'invoice_billing_acts_due_date', fn () => InvoiceBillingAct::factory()->create(['invoice_id' => $opening('MIGRASI')->id, 'due_date' => null]));

    expect($openingAct($opening('MIGRASI'))->due_date)->toBeNull();
});

it('revises an invoice by versions in one chain, a downward revision stating its typed basis of the invoice\'s project, an issued version immutable (C-56; BR-CR-06; CM-24)', function () {
    $first = InvoiceVersion::factory()->create();
    DB::table('invoice_versions')->where('id', $first->id)->update(['state' => 'ISSUED']);
    $revise = fn (array $attributes): InvoiceVersion => InvoiceVersion::factory()->create([
        'invoice_id' => $first->invoice_id, 'version_no' => 2, 'supersedes_version_id' => $first->id, 'revision_basis' => 'UPWARD', ...$attributes,
    ]);
    $issued = fn () => DB::table('invoice_versions')->where('id', $first->id);

    Refusal::assert(Refusal::CHECK, 'invoice_versions_c56_downward_basis', fn () => $revise(['sales_value' => '900000.00']));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_initial_first', fn () => $revise(['revision_basis' => 'INITIAL']));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_c56_basis_reference', fn () => $revise(['revision_basis' => 'RETURN', 'sales_value' => '900000.00']));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_c56_downward_basis', fn () => $revise([
        'revision_basis' => 'RECONFIRMATION', 'sales_value' => '900000.00',
        'basis_confirmation_id' => CommercialConfirmation::factory()->create(['project_id' => Project::factory()->create(['company_id' => $first->company_id])->id])->id,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_versions_c56_case_foreign', fn () => $revise(['revision_basis' => 'RETURN', 'basis_correction_case_id' => CorrectionCase::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_versions_linear_chain_foreign', fn () => $revise(['supersedes_version_id' => InvoiceVersion::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_immutable_once_issued', fn () => $issued()->update(['sales_value' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_immutable_once_issued', fn () => $issued()->update(['state' => 'DRAFT']));
    Refusal::assert(Refusal::CHECK, 'invoice_versions_draft_delete_only', fn () => $issued()->delete());
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoices_current_version_foreign', fn () => DB::table('invoices')->where('id', $first->invoice_id)->update(['current_version_id' => InvoiceVersion::factory()->create()->id]));

    $second = $revise(['revision_basis' => 'ENTRY_ERROR', 'sales_value' => '900000.00']);

    Refusal::assert(Refusal::UNIQUE, 'invoice_versions_supersedes_version_id_unique', fn () => $revise(['version_no' => 3]));

    // Superseded through the model, its stale-edit columns moving with it.
    InvoiceVersion::query()->whereKey($first->id)->firstOrFail()->forceFill(['state' => 'SUPERSEDED', 'lock_version' => 1])->save();
    DB::table('invoices')->where('id', $first->invoice_id)->update(['current_version_id' => $second->id]);

    expect(Invoice::query()->whereKey($first->invoice_id)->firstOrFail()->currentVersion?->gross_value)->toBe('1010000.00');
});

it('edits a version\'s lines and tax only while it is a DRAFT, deleted with it, a value-only line without quantity (§19; §28; C-09; C-32)', function () {
    $line = InvoiceVersionLine::factory()->create();
    $tax = InvoiceTaxComponent::factory()->create(['invoice_version_id' => $line->invoice_version_id]);
    $valueOnly = [
        'invoice_version_id' => $line->invoice_version_id, 'company_id' => $line->company_id, 'project_line_id' => null, 'product_id' => null, 'quantity_scale' => null,
        'entered_qty' => null, 'entered_unit_id' => null, 'ratio_to_base' => null, 'qty_base' => null, 'unit_price' => null, 'description' => 'Uang muka 30%',
        'line_sales_value' => '300000.00',
    ];

    Refusal::assert(Refusal::CHECK, 'invoice_version_lines_quantity_all_or_none', fn () => InvoiceVersionLine::factory()->create([...$valueOnly, 'entered_qty' => '1.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_version_lines_c09_line_foreign', fn () => InvoiceVersionLine::factory()->create([
        'invoice_version_id' => $line->invoice_version_id, 'project_line_id' => ProjectLineFactory::newOn(Project::factory()->create()->id),
    ]));

    $valueLine = InvoiceVersionLine::factory()->create($valueOnly);
    DB::table('invoice_version_lines')->where('id', $line->id)->update(['unit_price' => '90000.00', 'line_sales_value' => '900000.00']);
    DB::table('invoice_versions')->where('id', $line->invoice_version_id)->update(['state' => 'ISSUED']);

    Refusal::assert(Refusal::CHECK, 'invoice_version_lines_frozen_once_issued', fn () => DB::table('invoice_version_lines')->where('id', $line->id)->update(['unit_price' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'invoice_version_lines_frozen_once_issued', fn () => InvoiceVersionLine::factory()->create($valueOnly));
    Refusal::assert(Refusal::CHECK, 'invoice_tax_components_frozen_once_issued', fn () => DB::table('invoice_tax_components')->where('id', $tax->id)->delete());

    $draft = InvoiceTaxComponent::factory()->create();
    InvoiceVersionLine::factory()->create(['invoice_version_id' => $draft->invoice_version_id]);
    DB::table('invoice_versions')->where('id', $draft->invoice_version_id)->delete();

    expect(DB::table('invoice_tax_components')->where('invoice_version_id', $draft->invoice_version_id)->exists())->toBeFalse()
        ->and(DB::table('invoice_version_lines')->where('invoice_version_id', $draft->invoice_version_id)->exists())->toBeFalse()
        ->and($valueLine->qty_base)->toBeNull();
});

it('keeps a receivable\'s reductions within its gross and collected tax within its output tax, its act its own (C-15; C-16; C-22; C-41)', function () {
    $act = InvoiceBillingAct::factory()->create();
    $invoice = Invoice::query()->whereKey($act->invoice_id)->firstOrFail();
    DB::table('invoice_balances')->insert(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'gross_value' => 1110000, 'sales_value' => 1000000, 'output_tax_value' => 110000]);
    $row = fn () => DB::table('invoice_balances')->where('invoice_id', $invoice->id);

    Refusal::assert(Refusal::CHECK, 'invoice_balances_c15_conservation', fn () => $row()->update(['applied_amount' => 1000000, 'written_off_amount' => 110001]));
    Refusal::assert(Refusal::CHECK, 'invoice_balances_c15_amounts', fn () => $row()->update(['applied_amount' => -1]));
    Refusal::assert(Refusal::CHECK, 'invoice_balances_c22_collected_tax', fn () => $row()->update(['tax_settled_amount' => 110001, 'collected_tax_settled_amount' => 110001]));

    $row()->update(['applied_amount' => 500000]);

    // A void lowers the gross to 0 only with its reductions disposed in the same transaction (C-16).
    Refusal::assert(Refusal::CHECK, 'invoice_balances_c15_conservation', fn () => $row()->update(['gross_value' => 0, 'sales_value' => 0, 'output_tax_value' => 0]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'invoice_balances_c41_current_act_foreign', fn () => $row()->update(['current_billing_act_id' => InvoiceBillingAct::factory()->create()->id, 'current_due_date' => '2026-11-05']));
    Refusal::assert(Refusal::CHECK, 'invoice_balances_due_with_act', fn () => $row()->update(['current_due_date' => '2026-11-05']));

    $row()->update(['current_billing_act_id' => $act->id, 'current_due_date' => '2026-11-05']);

    expect($row()->value('outstanding'))->toBe('610000.00');
});

it('holds one open dispute per invoice, cleared once with reason (C-42; BR-FIN-08)', function () {
    $hold = ReceivableDisputeHold::factory()->create();
    $row = fn () => DB::table('receivable_dispute_holds')->where('id', $hold->id);
    $clear = ['cleared_by' => User::factory()->create()->id, 'cleared_at' => now(), 'clear_reason' => 'Klien menerima jumlah tagihan'];

    Refusal::assert(Refusal::UNIQUE, 'receivable_dispute_holds_c42_one_open', fn () => ReceivableDisputeHold::factory()->create(['invoice_id' => $hold->invoice_id]));
    Refusal::assert(Refusal::CHECK, 'receivable_dispute_holds_clear_columns', fn () => $row()->update(['cleared_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table receivable_dispute_holds', fn () => $row()->update(['reason' => 'Alasan lain']));

    $row()->update($clear);

    Refusal::assert(Refusal::CHECK, 'receivable_dispute_holds_cleared_once', fn () => $row()->update([...$clear, 'clear_reason' => 'Dibuka lagi']));

    expect(ReceivableDisputeHold::factory()->create(['invoice_id' => $hold->invoice_id])->exists)->toBeTrue();
});

it('writes off within the outstanding it states, once per invoice per command, of its company (BR-FIN-08; AX-24; C-32)', function () {
    $command = (string) Str::uuid7();
    $writeoff = ReceivableWriteoff::factory()->create(['command_id' => $command, 'command_line_no' => 1]);

    Refusal::assert(Refusal::CHECK, 'receivable_writeoffs_amounts', fn () => ReceivableWriteoff::factory()->create(['outstanding_after' => '1000000.00']));
    Refusal::assert(Refusal::CHECK, 'receivable_writeoffs_amounts', fn () => ReceivableWriteoff::factory()->create(['amount' => '2000000.00', 'outstanding_after' => '-890000.00']));
    Refusal::assert(Refusal::UNIQUE, 'receivable_writeoffs_one_per_invoice_per_command', fn () => ReceivableWriteoff::factory()->create(['invoice_id' => $writeoff->invoice_id, 'command_id' => $command, 'command_line_no' => 2]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'receivable_writeoffs_c32_invoice_foreign', fn () => ReceivableWriteoff::factory()->create(['company_id' => Company::factory()->create()->id, 'invoice_id' => $writeoff->invoice_id]));

    expect(ReceivableWriteoff::factory()->create(['invoice_id' => $writeoff->invoice_id])->outstanding_after)->toBe('1010000.00');
});

it('holds an invoice line\'s demand line within its invoice\'s project, and replaces a billed MIGRASI receivable, its inherited act undated as the act it copies (DIR-092 (1), (4); L-30)', function () {
    $line = InvoiceVersionLine::factory()->create();

    Refusal::assert(Refusal::CHECK, 'invoice_version_lines_within_project', fn () => InvoiceVersionLine::factory()->create([
        'invoice_version_id' => $line->invoice_version_id, 'project_line_id' => ProjectLineFactory::newOn(Project::factory()->create(['company_id' => $line->company_id])->id),
    ]));

    $company = Company::factory()->create()->id;
    $opening = fn (array $attributes = []): Invoice => Invoice::factory()->create([
        'company_id' => $company, 'origin' => 'OPENING', 'project_id' => null, 'aging_basis' => 'MIGRASI', 'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $company])->id, ...$attributes,
    ]);
    $replaced = $opening();
    issueInvoice($replaced);
    $act = InvoiceBillingAct::factory()->create(['invoice_id' => $replaced->id, 'kind' => 'OPENING', 'due_date' => null, 'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $company])->id]);
    $replacement = $opening(['client_organization_id' => $replaced->client_organization_id, 'replaces_invoice_id' => $replaced->id]);
    InvoiceBillingAct::factory()->create([
        'invoice_id' => $replacement->id, 'kind' => 'INHERITED', 'inherited_from_act_id' => $act->id, 'inherited_from_invoice_id' => $replaced->id, 'billed_at' => $act->billed_at, 'due_date' => null,
    ]);
    voidInvoice($replaced, 'ENTRY_ERROR_REPLACED');
    DB::statement('set constraints invoices_c56_replaced_with_replacement, invoices_c17_inherits_current_act, invoice_billing_acts_c17_inherits_current_act immediate');

    expect(InvoiceBillingAct::query()->where('invoice_id', $replacement->id)->sole()->due_date)->toBeNull();
});
