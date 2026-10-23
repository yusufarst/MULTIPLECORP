<?php

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\ConfirmationLinePin;
use App\Modules\PRJ\Models\LineScopeCancellation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PRJ\Models\ProjectSiplahDetail;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Modules\PRJ\Models\QuotationRevision;
use App\Modules\PRJ\Models\QuotationRevisionLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-007), criteria 1, 2 and 4: the PRJ rows of DATABASE §18 on projects and their commercial
// chain (§4.5, §9) — a project number once in its company (C-10), every reference within the company
// (C-32), exact conversions at the product's scale (C-09), the per-line guard by mode (C-05, C-30), the
// invoice cap (C-31), Force Complete's evidence (C-38), one root confirmation per revision and linear
// supersession (C-49, C-59), one effect per command (C-48), one fact per import row (C-47), the SIPLAH lock
// (C-51) and deletes of drafts only (§13, §19).

uses(RefreshDatabase::class);

/** A line's guard row, written as DemandPosting would (§3.1). @param  array<string, mixed>  $values */
function lineGuard(ProjectLine $line, array $values = []): void
{
    DB::table('project_line_balances')->insert(['project_line_id' => $line->id, 'fulfillment_mode' => $line->fulfillment_mode, ...$values]);
}

it('numbers a project once in its company and never renumbers it (C-10; NM-08)', function () {
    $project = Project::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'projects_c10_number', fn () => Project::factory()->create(['company_id' => $project->company_id, 'project_number' => $project->project_number]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table projects', fn () => DB::table('projects')->where('id', $project->id)->update(['project_number' => 'CTH/PRJ/2026/9999']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table projects', fn () => DB::table('projects')->where('id', $project->id)->update(['company_id' => Company::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'projects_period_key_format', fn () => Project::factory()->create(['period_key' => '26']));

    // Another company has its own series.
    expect(Project::factory()->create(['project_number' => $project->project_number])->exists)->toBeTrue();
});

it('refuses an unknown code and a cross-company link without its reason (§4.5; §15)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => Project::factory()->create($attributes));
})->with([
    'an unknown channel' => [['channel' => 'MARKETPLACE'], 'projects_channel_code'],
    'an unknown state' => [['state' => 'ON_HOLD'], 'projects_state_code'],
    'an unknown origin' => [['origin' => 'COPY'], 'projects_origin_code'],
    'a link without its reason' => [['linked_project_id' => null, 'link_reason' => 'Proyek induk'], 'projects_linked_project_reason'],
]);

it('links a project of another company only with its reason, and an import row to one project (§15; C-47)', function () {
    $other = Project::factory()->create();
    $row = ImportRow::factory()->create();
    $linked = Project::factory()->create(['linked_project_id' => $other->id, 'link_reason' => 'Pengadaan bersama sekolah yang sama', 'origin' => 'OPENING', 'import_row_id' => $row->id]);

    Refusal::assert(Refusal::CHECK, 'projects_linked_project_reason', fn () => DB::table('projects')->where('id', $linked->id)->update(['link_reason' => null]));
    Refusal::assert(Refusal::UNIQUE, 'projects_import_row_id_unique', fn () => Project::factory()->create(['origin' => 'OPENING', 'import_row_id' => $row->id]));

    expect($linked->linkedProject?->is($other))->toBeTrue();
});

it('keeps every reference of the commercial chain within the project\'s company (C-32)', function () {
    $project = Project::factory()->create();
    $other = Company::factory()->create()->id;

    Refusal::assert(Refusal::FOREIGN_KEY, 'project_lines_c32_project_foreign', fn () => ProjectLine::factory()->create(['project_id' => $project->id, 'company_id' => $other]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'quotation_revisions_c32_project_foreign', fn () => QuotationRevision::factory()->create(['project_id' => $project->id, 'company_id' => $other]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'commercial_confirmations_c32_project_foreign', fn () => CommercialConfirmation::factory()->create(['project_id' => $project->id, 'company_id' => $other]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'project_state_transitions_c32_project_foreign', fn () => ProjectStateTransition::factory()->create(['project_id' => $project->id, 'company_id' => $other]));

    $elsewhere = ProjectLine::factory()->create();
    $confirmation = CommercialConfirmation::factory()->create(['project_id' => $project->id]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'confirmation_line_pins_c09_line_foreign', fn () => ConfirmationLinePin::factory()->create(['commercial_confirmation_id' => $confirmation->id, 'project_line_id' => $elsewhere->id, 'company_id' => $project->company_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'quotation_revision_lines_c09_line_foreign', fn () => QuotationRevisionLine::factory()->create(['quotation_revision_id' => QuotationRevision::factory()->create(['project_id' => $project->id])->id, 'project_line_id' => $elsewhere->id, 'company_id' => $project->company_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'commercial_confirmations_revision_foreign', fn () => CommercialConfirmation::factory()->create(['project_id' => $project->id, 'kind' => 'QUOTATION_APPROVAL', 'quotation_revision_id' => QuotationRevision::factory()->create()->id]));
});

it('converts every quantity exactly and holds it to its product\'s scale (C-09; §7)', function (Closure $write, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, $write);
})->with([
    'a line not its ratio\'s multiple' => [fn () => ProjectLine::factory()->create(['entered_qty' => '2.000', 'ratio_to_base' => 12, 'qty_base' => '20.000']), 'project_lines_c09_conversion'],
    'a line at a ratio below one' => [fn () => ProjectLine::factory()->create(['ratio_to_base' => 0, 'qty_base' => '0.000']), 'project_lines_c09_conversion'],
    'a fraction of a counted product' => [fn () => ProjectLine::factory()->create(['entered_qty' => '1.500', 'qty_base' => '1.500']), 'project_lines_c09_scale'],
    'a pin not its ratio\'s multiple' => [fn () => ConfirmationLinePin::factory()->create(['entered_qty' => '1.000', 'ratio_to_base' => 12, 'qty_base' => '10.000']), 'confirmation_line_pins_c09_conversion'],
    'a pinned fraction of a counted product' => [fn () => ConfirmationLinePin::factory()->create(['entered_qty' => '0.500', 'qty_base' => '0.500']), 'confirmation_line_pins_c09_scale'],
    'a quoted fraction of a counted product' => [fn () => QuotationRevisionLine::factory()->create(['entered_qty' => '0.500', 'qty_base' => '0.500']), 'quotation_revision_lines_c09_scale'],
    'a cancelled fraction of a counted product' => [fn () => LineScopeCancellation::factory()->create(['qty_base' => '0.500']), 'line_scope_cancellations_c09_quantity'],
    'a cancellation of nothing' => [fn () => LineScopeCancellation::factory()->create(['qty_base' => '0.000']), 'line_scope_cancellations_c09_quantity'],
    'a service fulfilled from the warehouse' => [fn () => ProjectLine::factory()->create(['kind' => 'SERVICE']), 'project_lines_mode_by_kind'],
    'a negative price' => [fn () => ProjectLine::factory()->create(['unit_price' => '-1.00']), 'project_lines_unit_price'],
]);

it('accepts a measured product\'s fraction at its scale and keys every row to the line\'s product and scale (C-09)', function () {
    $metres = Product::factory()->create(['quantity_scale' => 3]);
    $line = ProjectLine::factory()->create(['product_id' => $metres->id, 'entered_qty' => '1.250', 'qty_base' => '1.250']);

    Refusal::assert(Refusal::FOREIGN_KEY, 'project_lines_c09_product_scale_foreign', fn () => ProjectLine::factory()->create(['product_id' => $metres->id, 'quantity_scale' => 0]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'confirmation_line_pins_c09_line_foreign', fn () => ConfirmationLinePin::factory()->create(['project_line_id' => $line->id, 'commercial_confirmation_id' => CommercialConfirmation::factory()->create(['project_id' => $line->project_id])->id, 'quantity_scale' => 0]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'line_scope_cancellations_c09_line_foreign', fn () => LineScopeCancellation::factory()->create(['project_line_id' => $line->id, 'product_id' => Product::factory()->create()->id]));
    // The scale cannot change while a row references it (§1).
    Refusal::assert(Refusal::FOREIGN_KEY, 'project_lines_c09_product_scale_foreign', fn () => DB::table('products')->where('id', $metres->id)->update(['quantity_scale' => 2]));

    expect(LineScopeCancellation::factory()->create(['project_line_id' => $line->id, 'qty_base' => '0.125'])->qty_base)->toBe('0.125');
});

it('holds a warehouse line\'s reserved, dispatched and cancelled quantity within its demand and its delivery within its dispatch (C-05; C-30)', function () {
    $line = ProjectLine::factory()->create();
    lineGuard($line, ['demand_qty_base' => 10, 'reserved_qty_base' => 4, 'dispatched_net_qty_base' => 5, 'cancelled_qty_base' => 1, 'delivered_net_qty_base' => 5]);
    $guard = fn () => DB::table('project_line_balances')->where('project_line_id', $line->id);

    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_warehouse', fn () => $guard()->update(['reserved_qty_base' => 5]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_warehouse', fn () => $guard()->update(['delivered_net_qty_base' => 6]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_warehouse', fn () => $guard()->update(['dropship_confirmed_net_qty_base' => 1]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c05_not_negative', fn () => $guard()->update(['reserved_qty_base' => -1, 'cancelled_qty_base' => 6]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table project_line_balances', fn () => $guard()->update(['fulfillment_mode' => 'DROPSHIP']));

    // A lower demand fails the guard while the reserved, dispatched and cancelled quantities stand (L-20; BR-QUO-02).
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_warehouse', fn () => $guard()->update(['demand_qty_base' => 9]));

    $guard()->update(['reserved_qty_base' => 3, 'dispatched_net_qty_base' => 6, 'delivered_net_qty_base' => 6]);

    expect($guard()->value('dispatched_net_qty_base'))->toBe('6.000');
});

it('holds a drop-ship and a service line to their own mode\'s columns (C-30; §9)', function () {
    $dropship = ProjectLine::factory()->create(['fulfillment_mode' => 'DROPSHIP']);
    $service = ProjectLine::factory()->create(['kind' => 'SERVICE', 'fulfillment_mode' => 'SERVICE']);

    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_dropship', fn () => lineGuard($dropship, ['demand_qty_base' => 5, 'reserved_qty_base' => 1]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_dropship', fn () => lineGuard($dropship, ['demand_qty_base' => 5, 'dropship_confirmed_net_qty_base' => 4, 'delivered_net_qty_base' => 5]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_service', fn () => lineGuard($service, ['demand_qty_base' => 1, 'handed_over_qty_base' => 2]));
    Refusal::assert(Refusal::CHECK, 'project_line_balances_c30_service', fn () => lineGuard($service, ['demand_qty_base' => 1, 'handed_over_qty_base' => 1, 'delivered_net_qty_base' => 1]));
    // The guard's mode is its line's.
    Refusal::assert(Refusal::FOREIGN_KEY, 'project_line_balances_line_mode_foreign', fn () => DB::table('project_line_balances')->insert(['project_line_id' => $service->id, 'fulfillment_mode' => 'WAREHOUSE']));

    lineGuard($dropship, ['demand_qty_base' => 5, 'dropship_confirmed_net_qty_base' => 4, 'cancelled_qty_base' => 1, 'delivered_net_qty_base' => 4]);
    lineGuard($service, ['demand_qty_base' => 1, 'handed_over_qty_base' => 1]);

    expect(DB::table('project_line_balances')->whereIn('project_line_id', [$dropship->id, $service->id])->count())->toBe(2);
});

it('never invoices a project above its confirmed value (C-31; L-16)', function () {
    $project = Project::factory()->create();
    DB::table('project_commercial_balances')->insert(['project_id' => $project->id, 'confirmed_sales_value' => '1500000.00']);
    $guard = fn () => DB::table('project_commercial_balances')->where('project_id', $project->id);

    Refusal::assert(Refusal::CHECK, 'project_commercial_balances_c31_invoice_cap', fn () => $guard()->update(['invoiced_sales_value' => '1500000.01']));
    Refusal::assert(Refusal::CHECK, 'project_commercial_balances_not_negative', fn () => $guard()->update(['invoiced_sales_value' => '-1.00']));

    $guard()->update(['invoiced_sales_value' => '1500000.00']);

    Refusal::assert(Refusal::CHECK, 'project_commercial_balances_c31_invoice_cap', fn () => $guard()->update(['confirmed_sales_value' => '1000000.00']));
});

it('records a Force Complete only with its reason, snapshot and confirmed hash, one transition per project per command (C-38; §1)', function () {
    $project = Project::factory()->active()->create();
    $force = ['from_state' => 'ACTIVE', 'to_state' => 'COMPLETED_FORCED', 'transition_kind' => 'FORCE_COMPLETE'];
    $evidence = ['reason' => 'Klien menerima sisa pekerjaan tidak dilanjutkan', 'predicate_snapshot' => json_encode(['dibayar' => false]), 'confirmed_snapshot_hash' => hash('sha256', 'pemblokir')];

    Refusal::assert(Refusal::CHECK, 'project_state_transitions_c38_force_complete', fn () => ProjectStateTransition::factory()->create(['project_id' => $project->id, ...$force, ...$evidence, 'reason' => null]));
    Refusal::assert(Refusal::CHECK, 'project_state_transitions_c38_force_complete', fn () => ProjectStateTransition::factory()->create(['project_id' => $project->id, ...$force, ...$evidence, 'predicate_snapshot' => null]));
    Refusal::assert(Refusal::CHECK, 'project_state_transitions_c38_force_complete', fn () => ProjectStateTransition::factory()->create(['project_id' => $project->id, ...$force, ...$evidence, 'confirmed_snapshot_hash' => null]));
    Refusal::assert(Refusal::CHECK, 'project_state_transitions_hash_hex', fn () => ProjectStateTransition::factory()->create(['project_id' => $project->id, ...$force, ...$evidence, 'confirmed_snapshot_hash' => 'ABC']));

    $forced = ProjectStateTransition::factory()->create(['project_id' => $project->id, ...$force, ...$evidence]);

    // One correction revalidates every completed project it touches, each once (§1; FX-04).
    $command = (string) Str::uuid7();
    $revalidate = ['from_state' => 'COMPLETED_NORMAL', 'to_state' => 'COMPLETED_NORMAL', 'transition_kind' => 'REVALIDATE', 'command_id' => $command];
    ProjectStateTransition::factory()->create(['project_id' => Project::factory()->create()->id, ...$revalidate, 'command_line_no' => 1]);
    $second = Project::factory()->create();
    ProjectStateTransition::factory()->create(['project_id' => $second->id, ...$revalidate, 'command_line_no' => 2]);

    Refusal::assert(Refusal::UNIQUE, 'project_state_transitions_one_per_project_per_command', fn () => ProjectStateTransition::factory()->create(['project_id' => $second->id, ...$revalidate, 'command_line_no' => 3]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table project_state_transitions', fn () => DB::table('project_state_transitions')->where('id', $forced->id)->update(['reason' => 'Diubah']));

    expect(ProjectStateTransition::query()->where('command_id', $command)->count())->toBe(2);
});

it('confirms a revision once at its root, corrected only by a linear chain of supersessions in its project (C-49; C-59; AX-13)', function () {
    $revision = QuotationRevision::factory()->create(['state' => 'APPROVED', 'decided_by' => User::factory()->create()->id, 'decided_at' => now()]);
    $approve = ['project_id' => $revision->project_id, 'kind' => 'QUOTATION_APPROVAL', 'quotation_revision_id' => $revision->id];
    $root = CommercialConfirmation::factory()->create($approve);

    Refusal::assert(Refusal::UNIQUE, 'commercial_confirmations_c49_one_root_per_revision', fn () => CommercialConfirmation::factory()->create($approve));
    Refusal::assert(Refusal::CHECK, 'commercial_confirmations_revision_by_kind', fn () => CommercialConfirmation::factory()->create([...$approve, 'quotation_revision_id' => null]));
    Refusal::assert(Refusal::CHECK, 'commercial_confirmations_revision_by_kind', fn () => CommercialConfirmation::factory()->create(['project_id' => $revision->project_id, 'kind' => 'NO_QUOTATION', 'quotation_revision_id' => $revision->id]));
    Refusal::assert(Refusal::UNIQUE, 'commercial_confirmations_one_per_command', fn () => CommercialConfirmation::factory()->create(['project_id' => $revision->project_id, 'command_id' => $root->command_id, 'command_line_no' => 2]));

    $superseding = CommercialConfirmation::factory()->create([...$approve, 'supersedes_confirmation_id' => $root->id, 'reason' => 'Harga satuan salah ketik']);

    Refusal::assert(Refusal::UNIQUE, 'commercial_confirmations_c59_linear_chain', fn () => CommercialConfirmation::factory()->create([...$approve, 'supersedes_confirmation_id' => $root->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'commercial_confirmations_c59_supersedes_foreign', fn () => CommercialConfirmation::factory()->create(['supersedes_confirmation_id' => $superseding->id]));

    expect($superseding->supersedes?->is($root))->toBeTrue();
});

it('pins each line once per confirmation and points the line only at a pin of its own (§9; BR-SN-01)', function () {
    $line = ProjectLine::factory()->create();
    $confirmation = CommercialConfirmation::factory()->create(['project_id' => $line->project_id]);
    $pin = ConfirmationLinePin::factory()->create(['commercial_confirmation_id' => $confirmation->id, 'project_line_id' => $line->id]);
    $elsewhere = ConfirmationLinePin::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'confirmation_line_pins_one_per_line', fn () => ConfirmationLinePin::factory()->create(['commercial_confirmation_id' => $confirmation->id, 'project_line_id' => $line->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'project_lines_current_pin_foreign', fn () => DB::table('project_lines')->where('id', $line->id)->update(['current_pin_id' => $elsewhere->id]));
    Refusal::assert(Refusal::CHECK, 'confirmation_line_pins_tax_components_list', fn () => ConfirmationLinePin::factory()->create(['tax_components' => ['jenis' => 'PPN']]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table confirmation_line_pins', fn () => DB::table('confirmation_line_pins')->where('id', $pin->id)->update(['unit_price' => '1.00']));

    DB::table('project_lines')->where('id', $line->id)->update(['current_pin_id' => $pin->id]);

    expect($line->refresh()->currentPin?->is($pin))->toBeTrue();
});

it('cancels a line\'s scope once per command, a supersession within its line (AX-14; L-32)', function () {
    $line = ProjectLine::factory()->create();
    $command = ['command_id' => (string) Str::uuid7()];
    $first = LineScopeCancellation::factory()->create(['project_line_id' => $line->id, ...$command, 'command_line_no' => 1]);
    // One command cancels several lines.
    LineScopeCancellation::factory()->create([...$command, 'command_line_no' => 2]);

    Refusal::assert(Refusal::UNIQUE, 'line_scope_cancellations_one_per_line_per_command', fn () => LineScopeCancellation::factory()->create(['project_line_id' => $line->id, ...$command, 'command_line_no' => 3]));
    Refusal::assert(Refusal::UNIQUE, 'line_scope_cancellations_c48_command_identity', fn () => LineScopeCancellation::factory()->create([...$command, 'command_line_no' => 2]));
    Refusal::assert(Refusal::CHECK, 'line_scope_cancellations_c48_command_identity_pair', fn () => LineScopeCancellation::factory()->create(['command_id' => (string) Str::uuid7()]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'line_scope_cancellations_supersedes_foreign', fn () => LineScopeCancellation::factory()->create(['supersedes_id' => $first->id]));

    expect(LineScopeCancellation::factory()->create(['project_line_id' => $line->id, 'supersedes_id' => $first->id])->supersedes?->is($first))->toBeTrue();
});

it('deletes only a DRAFT project, its unconfirmed lines with it, and never a pinned line (§13, §19; L-37)', function () {
    $draft = Project::factory()->create();
    ProjectLine::factory()->count(2)->create(['project_id' => $draft->id]);
    $active = Project::factory()->active()->create();
    $pinned = ProjectLine::factory()->create(['project_id' => $active->id]);
    $pin = ConfirmationLinePin::factory()->create(['project_line_id' => $pinned->id, 'commercial_confirmation_id' => CommercialConfirmation::factory()->create(['project_id' => $active->id])->id]);
    DB::table('project_lines')->where('id', $pinned->id)->update(['current_pin_id' => $pin->id]);

    Refusal::assert(Refusal::CHECK, 'projects_draft_delete_only', fn () => DB::table('projects')->where('id', $active->id)->delete());
    Refusal::assert(Refusal::CHECK, 'project_lines_unconfirmed_delete_only', fn () => DB::table('project_lines')->where('id', $pinned->id)->delete());

    DB::table('projects')->where('id', $draft->id)->delete();

    expect(DB::table('project_lines')->where('project_id', $draft->id)->exists())->toBeFalse()
        ->and(DB::table('projects')->where('id', $active->id)->exists())->toBeTrue();
});

it('edits and deletes a quotation\'s lines only while it is a DRAFT, and deletes only a DRAFT revision (§4.5, §19; §28)', function () {
    $line = QuotationRevisionLine::factory()->create();
    $revision = $line->revision;
    $rows = fn () => DB::table('quotation_revision_lines')->where('id', $line->id);

    $rows()->update(['unit_price' => '140000.00', 'sales_value' => '1400000.00']);
    DB::table('quotation_revisions')->where('id', $revision->id)->update(['state' => 'SENT']);

    Refusal::assert(Refusal::CHECK, 'quotation_revision_lines_frozen_once_sent', fn () => $rows()->update(['unit_price' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'quotation_revision_lines_frozen_once_sent', fn () => $rows()->delete());
    Refusal::assert(Refusal::CHECK, 'quotation_revisions_draft_delete_only', fn () => DB::table('quotation_revisions')->where('id', $revision->id)->delete());
    Refusal::assert(Refusal::CHECK, 'quotation_revision_lines_frozen_once_sent', fn () => QuotationRevisionLine::factory()->create(['quotation_revision_id' => $revision->id]));
    Refusal::assert(Refusal::CHECK, 'quotation_revisions_decision_columns', fn () => DB::table('quotation_revisions')->where('id', $revision->id)->update(['state' => 'APPROVED']));
    // A SENT revision never returns to DRAFT, which would unfreeze its lines, and a decision is final.
    Refusal::assert(Refusal::CHECK, 'quotation_revisions_state_forward_only', fn () => DB::table('quotation_revisions')->where('id', $revision->id)->update(['state' => 'DRAFT']));

    DB::table('quotation_revisions')->where('id', $revision->id)->update(['state' => 'APPROVED', 'decided_by' => User::factory()->create()->id, 'decided_at' => now()]);

    Refusal::assert(Refusal::CHECK, 'quotation_revisions_state_forward_only', fn () => DB::table('quotation_revisions')->where('id', $revision->id)->update(['state' => 'REJECTED']));

    // A DRAFT revision is deleted with its lines.
    $draft = QuotationRevisionLine::factory()->create()->revision;
    DB::table('quotation_revisions')->where('id', $draft->id)->delete();

    expect($rows()->value('unit_price'))->toBe('140000.00')
        ->and(DB::table('quotation_revision_lines')->where('quotation_revision_id', $draft->id)->exists())->toBeFalse();
});

it('chains a project\'s revisions linearly, each number once (§4.5; SM:Quotation)', function () {
    $first = QuotationRevision::factory()->create(['revision_no' => 1]);

    Refusal::assert(Refusal::UNIQUE, 'quotation_revisions_revision_no', fn () => QuotationRevision::factory()->create(['project_id' => $first->project_id, 'revision_no' => 1]));

    QuotationRevision::factory()->create(['project_id' => $first->project_id, 'revision_no' => 2, 'supersedes_revision_id' => $first->id]);

    Refusal::assert(Refusal::UNIQUE, 'quotation_revisions_linear_chain', fn () => QuotationRevision::factory()->create(['project_id' => $first->project_id, 'revision_no' => 3, 'supersedes_revision_id' => $first->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'quotation_revisions_supersedes_foreign', fn () => QuotationRevision::factory()->create(['project_id' => $first->project_id, 'revision_no' => 3, 'supersedes_revision_id' => QuotationRevision::factory()->create()->id]));
});

it('locks SIPLAH details after the first channel-dependent commit (C-51; BR-PRJ-05)', function () {
    $detail = ProjectSiplahDetail::factory()->create();
    $row = fn () => DB::table('project_siplah_details')->where('project_id', $detail->project_id);

    $row()->update(['fee_amount' => '350000.00']);
    $row()->update(['locked_at' => now()]);

    Refusal::assert(Refusal::CHECK, 'project_siplah_details_c51_locked', fn () => $row()->update(['fee_amount' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'project_siplah_details_c51_locked', fn () => $row()->update(['locked_at' => null]));
    Refusal::assert(Refusal::CHECK, 'project_siplah_details_amounts_not_negative', fn () => ProjectSiplahDetail::factory()->create(['cashback_amount' => '-1.00']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table project_siplah_details', fn () => $row()->delete());

    expect($row()->value('fee_amount'))->toBe('350000.00');
});

it('holds a pin\'s and a quotation line\'s project line within their parent\'s project, not only its company (DIR-090 (3); APPR-019)', function () {
    $confirmation = CommercialConfirmation::factory()->create();
    $revision = QuotationRevision::factory()->create(['project_id' => $confirmation->project_id]);
    // A line of another project of the same company.
    $sibling = ProjectLine::factory()->create(['project_id' => Project::factory()->create(['company_id' => $confirmation->company_id])->id]);

    Refusal::assert(Refusal::CHECK, 'confirmation_line_pins_line_of_confirmation_project', fn () => ConfirmationLinePin::factory()->create(['commercial_confirmation_id' => $confirmation->id, 'project_line_id' => $sibling->id]));
    Refusal::assert(Refusal::CHECK, 'quotation_revision_lines_line_of_revision_project', fn () => QuotationRevisionLine::factory()->create(['quotation_revision_id' => $revision->id, 'project_line_id' => $sibling->id]));

    expect(ConfirmationLinePin::factory()->create(['commercial_confirmation_id' => $confirmation->id])->exists)->toBeTrue()
        ->and(QuotationRevisionLine::factory()->create(['quotation_revision_id' => $revision->id])->exists)->toBeTrue();
});

it('discards a DRAFT SIPLAH project with its details, and never an active one\'s (DIR-090 (4); §19; L-37)', function () {
    $draft = ProjectSiplahDetail::factory()->create()->project_id;
    $active = ProjectSiplahDetail::factory()->create(['project_id' => Project::factory()->active()->create(['channel' => 'SIPLAH'])->id])->project_id;

    Refusal::assert(Refusal::CHECK, 'projects_draft_delete_only', fn () => DB::table('projects')->where('id', $active)->delete());

    DB::table('projects')->where('id', $draft)->delete();

    expect(DB::table('project_siplah_details')->where('project_id', $draft)->exists())->toBeFalse()
        ->and(DB::table('project_siplah_details')->where('project_id', $active)->exists())->toBeTrue();
});
