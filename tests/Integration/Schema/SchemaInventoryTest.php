<?php

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-005), criteria 1 and 4: migrating an empty database creates exactly DATABASE §4.1's eight
// IAM tables, §4.13's seven OPS tables and the four infrastructure stores in scope — no queue, cache or
// job-batch table, those stores being Valkey — with the identifiers of §16 and the types of §17.

uses(RefreshDatabase::class);

const SCHEMA_TABLES = [
    'users', 'roles', 'capabilities', 'role_capabilities', 'user_roles', 'user_capability_grants', 'user_company_grants', 'user_external_identities',
    'audit_events', 'security_events', 'command_log', 'correction_cases', 'import_batches', 'import_rows', 'legacy_archive_items',
    'sessions', 'credential_tokens', 'export_requests', 'failed_jobs',
];

function columnTypes(string $where, array $bindings = []): array
{
    return collect(DB::select("select table_name || '.' || column_name as name, format_type(a.atttypid, a.atttypmod) as type
        from information_schema.columns c
        join pg_attribute a on a.attrelid = (quote_ident(c.table_schema) || '.' || quote_ident(c.table_name))::regclass and a.attname = c.column_name
        where c.table_schema = 'public' and {$where} order by 1", $bindings))
        ->mapWithKeys(fn (object $column): array => [$column->name => $column->type])->all();
}

it('creates exactly the IAM and OPS tables and the infrastructure stores (criterion 1)', function () {
    $tables = DB::table('pg_tables')->where('schemaname', 'public')->where('tablename', '<>', 'migrations')->orderBy('tablename')->pluck('tablename')->all();

    expect($tables)->toBe(collect(SCHEMA_TABLES)->sort()->values()->all());
});

it('keys every table it creates by an identity column, never a sequence the runtime would need (§16)', function () {
    // The framework's session ids and the export requests' UUIDv7 keys are the two stores' own identifiers.
    $keys = DB::select("select table_name, column_name, is_identity, identity_generation from information_schema.columns
        where table_schema = 'public' and column_name = 'id' and table_name not in ('sessions', 'export_requests') order by table_name");

    expect(collect($keys)->every(fn (object $key): bool => $key->is_identity === 'YES' && $key->identity_generation === 'ALWAYS'))->toBeTrue()
        ->and(count($keys))->toBe(14)
        ->and(DB::scalar("select count(*) from pg_class s where s.relkind = 'S' and s.relname <> 'migrations_id_seq' and not exists (select from pg_depend d where d.objid = s.oid and d.deptype = 'i')"))->toBe(0);
});

it('stores money as numeric(18,2), business dates as date and instants as timestamptz (criterion 4)', function () {
    $money = ['correction_cases.unbacked_refund_amount', 'correction_cases.residual_settlement_amount', 'correction_cases.returned_cost', 'correction_cases.refunded_value', 'correction_cases.replaced_value', 'correction_cases.unrecovered_value'];

    expect(columnTypes("data_type = 'numeric'"))->toEqual(array_fill_keys($money, 'numeric(18,2)'))
        ->and(columnTypes("data_type = 'date'"))->toBe(['import_batches.cutoff_date' => 'date'])
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
    expect(columnTypes("column_name = 'public_id'"))->toBe([
        'correction_cases.public_id' => 'uuid',
        'import_batches.public_id' => 'uuid',
        'users.public_id' => 'uuid',
    ]);

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
]);

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
