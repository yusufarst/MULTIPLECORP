<?php

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\ADM\Models\AdminRequirementLink;
use App\Modules\ADM\Models\AdminRequirementWaiver;
use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Models\Unit;
use App\Modules\CST\Models\CostAttribution;
use App\Modules\CST\Models\Expense;
use App\Modules\CST\Models\LotCostEntry;
use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentType;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\EvidenceLink;
use App\Modules\DOC\Models\FileObject;
use App\Modules\FIN\Models\DeductionSettlement;
use App\Modules\FIN\Models\Disbursement;
use App\Modules\FIN\Models\FinancialContra;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceBillingAct;
use App\Modules\FIN\Models\InvoiceTaxComponent;
use App\Modules\FIN\Models\InvoiceVersion;
use App\Modules\FIN\Models\InvoiceVersionLine;
use App\Modules\FIN\Models\OpeningCustomerCredit;
use App\Modules\FIN\Models\OtherCashReceipt;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Models\PaymentApplication;
use App\Modules\FIN\Models\PaymentClaimedDeduction;
use App\Modules\FIN\Models\PrepaymentKuitansiLink;
use App\Modules\FIN\Models\ReceivableDisputeHold;
use App\Modules\FIN\Models\ReceivableWriteoff;
use App\Modules\FUL\Models\Delivery;
use App\Modules\FUL\Models\DeliveryClosure;
use App\Modules\FUL\Models\DeliveryLine;
use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\FUL\Models\DropshipConfirmationLine;
use App\Modules\FUL\Models\ServiceHandover;
use App\Modules\FUL\Models\ServiceHandoverLine;
use App\Modules\IAM\Models\Capability;
use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCapabilityGrant;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\IAM\Models\UserExternalIdentity;
use App\Modules\IAM\Models\UserRole;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\Location;
use App\Modules\INV\Models\ReceivingLine;
use App\Modules\INV\Models\Reservation;
use App\Modules\INV\Models\ReservationEvent;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountFinding;
use App\Modules\INV\Models\StockCountLine;
use App\Modules\INV\Models\StockMovement;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Models\StockRestoration;
use App\Modules\INV\Models\UnattributedLossCandidate;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Models\UnattributedLossResolution;
use App\Modules\INV\Models\UnattributedLossResolutionLine;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CommandLog;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Models\CompanyTaxProfile;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Models\RetiredNumber;
use App\Modules\ORG\Models\TaxRateVersion;
use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\ConfirmationLinePin;
use App\Modules\PRJ\Models\LineScopeCancellation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PRJ\Models\ProjectSiplahDetail;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Modules\PRJ\Models\QuotationRevision;
use App\Modules\PRJ\Models\QuotationRevisionLine;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineAdjustment;
use App\Modules\PUR\Models\PurchaseLineClosure;
use App\Modules\PUR\Models\PurchaseLineTaxComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-005–TASK-009), criteria 1 and 4 of each and TASK-007's–TASK-009's criterion 1: migrating an empty
// database creates exactly DATABASE §4.1's eight IAM tables, §4.13's seven OPS tables and the four
// infrastructure stores of TASK-005 — no queue, cache or job-batch table, those stores being Valkey —, the
// nine ORG, five PTY, six CAT and ten DOC tables of §4.2–§4.4 and §4.10, and the eleven PRJ, eight PUR, six
// CST and three ADM tables of §4.5, §4.6, §4.8 and §4.11, the 26 INV tables of §4.7, and the seven FUL and nineteen FIN tables of
// §4.9 and §4.12, with the identifiers of §16
// and the types of §17.

uses(RefreshDatabase::class);

