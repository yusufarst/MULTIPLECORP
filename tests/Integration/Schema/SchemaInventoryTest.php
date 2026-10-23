<?php

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Models\Unit;
use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentType;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\EvidenceLink;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\Capability;
use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCapabilityGrant;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\IAM\Models\UserExternalIdentity;
use App\Modules\IAM\Models\UserRole;
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
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-005, TASK-006), criteria 1 and 4 of each: migrating an empty database creates exactly
// DATABASE §4.1's eight IAM tables, §4.13's seven OPS tables and the four infrastructure stores of
// TASK-005 — no queue, cache or job-batch table, those stores being Valkey —, and the nine ORG, five PTY,
// six CAT and ten DOC tables of §4.2–§4.4 and §4.10, with the identifiers of §16 and the types of §17.

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
];

/** The tables keyed otherwise than by an identity `id`: the stores' own identifiers, the link tables' pairs and the keys DATABASE §4 states. */
const OTHERWISE_KEYED = ['sessions', 'export_requests', 'command_log', 'role_capabilities', 'user_roles', 'document_types', 'number_sequences'];

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

it('stores money as numeric(18,2), quantities as numeric(18,3), rates as numeric(9,6), dates as date and instants as timestamptz (criterion 4)', function () {
    $money = [
        'correction_cases.unbacked_refund_amount', 'correction_cases.residual_settlement_amount', 'correction_cases.returned_cost',
        'correction_cases.refunded_value', 'correction_cases.replaced_value', 'correction_cases.unrecovered_value',
        'product_price_history.amount', 'document_versions.kuitansi_amount', 'evidence_documents.proven_amount',
        'bank_statement_lines.amount', 'evidence_balances.proven_amount', 'evidence_balances.cited_amount', 'evidence_balances.expensed_amount',
    ];
    $dates = [
        'import_batches.cutoff_date', 'numbering_schemes.effective_from', 'product_price_history.effective_from', 'document_versions.business_date',
        'evidence_documents.document_date', 'evidence_documents.received_date', 'bank_statement_lines.business_date',
    ];

    expect(columnTypes("data_type = 'numeric'"))->toEqual([
        ...array_fill_keys($money, 'numeric(18,2)'),
        'products.min_stock_qty_base' => 'numeric(18,3)',
        'tax_rate_versions.rate_percent' => 'numeric(9,6)',
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
        'products.public_id', 'suppliers.public_id', 'users.public_id',
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
]);

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
