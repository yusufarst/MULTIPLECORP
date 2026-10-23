<?php

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\CST\Models\CostAttribution;
use App\Modules\CST\Models\Expense;
use App\Modules\CST\Models\LotCostEntry;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceLink;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockMovement;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\QuotationRevision;
use App\Modules\PUR\Models\Purchase;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), criterion 6 and DIR-088 (1): the foreign keys TASK-006 adds from TASK-005's columns
// to `companies` and `file_objects` exist, exactly those, each restricting a delete, and each refuses a
// row that references no company or file — the legacy archive item's file also another company's, its
// key carrying the company (C-32; DATABASE §15). `command_log`'s and `security_events`' company columns
// stay plain values (DATABASE §4.13).
//
// TL-05 (TASK-007), criteria 6 and 7, DIR-088 and DIR-089 (1): the foreign keys TASK-007 adds from
// TASK-005's and TASK-006's columns to its projects, purchases, quotation revisions, confirmations,
// expenses and requirements exist, exactly those, each restricting a delete, and each refuses a row that
// references no record, or another company's where the key carries the company; the causal project of a
// correction case is an intentional cross-company link (§15).
//
// TL-05 (TASK-008), criteria 6–8, DIR-088, DIR-089 (1) and DIR-090 (1): the foreign keys and triggers
// TASK-008 adds from TASK-005's, TASK-006's and TASK-007's columns to its lots, movements, cases,
// consumptions and restorations exist, exactly those, each refusing a row that references no record, or
// another company's; pool evidence links only to a pool movement; a lot's basis is its acquisition's and a
// lot draw's bearer its consumption's, a return settlement aside.

uses(RefreshDatabase::class);

const FOUNDATION_TABLES = [
    'users', 'roles', 'capabilities', 'role_capabilities', 'user_roles', 'user_capability_grants', 'user_company_grants', 'user_external_identities',
    'audit_events', 'security_events', 'command_log', 'correction_cases', 'import_batches', 'import_rows', 'legacy_archive_items',
    'sessions', 'credential_tokens', 'export_requests', 'failed_jobs',
];