const SCHEMA_TABLES = [
    // TASK-005
    'users', 'roles', 'capabilities', 'role_capabilities', 'user_roles', 'user_capability_grants', 'user_company_grants', 'user_external_identities',
    'audit_events', 'security_events', 'command_log', 'correction_cases', 'import_batches', 'import_rows', 'legacy_archive_items',
    'sessions', 'credential_tokens', 'export_requests', 'failed_jobs',
    // TASK-006
    'companies', 'company_bank_accounts', 'numbering_schemes', 'number_sequences', 'retired_numbers', 'tax_types', 'tax_rate_versions', 'company_tax_profiles', 'tax_treatment_rules',
    'client_organizations', 'client_units', 'client_addresses', 'client_contacts', 'suppliers',
    'products', 'units', 'product_units', 'product_barcodes', 'product_price_history', 'product_images',
    'document_types', 'documents', 'document_versions', 'document_renditions', 'file_objects', 'evidence_documents', 'evidence_document_versions', 'bank_statement_lines', 'evidence_balances', 'evidence_links',
    // TASK-007
    'projects', 'project_siplah_details', 'project_lines', 'commercial_confirmations', 'confirmation_line_pins', 'quotation_revisions', 'quotation_revision_lines',
    'line_scope_cancellations', 'project_line_balances', 'project_commercial_balances', 'project_state_transitions',
    'purchases', 'purchase_lines', 'purchase_line_tax_components', 'purchase_charges', 'purchase_charge_line_shares', 'purchase_line_balances', 'purchase_line_closures', 'purchase_line_adjustments',
    'lot_cost_entries', 'lot_cost_balances', 'cost_attributions', 'purchase_charge_balances', 'purchase_charge_share_balances', 'expenses',
    'admin_requirements', 'admin_requirement_links', 'admin_requirement_waivers',
    // TASK-008
    'locations', 'inventory_lots', 'serial_units', 'stock_movements', 'stock_movement_entries', 'receiving_lines', 'dispatch_lines', 'stock_consumptions',
    'stock_consumption_balances', 'stock_restorations', 'stock_product_balances', 'stock_scope_balances', 'stock_lot_balances', 'stock_reversal_balances',
    'reservations', 'reservation_events', 'stock_counts', 'stock_count_scopes', 'stock_count_lines', 'stock_count_findings', 'unattributed_loss_cases',
    'unattributed_loss_candidates', 'unattributed_loss_exposures', 'unattributed_loss_group_exposures', 'unattributed_loss_resolutions',
    'unattributed_loss_resolution_lines',
    // TASK-009
    'dropship_confirmations', 'dropship_confirmation_lines', 'deliveries', 'delivery_lines', 'delivery_closures', 'service_handovers', 'service_handover_lines',
    'invoices', 'invoice_versions', 'invoice_version_lines', 'invoice_tax_components', 'invoice_balances', 'invoice_billing_acts', 'payments',
    'payment_claimed_deductions', 'application_source_balances', 'payment_applications', 'deduction_settlements', 'receivable_writeoffs',
    'receivable_dispute_holds', 'opening_customer_credits', 'prepayment_kuitansi_links', 'disbursements', 'other_cash_receipts', 'financial_contras',
    'money_fact_balances',
];

/** The tables keyed otherwise than by an identity `id`: the stores' own identifiers, the link tables' pairs and the keys DATABASE §4 states. */
const OTHERWISE_KEYED = [
    'sessions', 'export_requests', 'command_log', 'role_capabilities', 'user_roles', 'document_types', 'number_sequences',
    'project_siplah_details', 'project_line_balances', 'project_commercial_balances', 'purchase_line_balances', 'lot_cost_balances', 'purchase_charge_balances',
    'purchase_charge_share_balances', 'stock_product_balances', 'stock_scope_balances', 'stock_lot_balances', 'stock_reversal_balances', 'stock_count_scopes',
    'unattributed_loss_exposures', 'unattributed_loss_group_exposures', 'invoice_balances',
];

