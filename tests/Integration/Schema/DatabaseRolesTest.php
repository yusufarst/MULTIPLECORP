<?php

use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\OPS\Models\SecurityEvent;
use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 and the security requirement of TASK-005 (SECURITY H9-01; DATABASE §28; C-53): the application
// runtime runs no DDL and holds, table by table, only the privileges of its table's class; the
// append-only tables refuse its updates and deletes at the database and at the model.

uses(RefreshDatabase::class);

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

it('grants the runtime no TRUNCATE, REFERENCES or TRIGGER anywhere, and the migration table nothing', function () {
    expect(DB::table('information_schema.table_privileges')->where('table_schema', 'public')->where('grantee', DatabaseRole::RUNTIME)
        ->whereNotIn('privilege_type', ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])->count())->toBe(0)
        ->and(DB::table('information_schema.table_privileges')->where('table_name', 'migrations')->where('grantee', DatabaseRole::RUNTIME)->count())->toBe(0);
});

it('refuses the runtime an update or a delete of append-only rows, at the database and at the model (C-53)', function (string $model, string $table) {
    /** @var AuditEvent|SecurityEvent|LegacyArchiveItem $row */
    $row = $model::factory()->create();

    Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $row->id)->update(['recorded_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $row->id)->delete());

    expect(fn () => $row->forceFill(['recorded_at' => now()->subYear()])->save())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->delete())->toThrow(LogicException::class, 'append-only');
})->with([
    'audit events' => [AuditEvent::class, 'audit_events'],
    'security events' => [SecurityEvent::class, 'security_events'],
    'legacy archive items' => [LegacyArchiveItem::class, 'legacy_archive_items'],
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
