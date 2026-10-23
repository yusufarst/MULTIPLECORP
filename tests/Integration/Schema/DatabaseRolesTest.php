<?php

use App\Modules\CST\Models\CostAttribution;
use App\Modules\CST\Models\LotCostEntry;
use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 and the security requirement of TASK-005–TASK-007 (SECURITY H9-01; DATABASE §28; C-53): the
// application runtime runs no DDL and holds, table by table, only the privileges of its table's class;
// the append-only tables refuse its updates and deletes at the database and at the model.

uses(RefreshDatabase::class);

/** A master's archive columns (DATABASE §13, §19). */
const DEACTIVATION = ['is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason'];

/** DATABASE §28 for these tables: the runtime's table privileges and the only columns it may update. */
const RUNTIME_PRIVILEGES = [
    'users' => ['SELECT, INSERT', ['name', 'email', 'password', 'is_active', 'deactivated_at', 'deactivated_by', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step', 'locale', 'lock_version', 'updated_at']],
    'roles' => ['SELECT, INSERT', []],
    'capabilities' => ['SELECT, INSERT', []],
    'role_capabilities' => ['SELECT, INSERT', []],
    'user_roles' => ['SELECT, INSERT', ['role_id', 'granted_by', 'granted_at']],
    'user_capability_grants' => ['SELECT, INSERT', ['revoked_by', 'revoked_at']],
    'user_company_grants' => ['SELECT, INSERT', ['revoked_by', 'revoked_at']],
    'user_external_identities' => ['SELECT, INSERT', ['unlinked_at', 'unlinked_by', 'unlink_reason']],
    'sessions' => ['SELECT, INSERT, UPDATE, DELETE', []],
    'credential_tokens' => ['SELECT, INSERT, DELETE', ['used_at', 'revoked_at']],
    'audit_events' => ['SELECT, INSERT', []],
    'security_events' => ['SELECT, INSERT', []],
    'command_log' => ['SELECT, INSERT, DELETE', ['state', 'result_entity_type', 'result_entity_id', 'outcome_code', 'completed_at', 'expires_at']],
    'correction_cases' => ['SELECT, INSERT', ['state', 'closure_outcome', 'closed_by', 'closed_at', 'reopened_by', 'reopened_at', 'reopen_reason', 'reopen_command_id', 'unbacked_refund_amount', 'residual_settlement_amount', 'returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value', 'lock_version', 'updated_at']],
    'import_batches' => ['SELECT, INSERT', ['state', 'control_totals', 'dry_run_report', 'admin_signoff_by', 'admin_signoff_at', 'owner_signoff_by', 'owner_signoff_at', 'committed_by', 'committed_at', 'lock_version', 'updated_at']],
    'import_rows' => ['SELECT, INSERT', ['validation_state', 'errors', 'result_entity_type', 'result_entity_id', 'updated_at']],
    'legacy_archive_items' => ['SELECT, INSERT', []],
    'export_requests' => ['SELECT, INSERT, DELETE', ['state', 'claimed_at', 'claim_count', 'file_object_id', 'expires_at']],
    'failed_jobs' => ['SELECT, INSERT, DELETE', []],
    // TASK-006
    'companies' => ['SELECT, INSERT', ['legal_name', 'legal_form', 'npwp', 'address_lines', 'city', 'phone', 'email', 'director_name', 'logo_file_id', 'stamp_file_id', 'signature_file_id', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'company_bank_accounts' => ['SELECT, INSERT', ['bank_name', 'account_number', 'account_holder', 'branch', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'numbering_schemes' => ['SELECT, INSERT', ['period_granularity', 'format_template', 'revision_format', 'effective_from', 'lock_version', 'updated_at']],
    'number_sequences' => ['SELECT, INSERT', ['next_value', 'seed_value', 'seeded_by_import_row_id']],
    'retired_numbers' => ['SELECT, INSERT', []],
    'tax_types' => ['SELECT, INSERT', ['name', 'category', 'is_active', 'lock_version', 'updated_at']],
    'tax_rate_versions' => ['SELECT, INSERT', ['rate_percent', 'base_numerator', 'base_denominator', 'rounding_mode', 'effective', 'lock_version', 'updated_at']],
    'company_tax_profiles' => ['SELECT, INSERT', ['registration_status', 'effective', 'lock_version', 'updated_at']],
    'tax_treatment_rules' => ['SELECT, INSERT', ['treatment', 'evidence_required', 'effective', 'lock_version', 'updated_at']],
    'client_organizations' => ['SELECT, INSERT', ['name', 'npwp', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'client_units' => ['SELECT, INSERT', ['name', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'client_addresses' => ['SELECT, INSERT', ['label', 'address_lines', 'city', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'client_contacts' => ['SELECT, INSERT', ['name', 'position', 'phone', 'email', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'suppliers' => ['SELECT, INSERT', ['name', 'npwp', 'address_lines', 'city', 'phone', 'email', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'units' => ['SELECT, INSERT', ['name', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'products' => ['SELECT, INSERT', ['sku', 'name', 'quantity_scale', 'is_serialized', 'min_stock_qty_base', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'product_units' => ['SELECT, INSERT', ['ratio_to_base', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'product_barcodes' => ['SELECT, INSERT', [...DEACTIVATION, 'lock_version', 'updated_at']],
    'product_price_history' => ['SELECT, INSERT', []],
    'product_images' => ['SELECT, INSERT', ['sort_order', 'is_primary', 'lock_version', 'updated_at']],
    'document_types' => ['SELECT, INSERT', []],
    'documents' => ['SELECT, INSERT', []],
    'document_versions' => ['SELECT, INSERT, DELETE', [
        'state', 'business_date', 'numbering_scheme_id', 'period_key', 'sequence_no', 'revision_suffix', 'formatted_number',
        'quotation_revision_id', 'invoice_version_id', 'kuitansi_amount', 'company_bank_account_id', 'payload', 'payload_sha256',
        'issued_by', 'issue_command_id', 'voided_by', 'voided_at', 'voided_reason', 'lock_version', 'updated_at',
    ]],
    'document_renditions' => ['SELECT, INSERT', ['state', 'file_object_id', 'renderer_version', 'error_summary', 'updated_at']],
    'file_objects' => ['SELECT, INSERT', []],
    'evidence_documents' => ['SELECT, INSERT', ['current_version_id', 'lock_version', 'updated_at']],
    'evidence_document_versions' => ['SELECT, INSERT', []],
    'bank_statement_lines' => ['SELECT, INSERT', ['state', 'voided_by', 'voided_at', 'voided_reason']],
    'evidence_balances' => ['SELECT, INSERT', ['proven_amount', 'cited_amount', 'expensed_amount']],
    'evidence_links' => ['SELECT, INSERT', ['unlinked_at', 'unlinked_by', 'unlink_reason']],
    // TASK-007: DELETE on the draft tables only, through their draft triggers (§19)
    'projects' => ['SELECT, INSERT, DELETE', ['client_organization_id', 'client_unit_id', 'client_contact_id', 'client_address_id', 'channel', 'state', 'title', 'deadline', 'linked_project_id', 'link_reason', 'lock_version', 'updated_at']],
    'project_siplah_details' => ['SELECT, INSERT', ['external_reference', 'program_code', 'account_code', 'siplah_value', 'real_purchase_value', 'fee_amount', 'va_fee_amount', 'cashback_amount', 'refund_amount', 'notes', 'locked_at', 'lock_version', 'updated_at']],
    'project_lines' => ['SELECT, INSERT, DELETE', ['kind', 'product_id', 'quantity_scale', 'description', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base', 'unit_price', 'fulfillment_mode', 'current_pin_id', 'lock_version', 'updated_at']],
    'quotation_revisions' => ['SELECT, INSERT, DELETE', ['state', 'valid_until', 'decided_by', 'decided_at', 'decision_reason', 'lock_version', 'updated_at']],
    'quotation_revision_lines' => ['SELECT, INSERT, DELETE', ['description', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base', 'unit_price', 'sales_value', 'tax_components', 'lock_version', 'updated_at']],
    'commercial_confirmations' => ['SELECT, INSERT', []],
    'confirmation_line_pins' => ['SELECT, INSERT', []],
    'line_scope_cancellations' => ['SELECT, INSERT', []],
    'project_line_balances' => ['SELECT, INSERT', ['demand_qty_base', 'reserved_qty_base', 'dispatched_net_qty_base', 'dropship_confirmed_net_qty_base', 'handed_over_qty_base', 'delivered_net_qty_base', 'cancelled_qty_base']],
    'project_commercial_balances' => ['SELECT, INSERT', ['confirmed_sales_value', 'invoiced_sales_value']],
    'project_state_transitions' => ['SELECT, INSERT', []],
    'purchases' => ['SELECT, INSERT', ['state', 'cancelled_by', 'cancelled_at', 'cancelled_reason', 'lock_version', 'updated_at']],
    'purchase_lines' => ['SELECT, INSERT', ['kind', 'product_id', 'quantity_scale', 'project_line_id', 'delivery_mode', 'entered_qty', 'entered_unit_id', 'ratio_to_base', 'qty_base', 'unit_price', 'line_value', 'description', 'lock_version', 'updated_at']],
    'purchase_line_tax_components' => ['SELECT, INSERT, DELETE', ['tax_type_id', 'treatment_rule_id', 'treatment', 'rate_percent', 'base_amount', 'amount']],
    'purchase_charges' => ['SELECT, INSERT', []],
    'purchase_charge_line_shares' => ['SELECT, INSERT', []],
    'purchase_line_balances' => ['SELECT, INSERT', [
        'ordered_qty_base', 'normal_received_net_qty_base', 'closed_qty_base', 'dropship_confirmed_net_qty_base', 'replacement_expected_qty_base',
        'replacement_received_net_qty_base', 'goods_value', 'noncreditable_tax_value', 'posted_goods_value', 'posted_noncreditable_tax_value',
    ]],
    'purchase_line_closures' => ['SELECT, INSERT', []],
    'purchase_line_adjustments' => ['SELECT, INSERT', []],
    'lot_cost_entries' => ['SELECT, INSERT', []],
    'lot_cost_balances' => ['SELECT, INSERT', ['remaining_qty_base', 'remaining_cost']],
    'cost_attributions' => ['SELECT, INSERT', []],
    'purchase_charge_balances' => ['SELECT, INSERT', ['amount', 'allocated_amount', 'expensed_amount']],
    'purchase_charge_share_balances' => ['SELECT, INSERT', ['allocation_version', 'share_amount', 'posted_amount', 'respread_out_amount', 'expensed_out_amount']],
    'expenses' => ['SELECT, INSERT', []],
    'admin_requirements' => ['SELECT, INSERT', ['description', 'is_required', 'satisfaction_mode', 'requires_client_original', 'waiver_eligible', 'is_default_invoice_item', 'state', 'removed_by', 'removed_at', 'removed_reason', 'lock_version', 'updated_at']],
    'admin_requirement_links' => ['SELECT, INSERT', ['unlinked_at', 'unlinked_by', 'unlink_reason']],
    'admin_requirement_waivers' => ['SELECT, INSERT', []],
    // TASK-008
    'locations' => ['SELECT, INSERT', ['name', ...DEACTIVATION, 'lock_version', 'updated_at']],
    'inventory_lots' => ['SELECT, INSERT', []],
    'serial_units' => ['SELECT, INSERT', ['state', 'current_lot_id', 'current_location_id', 'current_condition', 'last_entry_id']],
    'stock_movements' => ['SELECT, INSERT', []],
    'stock_movement_entries' => ['SELECT, INSERT', []],
    'receiving_lines' => ['SELECT, INSERT', []],
    'dispatch_lines' => ['SELECT, INSERT', []],
    'stock_consumptions' => ['SELECT, INSERT', []],
    'stock_consumption_balances' => ['SELECT, INSERT', ['restored_qty_base', 'reclassified_qty_base', 'substituted_qty_base']],
    'stock_restorations' => ['SELECT, INSERT', []],
    'stock_product_balances' => ['SELECT, INSERT', ['on_hand_qty', 'unusable_qty', 'reserved_qty', 'version']],
    'stock_scope_balances' => ['SELECT, INSERT', ['claims_qty', 'pending_reduction_qty', 'pending_addition_qty', 'version', 'last_entry_id']],
    'stock_lot_balances' => ['SELECT, INSERT', ['qty']],
    'stock_reversal_balances' => ['SELECT, INSERT', ['reversed_qty_base']],
    'reservations' => ['SELECT, INSERT', ['state', 'remaining_qty_base', 'lock_version', 'updated_at']],
    'reservation_events' => ['SELECT, INSERT', []],
    'stock_counts' => ['SELECT, INSERT', ['state', 'lock_version', 'updated_at']],
    'stock_count_scopes' => ['SELECT, INSERT', []],
    'stock_count_lines' => ['SELECT, INSERT', []],
    'stock_count_findings' => ['SELECT, INSERT', ['state', 'attributed_company_id', 'flagged_cost_basis', 'decided_by', 'reason', 'lock_version', 'updated_at']],
    'unattributed_loss_cases' => ['SELECT, INSERT', ['found_qty_base', 'resolved_qty_base', 'reversed_qty_base', 'converted_qty_base']],
    'unattributed_loss_candidates' => ['SELECT, INSERT', []],
    'unattributed_loss_exposures' => ['SELECT, INSERT', ['assigned_net_qty_base']],
    'unattributed_loss_group_exposures' => ['SELECT, INSERT', ['bound_qty_base', 'assigned_net_qty_base']],
    'unattributed_loss_resolutions' => ['SELECT, INSERT', []],
    'unattributed_loss_resolution_lines' => ['SELECT, INSERT', []],
];

it('connects the application as the runtime role, which owns nothing and runs no DDL (H9-01)', function (string $statement, string $refusal) {
    expect(DB::scalar('select rolsuper from pg_roles where rolname = current_user'))->toBeFalse()
        ->and(DB::scalar('select count(*) from pg_tables where schemaname = ? and tableowner = current_user', ['public']))->toBe(0);

    Refusal::assert(Refusal::PRIVILEGE, $refusal, fn () => DB::statement($statement));
})->with([
    'create a table' => ['create table runtime_ddl (id bigint)', 'permission denied for schema public'],
    'create a function' => ['create function runtime_ddl() returns int language sql as $$ select 1 $$', 'permission denied for schema public'],
    'add a column' => ['alter table users add column nickname text', 'must be owner of table users'],
    'create an index' => ['create index runtime_ddl_index on users (name)', 'must be owner of table users'],
    'disable a trigger' => ['alter table command_log disable trigger command_log_delete_expired_only', 'must be owner of table command_log'],
    'drop a table' => ['drop table audit_events', 'must be owner of table audit_events'],
    'truncate a table' => ['truncate command_log', 'permission denied for table command_log'],
]);

it('reaches the migration role only through the migration connection, a role apart from the runtime\'s', function () {
    $migration = DB::connection(DatabaseRole::MIGRATION_CONNECTION);

    expect($migration->scalar('select current_user'))->not->toBe(DB::scalar('select current_user'))
        ->and($migration->scalar('select count(*) from pg_tables where schemaname = ? and tableowner = current_user', ['public']))->toBeGreaterThan(0)
        ->and(DB::scalar('select pg_has_role(?, ?, ?)', [$migration->getConfig('username'), DatabaseRole::RUNTIME, 'member']))->toBeFalse()
        ->and(DB::scalar('select pg_has_role(?, ?, ?)', [DatabaseRole::ADMINISTRATION, DatabaseRole::RUNTIME, 'member']))->toBeTrue();
});

it('grants the runtime exactly its table class\'s privileges (DATABASE §28)', function (string $table, string $privileges, array $updatable) {
    $granted = DB::table('information_schema.table_privileges')
        ->where('table_schema', 'public')->where('table_name', $table)->where('grantee', DatabaseRole::RUNTIME)
        ->orderByRaw("array_position(array['SELECT', 'INSERT', 'UPDATE', 'DELETE']::text[], privilege_type::text)")
        ->pluck('privilege_type')->implode(', ');
    $columns = DB::table('information_schema.column_privileges')
        ->where('table_schema', 'public')->where('table_name', $table)->where('grantee', DatabaseRole::RUNTIME)->where('privilege_type', 'UPDATE')
        ->pluck('column_name')->sort()->values()->all();
    $expectedColumns = str_contains($privileges, 'UPDATE')
        ? collect(DB::getSchemaBuilder()->getColumnListing($table))->sort()->values()->all()
        : collect($updatable)->sort()->values()->all();

    expect($granted)->toBe($privileges)
        ->and($columns)->toBe($expectedColumns);
})->with(array_map(fn (array $expected, string $table) => [$table, ...$expected], RUNTIME_PRIVILEGES, array_keys(RUNTIME_PRIVILEGES)));

it('states the runtime\'s privileges for every table the migrations create', function () {
    $tables = DB::table('pg_tables')->where('schemaname', 'public')->where('tablename', '<>', 'migrations')->orderBy('tablename')->pluck('tablename')->all();

    expect($tables)->toBe(collect(RUNTIME_PRIVILEGES)->keys()->sort()->values()->all());
});

it('lets the runtime draw the internal barcodes\' codes from their sequence, and nothing more of it (DATABASE §4.4)', function () {
    $first = (int) DB::scalar("select nextval('product_barcode_internal_codes')");

    expect((int) DB::scalar("select nextval('product_barcode_internal_codes')"))->toBeGreaterThan($first)
        ->and(DB::scalar("select has_sequence_privilege(current_user, 'product_barcode_internal_codes', 'UPDATE')"))->toBeFalse();

    Refusal::assert(Refusal::PRIVILEGE, 'must be owner of sequence', fn () => DB::statement('alter sequence product_barcode_internal_codes restart'));
});

it('grants the runtime no TRUNCATE, REFERENCES or TRIGGER anywhere, and the migration table nothing', function () {
    expect(DB::table('information_schema.table_privileges')->where('table_schema', 'public')->where('grantee', DatabaseRole::RUNTIME)
        ->whereNotIn('privilege_type', ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])->count())->toBe(0)
        ->and(DB::table('information_schema.table_privileges')->where('table_name', 'migrations')->where('grantee', DatabaseRole::RUNTIME)->count())->toBe(0);
});

it('refuses the runtime an update or a delete of append-only rows, at the database and at the model (C-53)', function (string $model, string $table) {
    /** @var AuditEvent|SecurityEvent|LegacyArchiveItem|CommercialConfirmation|LotCostEntry|CostAttribution $row */
    $row = $model::factory()->create();

    Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $row->id)->update(['recorded_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $row->id)->delete());

    expect(fn () => $row->forceFill(['recorded_at' => now()->subYear()])->save())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->delete())->toThrow(LogicException::class, 'append-only');
})->with([
    'audit events' => [AuditEvent::class, 'audit_events'],
    'security events' => [SecurityEvent::class, 'security_events'],
    'legacy archive items' => [LegacyArchiveItem::class, 'legacy_archive_items'],
    'commercial confirmations' => [CommercialConfirmation::class, 'commercial_confirmations'],
    'lot cost entries' => [LotCostEntry::class, 'lot_cost_entries'],
    'cost attributions' => [CostAttribution::class, 'cost_attributions'],
]);

it('changes an account\'s one launch role in place, as a demotion or the OWNER restoration does (TASK-024; RV-18)', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    [$from, $to] = [Role::factory()->create(), Role::factory()->create()];
    DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $from->id, 'granted_at' => now()->subMonth()]);

    DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => $to->id, 'granted_by' => $owner->id, 'granted_at' => now()]);

    expect(DB::table('user_roles')->where('user_id', $user->id)->pluck('role_id')->all())->toBe([$to->id]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'user_roles_role_id_foreign', fn () => DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => $to->id + 1000]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table user_roles', fn () => DB::table('user_roles')->where('user_id', $user->id)->update(['user_id' => $owner->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table user_roles', fn () => DB::table('user_roles')->where('user_id', $user->id)->delete());
});

it('refuses the runtime a change to the columns its table class keeps as inserted', function () {
    $user = User::factory()->create();

    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table users', fn () => DB::table('users')->where('id', $user->id)->update(['public_id' => DB::raw('uuidv7()')]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table users', fn () => DB::table('users')->where('id', $user->id)->update(['recorded_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table users', fn () => DB::table('users')->where('id', $user->id)->delete());
});