function columnTypes(string $where, array $bindings = []): array
{
    return collect(DB::select("select table_name || '.' || column_name as name, format_type(a.atttypid, a.atttypmod) as type
        from information_schema.columns c
        join pg_attribute a on a.attrelid = (quote_ident(c.table_schema) || '.' || quote_ident(c.table_name))::regclass and a.attname = c.column_name
        where c.table_schema = 'public' and {$where} order by 1", $bindings))
        ->mapWithKeys(fn (object $column): array => [$column->name => $column->type])->all();
}

it('creates exactly the tables of the schema Tasks so far and the infrastructure stores (criterion 1)', function () {
    $tables = DB::table('pg_tables')->where('schemaname', 'public')->where('tablename', '<>', 'migrations')->orderBy('tablename')->pluck('tablename')->all();

    expect($tables)->toBe(collect(SCHEMA_TABLES)->sort()->values()->all());
});

it('keys every other table by an identity column, never a sequence the runtime would need but the one DATABASE states (§16)', function () {
    $keys = DB::table('information_schema.columns')->where('table_schema', 'public')->where('column_name', 'id')
        ->whereNotIn('table_name', OTHERWISE_KEYED)->orderBy('table_name')->get(['table_name', 'is_identity', 'identity_generation']);
    $sequences = DB::select("select s.relname from pg_class s where s.relkind = 'S' and s.relname <> 'migrations_id_seq'
        and not exists (select from pg_depend d where d.objid = s.oid and d.deptype = 'i')");

    expect($keys->pluck('table_name')->all())->toBe(collect(SCHEMA_TABLES)->diff(OTHERWISE_KEYED)->sort()->values()->all())
        ->and($keys->every(fn (object $key): bool => $key->is_identity === 'YES' && $key->identity_generation === 'ALWAYS'))->toBeTrue()
        // The internal barcodes' codes come from a database sequence, whose gaps are harmless (§4.4).
        ->and(array_column($sequences, 'relname'))->toBe(['product_barcode_internal_codes']);
});

it('keys the counters and the document catalogue as DATABASE §4.2 and §4.10 state', function () {
    $primaryKey = fn (string $table): array => array_column(DB::select('select a.attname from pg_index i
        join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any (i.indkey)
        where i.indrelid = ?::regclass and i.indisprimary order by array_position(i.indkey::smallint[], a.attnum)', [$table]), 'attname');

    expect($primaryKey('number_sequences'))->toBe(['company_id', 'sequence_type', 'period_key'])
        ->and($primaryKey('document_types'))->toBe(['code']);
});

it('keys each guard and the SIPLAH details by the record they belong to, as DATABASE §4.5–§4.8 state', function (string $table, array $key) {
    expect(array_column(DB::select('select a.attname from pg_index i
        join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any (i.indkey)
        where i.indrelid = ?::regclass and i.indisprimary order by array_position(i.indkey::smallint[], a.attnum)', [$table]), 'attname'))->toBe($key);
})->with([
    ['project_siplah_details', ['project_id']], ['project_line_balances', ['project_line_id']], ['project_commercial_balances', ['project_id']],
    ['purchase_line_balances', ['purchase_line_id']], ['lot_cost_balances', ['lot_id']], ['purchase_charge_balances', ['purchase_charge_id']],
    ['purchase_charge_share_balances', ['purchase_charge_id', 'purchase_line_id']],
    ['stock_product_balances', ['product_id']], ['stock_scope_balances', ['product_id', 'location_id', 'condition']],
    ['stock_lot_balances', ['lot_id', 'location_id', 'condition']], ['stock_reversal_balances', ['reversed_movement_id', 'lot_id', 'location_id', 'condition']],
    ['stock_count_scopes', ['stock_count_id', 'product_id', 'location_id', 'condition']], ['unattributed_loss_exposures', ['unattributed_loss_case_id', 'company_id']],
    ['unattributed_loss_group_exposures', ['exposure_group_case_id', 'company_id']], ['invoice_balances', ['invoice_id']],
]);

it('stores money as numeric(18,2), quantities as numeric(18,3), rates as numeric(9,6), dates as date and instants as timestamptz (criterion 4)', function () {
    $money = [
        'correction_cases.unbacked_refund_amount', 'correction_cases.residual_settlement_amount', 'correction_cases.returned_cost',
        'correction_cases.refunded_value', 'correction_cases.replaced_value', 'correction_cases.unrecovered_value',
        'product_price_history.amount', 'document_versions.kuitansi_amount', 'evidence_documents.proven_amount',
        'bank_statement_lines.amount', 'evidence_balances.proven_amount', 'evidence_balances.cited_amount', 'evidence_balances.expensed_amount',
        // TASK-007
        'confirmation_line_pins.unit_price', 'confirmation_line_pins.sales_value', 'cost_attributions.amount', 'expenses.amount', 'lot_cost_balances.remaining_cost',
        'lot_cost_entries.amount', 'project_commercial_balances.confirmed_sales_value', 'project_commercial_balances.invoiced_sales_value', 'project_lines.unit_price',
        'project_siplah_details.siplah_value', 'project_siplah_details.real_purchase_value', 'project_siplah_details.fee_amount', 'project_siplah_details.va_fee_amount',
        'project_siplah_details.cashback_amount', 'project_siplah_details.refund_amount', 'purchase_charge_balances.amount', 'purchase_charge_balances.allocated_amount',
        'purchase_charge_balances.expensed_amount', 'purchase_charge_line_shares.share_amount', 'purchase_charge_share_balances.share_amount',
        'purchase_charge_share_balances.posted_amount', 'purchase_charge_share_balances.respread_out_amount', 'purchase_charge_share_balances.expensed_out_amount',
        'purchase_charges.amount', 'purchase_line_adjustments.amount_delta', 'purchase_line_balances.goods_value', 'purchase_line_balances.noncreditable_tax_value',
        'purchase_line_balances.posted_goods_value', 'purchase_line_balances.posted_noncreditable_tax_value', 'purchase_line_tax_components.base_amount',
        'purchase_line_tax_components.amount', 'purchase_lines.unit_price', 'purchase_lines.line_value', 'quotation_revision_lines.unit_price', 'quotation_revision_lines.sales_value',
        // TASK-008
        'stock_count_findings.flagged_cost_basis', 'unattributed_loss_candidates.net_unit_cost_at_recognition',
        // TASK-009
        'application_source_balances.capacity_amount', 'application_source_balances.claimed_deductions_amount', 'application_source_balances.contra_amount',
        'application_source_balances.gross_amount', 'application_source_balances.invoice_applied_amount', 'application_source_balances.net_amount',
        'application_source_balances.refund_backed_amount', 'application_source_balances.refund_released_amount', 'application_source_balances.refund_shortfall_amount',
        'application_source_balances.settled_amount', 'deduction_settlements.amount', 'disbursements.amount', 'financial_contras.amount',
        'invoice_balances.applied_amount', 'invoice_balances.collected_tax_settled_amount', 'invoice_balances.fee_settled_amount', 'invoice_balances.gross_value',
        'invoice_balances.output_tax_value', 'invoice_balances.outstanding', 'invoice_balances.sales_value', 'invoice_balances.tax_settled_amount',
        'invoice_balances.written_off_amount', 'invoice_tax_components.amount', 'invoice_tax_components.base_amount', 'invoice_version_lines.line_sales_value',
        'invoice_version_lines.unit_price', 'invoice_versions.gross_value', 'invoice_versions.output_tax_value', 'invoice_versions.sales_value',
        'money_fact_balances.amount', 'money_fact_balances.backed_amount', 'money_fact_balances.contra_amount', 'money_fact_balances.released_amount',
        'money_fact_balances.shortfall_amount', 'money_fact_balances.transfer_cited_amount', 'opening_customer_credits.amount', 'other_cash_receipts.amount',
        'payment_applications.amount', 'payment_claimed_deductions.claimed_amount', 'payments.amount', 'receivable_writeoffs.amount',
        'receivable_writeoffs.outstanding_after', 'receivable_writeoffs.outstanding_before',
    ];
    $quantities = [
        'products.min_stock_qty_base',
        // TASK-007; a charge share's basis is a value, a quantity or a weight, exact at three places.
        'confirmation_line_pins.entered_qty', 'confirmation_line_pins.qty_base', 'line_scope_cancellations.qty_base', 'lot_cost_balances.remaining_qty_base',
        'project_line_balances.demand_qty_base', 'project_line_balances.reserved_qty_base', 'project_line_balances.dispatched_net_qty_base',
        'project_line_balances.dropship_confirmed_net_qty_base', 'project_line_balances.handed_over_qty_base', 'project_line_balances.delivered_net_qty_base',
        'project_line_balances.cancelled_qty_base', 'project_lines.entered_qty', 'project_lines.qty_base', 'purchase_charge_line_shares.basis_value',
        'purchase_line_balances.ordered_qty_base', 'purchase_line_balances.normal_received_net_qty_base', 'purchase_line_balances.closed_qty_base',
        'purchase_line_balances.dropship_confirmed_net_qty_base', 'purchase_line_balances.replacement_expected_qty_base', 'purchase_line_balances.replacement_received_net_qty_base',
        'purchase_line_closures.closed_qty_base', 'purchase_lines.entered_qty', 'purchase_lines.qty_base', 'quotation_revision_lines.entered_qty', 'quotation_revision_lines.qty_base',
        // TASK-008
        'dispatch_lines.entered_qty', 'dispatch_lines.qty_base', 'dispatch_lines.reserved_qty_base', 'dispatch_lines.unreserved_qty_base', 'inventory_lots.entered_qty',
        'inventory_lots.acquired_qty_base', 'receiving_lines.entered_qty', 'receiving_lines.qty_base', 'receiving_lines.usable_qty_base', 'receiving_lines.damaged_qty_base',
        'receiving_lines.rejected_qty_base', 'reservation_events.qty_delta_base', 'reservations.remaining_qty_base', 'stock_consumption_balances.qty_base',
        'stock_consumption_balances.restored_qty_base', 'stock_consumption_balances.reclassified_qty_base', 'stock_consumption_balances.substituted_qty_base',
        'stock_consumptions.qty_base', 'stock_count_findings.surplus_qty_base', 'stock_count_lines.counted_qty', 'stock_count_scopes.system_qty_at_start',
        'stock_lot_balances.qty', 'stock_movement_entries.qty_delta_base', 'stock_product_balances.on_hand_qty', 'stock_product_balances.unusable_qty',
        'stock_product_balances.reserved_qty', 'stock_restorations.entered_qty', 'stock_restorations.qty_base', 'stock_reversal_balances.original_qty_base',
        'stock_reversal_balances.reversed_qty_base', 'stock_scope_balances.claims_qty', 'stock_scope_balances.pending_reduction_qty',
        'stock_scope_balances.pending_addition_qty', 'stock_scope_balances.qty', 'unattributed_loss_candidates.qty_in_scope_at_recognition',
        'unattributed_loss_cases.recognized_qty_base', 'unattributed_loss_cases.exposure_qty_base', 'unattributed_loss_cases.prior_pending_qty_base',
        'unattributed_loss_cases.found_qty_base', 'unattributed_loss_cases.resolved_qty_base', 'unattributed_loss_cases.reversed_qty_base',
        'unattributed_loss_cases.converted_qty_base', 'unattributed_loss_cases.pending_qty_base', 'unattributed_loss_exposures.exposure_qty_base',
        'unattributed_loss_exposures.assigned_net_qty_base', 'unattributed_loss_group_exposures.bound_qty_base', 'unattributed_loss_group_exposures.assigned_net_qty_base',
        'unattributed_loss_resolution_lines.assigned_qty_base',
        // TASK-009
        'delivery_closures.closed_qty_base', 'delivery_lines.entered_qty', 'delivery_lines.qty_base', 'dropship_confirmation_lines.entered_qty',
        'dropship_confirmation_lines.qty_base', 'invoice_version_lines.entered_qty', 'invoice_version_lines.qty_base', 'service_handover_lines.entered_qty',
        'service_handover_lines.qty_base',
    ];
    $dates = [
        'import_batches.cutoff_date', 'numbering_schemes.effective_from', 'product_price_history.effective_from', 'document_versions.business_date',
        'evidence_documents.document_date', 'evidence_documents.received_date', 'bank_statement_lines.business_date',
        // TASK-007
        'commercial_confirmations.business_date', 'cost_attributions.business_date', 'expenses.business_date', 'line_scope_cancellations.business_date',
        'lot_cost_entries.business_date', 'projects.deadline', 'purchase_line_closures.business_date', 'purchases.business_date', 'quotation_revisions.valid_until',
        // TASK-008
        'inventory_lots.business_date', 'stock_movements.business_date', 'unattributed_loss_cases.business_date', 'unattributed_loss_resolutions.business_date',
        // TASK-009; a billing act's and the receivable guard's due dates are dates too.
        'deduction_settlements.business_date', 'deliveries.business_date', 'delivery_closures.business_date', 'disbursements.business_date',
        'dropship_confirmations.business_date', 'financial_contras.business_date', 'invoice_balances.current_due_date', 'invoice_billing_acts.billed_at',
        'invoice_billing_acts.due_date', 'invoice_versions.business_date', 'opening_customer_credits.business_date', 'other_cash_receipts.business_date',
        'payment_applications.business_date', 'payments.business_date', 'receivable_writeoffs.business_date', 'service_handovers.business_date',
    ];

    expect(columnTypes("data_type = 'numeric'"))->toEqual([
        ...array_fill_keys($money, 'numeric(18,2)'),
        ...array_fill_keys($quantities, 'numeric(18,3)'),
        'tax_rate_versions.rate_percent' => 'numeric(9,6)',
        'purchase_line_tax_components.rate_percent' => 'numeric(9,6)',
        'invoice_tax_components.rate_percent' => 'numeric(9,6)',
    ])
        ->and(columnTypes("data_type = 'date'"))->toEqual(array_fill_keys($dates, 'date'))
        ->and(columnTypes("udt_name = 'daterange'"))->toEqual(array_fill_keys(['company_tax_profiles.effective', 'tax_rate_versions.effective', 'tax_treatment_rules.effective'], 'daterange'))
        ->and(array_values(array_unique(columnTypes("data_type like 'timestamp%'"))))->toBe(['timestamp with time zone'])
        ->and(columnTypes("data_type in ('real', 'double precision')"))->toBe([]);
});

it('keys the export requests by a UUIDv7, their public identifier (RC-01; DIR-088)', function () {
    $id = DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => '{1001}',
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ]);

    expect(columnTypes("table_name = 'export_requests' and column_name = 'id'"))->toBe(['export_requests.id' => 'uuid'])
        ->and(Str::isUuid($id, 7))->toBeTrue();
});

it('gives the routable roots a UUIDv7 public identifier, from the model or from the database (§16)', function () {
    expect(columnTypes("column_name = 'public_id'"))->toEqual(array_fill_keys([
        'client_organizations.public_id', 'companies.public_id', 'correction_cases.public_id', 'document_versions.public_id',
        'documents.public_id', 'evidence_documents.public_id', 'file_objects.public_id', 'import_batches.public_id',
        'products.public_id', 'suppliers.public_id', 'users.public_id', 'projects.public_id', 'quotation_revisions.public_id', 'purchases.public_id',
        'stock_counts.public_id', 'stock_movements.public_id', 'unattributed_loss_cases.public_id',
        'dropship_confirmations.public_id', 'deliveries.public_id', 'service_handovers.public_id', 'invoices.public_id', 'payments.public_id', 'disbursements.public_id',
    ], 'uuid'));

    $user = User::factory()->create();
    $id = DB::table('users')->insertGetId(['name' => 'Citra Uji', 'email' => 'citra.uji@example.org', 'password' => 'tidak-dipakai']);

    expect(Str::isUuid($user->public_id, 7))->toBeTrue()
        ->and(DB::scalar('select uuid_extract_version(public_id) from users where id = ?', [$id]))->toBe(7)
        ->and(DB::scalar('select uuid_extract_version(public_id) from users where id = ?', [$user->id]))->toBe(7);
});

it('creates every model from its synthetic factory under the runtime\'s privileges', function (string $model) {
    /** @var class-string<Model> $model */
    $row = $model::factory()->create();

    expect($row->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    User::class, Role::class, Capability::class, UserCapabilityGrant::class, UserCompanyGrant::class, UserExternalIdentity::class,
    AuditEvent::class, SecurityEvent::class, CommandLog::class, CorrectionCase::class, ImportBatch::class, ImportRow::class, LegacyArchiveItem::class,
    Company::class, CompanyBankAccount::class, NumberingScheme::class, RetiredNumber::class, TaxType::class, TaxRateVersion::class,
    CompanyTaxProfile::class, TaxTreatmentRule::class, ClientOrganization::class, ClientUnit::class, ClientAddress::class, ClientContact::class,
    Supplier::class, Unit::class, Product::class, ProductUnit::class, ProductBarcode::class, ProductPriceHistory::class, ProductImage::class,
    DocumentType::class, Document::class, DocumentVersion::class, DocumentRendition::class, FileObject::class, EvidenceDocument::class,
    // EvidenceBalance has none: a balance belongs to its posting service (ARCHITECTURE §7, §19).
    EvidenceDocumentVersion::class, BankStatementLine::class, EvidenceLink::class,
    // TASK-007; nor have its guards.
    Project::class, ProjectSiplahDetail::class, ProjectLine::class, QuotationRevision::class, QuotationRevisionLine::class, CommercialConfirmation::class,
    ConfirmationLinePin::class, LineScopeCancellation::class, ProjectStateTransition::class, Purchase::class, PurchaseLine::class, PurchaseLineTaxComponent::class,
    PurchaseCharge::class, PurchaseChargeLineShare::class, PurchaseLineClosure::class, PurchaseLineAdjustment::class, LotCostEntry::class, CostAttribution::class,
    Expense::class, AdminRequirement::class, AdminRequirementLink::class, AdminRequirementWaiver::class,
    // TASK-008; nor have its guards, and the tables keyed by several columns have no model.
    Location::class, InventoryLot::class, SerialUnit::class, StockMovement::class, StockMovementEntry::class, ReceivingLine::class, DispatchLine::class,
    StockConsumption::class, StockRestoration::class, Reservation::class, ReservationEvent::class, StockCount::class, StockCountLine::class,
    StockCountFinding::class, UnattributedLossCase::class, UnattributedLossCandidate::class, UnattributedLossResolution::class,
    UnattributedLossResolutionLine::class,
    // TASK-009; nor have its guards.
    DropshipConfirmation::class, DropshipConfirmationLine::class, Delivery::class, DeliveryLine::class, DeliveryClosure::class, ServiceHandover::class, ServiceHandoverLine::class,
    Invoice::class, InvoiceVersion::class, InvoiceVersionLine::class, InvoiceTaxComponent::class, InvoiceBillingAct::class, Payment::class, PaymentClaimedDeduction::class, PaymentApplication::class,
    DeductionSettlement::class, ReceivableWriteoff::class, ReceivableDisputeHold::class, OpeningCustomerCredit::class, PrepaymentKuitansiLink::class, Disbursement::class, OtherCashReceipt::class, FinancialContra::class,
]);

it('loads the PRJ, PUR, CST and ADM models\' relations through their keys', function () {
    $line = ProjectLine::factory()->create();
    $pin = ConfirmationLinePin::factory()->create(['project_line_id' => $line->id, 'commercial_confirmation_id' => CommercialConfirmation::factory()->create(['project_id' => $line->project_id])->id]);
    DB::table('project_lines')->where('id', $line->id)->update(['current_pin_id' => $pin->id]);
    $share = PurchaseChargeLineShare::factory()->create();
    $requirement = AdminRequirementLink::factory()->create()->requirement;

    $line->refresh()->load('project.company', 'project.client', 'product', 'currentPin.confirmation', 'pins');
    $share->load('charge.purchase.supplier', 'charge.sourceBill', 'purchaseLine.purchase');
    $requirement?->load('project', 'links.evidence');

    expect($line->currentPin?->is($pin))->toBeTrue()
        ->and($line->pins->sole()->is($pin))->toBeTrue()
        ->and($line->project->company->id)->toBe($line->company_id)
        ->and($line->currentPin?->confirmation->project_id)->toBe($line->project_id)
        ->and($share->charge->sourceBill->evidence_type)->toBe('SUPPLIER_BILL')
        ->and($share->purchaseLine->purchase->is($share->charge->purchase))->toBeTrue()
        ->and($requirement?->links->sole()->evidence?->company_id)->toBe($requirement?->company_id);
});

it('loads the ORG, PTY, CAT and DOC models\' relations through their keys', function () {
    $company = Company::factory()->create();
    $account = CompanyBankAccount::factory()->for($company)->create();
    $unit = ClientUnit::factory()->create();
    ClientAddress::factory()->for($unit, 'unit')->create();
    $product = Product::factory()->create();
    ProductUnit::factory()->for($product)->create();
    $version = DocumentVersion::factory()->issued()->create(['document_id' => Document::factory()->for($company)->create()->id, 'company_bank_account_id' => $account->id]);
    $evidence = EvidenceDocument::factory()->for($company)->create();
    $evidence->forceFill(['current_version_id' => EvidenceDocumentVersion::factory()->for($evidence, 'evidence')->create()->id])->save();

    $company->load('bankAccounts');
    $unit->load('organization', 'addresses');
    $product->load('baseUnit', 'units.unit');
    $version->load('document.type', 'bankAccount', 'issuedBy');
    $evidence->load('currentVersion.file', 'company');

    expect($company->bankAccounts->sole()->is($account))->toBeTrue()
        ->and($unit->addresses)->toHaveCount(1)
        ->and($unit->organization->exists)->toBeTrue()
        ->and($product->units->sole()->unit->exists)->toBeTrue()
        ->and($version->document->company_id)->toBe($company->id)
        ->and($version->document->type->code)->toBe('DOC-01')
        ->and($version->bankAccount?->is($account))->toBeTrue()
        ->and($evidence->currentVersion?->file->exists)->toBeTrue()
        ->and($evidence->company?->is($company))->toBeTrue();
});

it('loads the models\' relations through their keys and pivots', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $capability = Capability::factory()->create();
    $role->capabilities()->attach($capability);
    $user->roles()->attach($role, ['granted_at' => now()]);
    UserCapabilityGrant::factory()->for($user)->for($capability)->create(['granted_by' => $user->id]);
    $batch = ImportBatch::factory()->create(['prepared_by' => $user->id]);
    ImportRow::factory()->for($batch, 'batch')->count(2)->create();

    $user->load('roles.capabilities', 'capabilityGrants.grantedBy');
    $batch->load('rows', 'preparedBy');

    expect($user->roles->sole()->is($role))->toBeTrue()
        ->and($user->roles->sole()->pivot)->toBeInstanceOf(UserRole::class)
        ->and($user->roles->sole()->capabilities->sole()->is($capability))->toBeTrue()
        ->and($user->capabilityGrants->sole()->grantedBy?->is($user))->toBeTrue()
        ->and($batch->rows)->toHaveCount(2)
        ->and($batch->preparedBy?->is($user))->toBeTrue();
});