it('adds exactly the foreign keys DIR-088 assigns to this Task, each restricting a delete', function () {
    $keys = DB::select("select conname, pg_get_constraintdef(oid) as definition from pg_constraint
        where contype = 'f' and conrelid::regclass::text = any (?::text[]) and confrelid::regclass::text in ('companies', 'file_objects')
        order by conname", ['{'.implode(',', FOUNDATION_TABLES).'}']);

    expect(array_column($keys, 'definition', 'conname'))->toBe([
        'audit_events_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'correction_cases_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'export_requests_file_object_id_foreign' => 'FOREIGN KEY (file_object_id) REFERENCES file_objects(id) ON DELETE RESTRICT',
        'import_batches_source_file_id_foreign' => 'FOREIGN KEY (source_file_id) REFERENCES file_objects(id) ON DELETE RESTRICT',
        'import_rows_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'legacy_archive_items_c32_file_object_foreign' => 'FOREIGN KEY (company_id, file_object_id) REFERENCES file_objects(company_id, id) ON DELETE RESTRICT',
        'legacy_archive_items_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
        'user_company_grants_company_id_foreign' => 'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT',
    ]);
});

it('refuses a TASK-005 row that references no company (DIR-088; C-32)', function (Closure $write, string $constraint) {
    Refusal::assert(Refusal::FOREIGN_KEY, $constraint, $write);
})->with([
    'a company grant' => [fn () => UserCompanyGrant::factory()->create(['company_id' => 999_999]), 'user_company_grants_company_id_foreign'],
    'a correction case' => [fn () => CorrectionCase::factory()->create(['company_id' => 999_999]), 'correction_cases_company_id_foreign'],
    'an import row' => [fn () => ImportRow::factory()->create(['company_id' => 999_999]), 'import_rows_company_id_foreign'],
    'a legacy archive item' => [fn () => LegacyArchiveItem::factory()->create(['company_id' => 999_999]), 'legacy_archive_items_company_id_foreign'],
    'an audit event' => [fn () => AuditEvent::factory()->create(['company_id' => 999_999]), 'audit_events_company_id_foreign'],
]);

it('refuses a reference to no file, and a legacy archive item holding a file not its company\'s (DIR-088; C-32; §15)', function () {
    $company = Company::factory()->create();
    $archived = fn (int $file) => LegacyArchiveItem::factory()->create(['company_id' => $company->id, 'file_object_id' => $file]);
    $request = DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => "{{$company->id}}",
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(FileObject::factory()->create(['purpose' => 'ARCHIVE'])->id));
    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(FileObject::factory()->productImage()->create()->id));
    Refusal::assert(Refusal::FOREIGN_KEY, 'legacy_archive_items_c32_file_object_foreign', fn () => $archived(999_999));
    Refusal::assert(Refusal::FOREIGN_KEY, 'import_batches_source_file_id_foreign', fn () => ImportBatch::factory()->create(['source_file_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'export_requests_file_object_id_foreign', fn () => DB::table('export_requests')->where('id', $request)->update(['file_object_id' => 999_999]));
});

it('accepts the rows that reference their own company and file', function () {
    $company = Company::factory()->create();
    $file = FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'ARCHIVE']);
    // An export belongs to no company: its request names them (DIR-089 (5)).
    $export = FileObject::factory()->create(['company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'EXPORT']);
    $request = DB::table('export_requests')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => "{{$company->id}}",
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ]);
    DB::table('export_requests')->where('id', $request)->update(['state' => 'READY', 'file_object_id' => $export->id]);

    expect(UserCompanyGrant::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(CorrectionCase::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(ImportRow::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(AuditEvent::factory()->create(['company_id' => $company->id])->company?->is($company))->toBeTrue()
        ->and(LegacyArchiveItem::factory()->create(['company_id' => $company->id, 'file_object_id' => $file->id])->file?->is($file))->toBeTrue()
        ->and(ImportBatch::factory()->create()->sourceFile?->purpose)->toBe('IMPORT_SOURCE')
        ->and(DB::table('export_requests')->where('id', $request)->value('file_object_id'))->toBe($export->id)
        // The company context of an audit event and of an import row is optional (DATABASE §4.13).
        ->and(AuditEvent::factory()->create()->company_id)->toBeNull()
        ->and(ImportRow::factory()->create()->company_id)->toBeNull();
});

it('adds exactly the foreign keys DIR-088 and DIR-089 (1) assign to TASK-007, each restricting a delete', function () {
    $keys = DB::select("select conname, pg_get_constraintdef(oid) as definition from pg_constraint
        where contype = 'f' and conrelid::regclass::text in ('correction_cases', 'documents', 'document_versions', 'evidence_links')
            and confrelid::regclass::text in ('projects', 'purchases', 'quotation_revisions', 'commercial_confirmations', 'expenses', 'admin_requirements')
        order by conname");

    expect(array_column($keys, 'definition', 'conname'))->toBe([
        'correction_cases_c32_project_foreign' => 'FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id) ON DELETE RESTRICT',
        'correction_cases_causal_project_foreign' => 'FOREIGN KEY (causal_project_id) REFERENCES projects(id) ON DELETE RESTRICT',
        'document_versions_c32_quotation_revision_foreign' => 'FOREIGN KEY (company_id, quotation_revision_id) REFERENCES quotation_revisions(company_id, id) ON DELETE RESTRICT',
        'documents_c32_project_foreign' => 'FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id) ON DELETE RESTRICT',
        'documents_c32_purchase_foreign' => 'FOREIGN KEY (company_id, purchase_id) REFERENCES purchases(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_admin_requirement_foreign' => 'FOREIGN KEY (company_id, admin_requirement_id) REFERENCES admin_requirements(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_commercial_confirmation_foreign' => 'FOREIGN KEY (company_id, commercial_confirmation_id) REFERENCES commercial_confirmations(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_expense_foreign' => 'FOREIGN KEY (company_id, expense_id) REFERENCES expenses(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_project_foreign' => 'FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_purchase_foreign' => 'FOREIGN KEY (company_id, purchase_id) REFERENCES purchases(company_id, id) ON DELETE RESTRICT',
    ]);
});

it('refuses a TASK-005 or TASK-006 row that references no record of TASK-007, or another company\'s (DIR-088; DIR-089 (1); C-32)', function (Closure $target, string $column, Closure $write, string $constraint) {
    $company = Company::factory()->create()->id;

    Refusal::assert(Refusal::FOREIGN_KEY, $constraint, fn () => $write($company, $column, 999_999));
    Refusal::assert(Refusal::FOREIGN_KEY, $constraint, fn () => $write($company, $column, $target(Company::factory()->create()->id)));

    expect($write($company, $column, $target($company)))->toBeTrue();
})->with(function () {
    $project = static fn (int $company): int => Project::factory()->create(['company_id' => $company])->id;
    $purchase = static fn (int $company): int => Purchase::factory()->create(['company_id' => $company])->id;
    $confirmation = static fn (int $company): int => CommercialConfirmation::factory()->create(['project_id' => $project($company)])->id;
    $expense = static fn (int $company): int => Expense::factory()->create(['company_id' => $company])->id;
    $requirement = static fn (int $company): int => AdminRequirement::factory()->create(['project_id' => $project($company)])->id;
    $case = static fn (int $company, string $column, int $id): bool => CorrectionCase::factory()->create(['company_id' => $company, $column => $id])->exists;
    $document = static fn (int $company, string $column, int $id): bool => Document::factory()->create([
        'company_id' => $company, 'document_type_code' => DocumentTypeFactory::ensure($column === 'project_id' ? 'DOC-01' : 'DOC-02'), 'project_id' => null, $column => $id,
    ])->exists;
    $link = static fn (int $company, string $column, int $id): bool => EvidenceLink::factory()->create([
        'evidence_document_id' => EvidenceDocument::factory()->create(['company_id' => $company])->id, 'evidence_scope' => 'COMPANY', 'company_id' => $company, $column => $id,
    ])->exists;

    return [
        'a correction case\'s project' => [$project, 'project_id', $case, 'correction_cases_c32_project_foreign'],
        'a document\'s project' => [$project, 'project_id', $document, 'documents_c32_project_foreign'],
        'a document\'s purchase' => [$purchase, 'purchase_id', $document, 'documents_c32_purchase_foreign'],
        'an evidence link\'s project' => [$project, 'project_id', $link, 'evidence_links_c32_project_foreign'],
        'an evidence link\'s confirmation' => [$confirmation, 'commercial_confirmation_id', $link, 'evidence_links_c32_commercial_confirmation_foreign'],
        'an evidence link\'s purchase' => [$purchase, 'purchase_id', $link, 'evidence_links_c32_purchase_foreign'],
        'an evidence link\'s expense' => [$expense, 'expense_id', $link, 'evidence_links_c32_expense_foreign'],
        'an evidence link\'s requirement' => [$requirement, 'admin_requirement_id', $link, 'evidence_links_c32_admin_requirement_foreign'],
    ];
});

it('keys a version\'s rendered revision in its company, and a case\'s causal project in any company (DIR-089 (1); §15)', function () {
    $version = DocumentVersion::factory()->create();
    $own = QuotationRevision::factory()->create(['project_id' => Project::factory()->create(['company_id' => $version->company_id])->id]);
    $row = fn () => DB::table('document_versions')->where('id', $version->id);

    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_c32_quotation_revision_foreign', fn () => $row()->update(['quotation_revision_id' => QuotationRevision::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'document_versions_c32_quotation_revision_foreign', fn () => $row()->update(['quotation_revision_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'correction_cases_causal_project_foreign', fn () => CorrectionCase::factory()->create(['causal_project_id' => 999_999]));

    $row()->update(['quotation_revision_id' => $own->id]);
    $case = CorrectionCase::factory()->create(['case_kind' => 'PURCHASE_RETURN', 'causal_project_id' => Project::factory()->create()->id]);

    expect($row()->value('quotation_revision_id'))->toBe($own->id)
        ->and($case->causal_project_id)->not->toBeNull();
});

it('adds exactly the foreign keys DIR-088, DIR-089 (1) and DIR-090 (1) assign to TASK-008, each restricting a delete', function () {
    $keys = DB::select("select conname, pg_get_constraintdef(oid) as definition from pg_constraint
        where contype = 'f' and conrelid::regclass::text in ('correction_cases', 'documents', 'evidence_links', 'lot_cost_entries', 'lot_cost_balances', 'cost_attributions')
            and confrelid::regclass::text in ('inventory_lots', 'stock_movements', 'unattributed_loss_cases', 'stock_consumptions', 'stock_restorations')
        order by conname");

    expect(array_column($keys, 'definition', 'conname'))->toBe([
        'correction_cases_source_lot_foreign' => 'FOREIGN KEY (source_lot_id) REFERENCES inventory_lots(id) ON DELETE RESTRICT',
        'cost_attributions_c28_consumption_foreign' => 'FOREIGN KEY (stock_consumption_id, lot_id, source_company_id) REFERENCES stock_consumptions(id, lot_id, source_company_id) ON DELETE RESTRICT',
        'cost_attributions_restoration_foreign' => 'FOREIGN KEY (stock_restoration_id) REFERENCES stock_restorations(id) ON DELETE RESTRICT',
        'documents_c32_dispatch_movement_foreign' => 'FOREIGN KEY (company_id, dispatch_movement_id) REFERENCES stock_movements(company_id, id) ON DELETE RESTRICT',
        'documents_c32_receiving_movement_foreign' => 'FOREIGN KEY (company_id, receiving_movement_id) REFERENCES stock_movements(company_id, id) ON DELETE RESTRICT',
        'evidence_links_c32_stock_movement_foreign' => 'FOREIGN KEY (company_id, stock_movement_id) REFERENCES stock_movements(company_id, id) ON DELETE RESTRICT',
        'evidence_links_stock_movement_foreign' => 'FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id) ON DELETE RESTRICT',
        'evidence_links_unattributed_loss_case_foreign' => 'FOREIGN KEY (unattributed_loss_case_id) REFERENCES unattributed_loss_cases(id) ON DELETE RESTRICT',
        'lot_cost_balances_lot_foreign' => 'FOREIGN KEY (lot_id) REFERENCES inventory_lots(id) ON DELETE RESTRICT',
        'lot_cost_entries_c28_lot_foreign' => 'FOREIGN KEY (lot_id, source_company_id) REFERENCES inventory_lots(id, source_company_id) ON DELETE RESTRICT',
        'lot_cost_entries_restoration_foreign' => 'FOREIGN KEY (stock_restoration_id) REFERENCES stock_restorations(id) ON DELETE RESTRICT',
    ]);
});

it('refuses an earlier Task\'s row that references no INV record, or another company\'s, and pool evidence on a company\'s movement (DIR-088; DIR-089 (1); DIR-090 (1); C-32; C-58)', function () {
    $company = Company::factory()->create();
    $evidence = EvidenceDocument::factory()->create(['company_id' => $company->id]);
    $pool = EvidenceDocument::factory()->pool()->create();
    $theirs = StockMovement::factory()->create(['company_id' => Company::factory()->create()->id, 'movement_type' => 'DISPATCH'])->id;
    $link = fn (EvidenceDocument $evidence, int $movement) => EvidenceLink::factory()->create(['evidence_document_id' => $evidence->id, 'stock_movement_id' => $movement]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'correction_cases_source_lot_foreign', fn () => CorrectionCase::factory()->create(['source_lot_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'documents_c32_dispatch_movement_foreign', fn () => Document::factory()->create([
        'company_id' => $company->id, 'document_type_code' => DocumentTypeFactory::ensure('DOC-03'), 'project_id' => null, 'dispatch_movement_id' => $theirs,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'documents_c32_receiving_movement_foreign', fn () => Document::factory()->create([
        'company_id' => $company->id, 'document_type_code' => DocumentTypeFactory::ensure('DOC-12'), 'project_id' => null, 'receiving_movement_id' => $theirs,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_stock_movement_foreign', fn () => $link($pool, 999_999));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_c32_stock_movement_foreign', fn () => $link($evidence, $theirs));
    Refusal::assert(Refusal::CHECK, 'evidence_links_c58_pool_movement', fn () => $link($pool, $theirs));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_unattributed_loss_case_foreign', fn () => EvidenceLink::factory()->create(['evidence_document_id' => $pool->id, 'unattributed_loss_case_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_c28_lot_foreign', fn () => LotCostEntry::factory()->create(['lot_id' => InventoryLot::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_restoration_foreign', fn () => LotCostEntry::factory()->create(['entry_kind' => 'COST_CORRECTION', 'stock_restoration_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'cost_attributions_restoration_foreign', fn () => CostAttribution::factory()->create(['stock_restoration_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_balances_lot_foreign', fn () => DB::table('lot_cost_balances')->insert(['lot_id' => 999_999]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'cost_attributions_c28_consumption_foreign', function (): void {
        $consumption = StockConsumption::factory()->create();
        CostAttribution::factory()->create(['purchase_line_id' => null, 'cost_component' => null, 'stock_consumption_id' => $consumption->id,
            'lot_id' => InventoryLot::factory()->create(['source_company_id' => $consumption->source_company_id])->id, 'source_company_id' => $consumption->source_company_id,
            'bearer_company_id' => $consumption->bearer_company_id]);
    });

    expect($link($pool, StockMovement::factory()->create()->id)->exists)->toBeTrue()
        ->and($link($evidence, StockMovement::factory()->create(['company_id' => $company->id, 'movement_type' => 'RECEIPT'])->id)->exists)->toBeTrue();
});

it('holds a lot\'s basis to its acquisition and a lot draw\'s bearer to its consumption\'s, a return settlement aside (DIR-090 (1); §4.8; §6.5)', function () {
    $opening = InventoryLot::factory()->create();
    $consumption = StockConsumption::factory()->create();
    $draw = ['purchase_line_id' => null, 'cost_component' => null, 'stock_consumption_id' => $consumption->id, 'lot_id' => $consumption->lot_id, 'source_company_id' => $consumption->source_company_id];
    $other = Company::factory()->create()->id;

    Refusal::assert(Refusal::CHECK, 'lot_cost_entries_basis_of_acquisition', fn () => LotCostEntry::factory()->create(['lot_id' => $opening->id, 'source_company_id' => $opening->source_company_id, 'entry_kind' => 'SURPLUS_FLAGGED_BASIS']));
    Refusal::assert(Refusal::CHECK, 'cost_attributions_c28_bearer_of_consumption', fn () => CostAttribution::factory()->create([...$draw, 'bearer_company_id' => $other, 'allocation_reason' => 'Dibebankan ke PT lain']));

    expect(LotCostEntry::factory()->create(['lot_id' => $opening->id, 'source_company_id' => $opening->source_company_id])->entry_kind)->toBe('OPENING_COST')
        ->and(CostAttribution::factory()->create([...$draw, 'bearer_company_id' => $consumption->bearer_company_id])->exists)->toBeTrue()
        // The unrecovered share of a supplier return is borne by its case's causal project (§6.5).
        ->and(CostAttribution::factory()->create([...$draw, 'cost_class' => 'LOSS', 'cause' => 'RETURN_SETTLEMENT', 'bearer_company_id' => $other, 'allocation_reason' => 'Bagian tak terpulihkan'])->exists)->toBeTrue();
});
