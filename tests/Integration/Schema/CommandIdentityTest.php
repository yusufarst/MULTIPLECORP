<?php

use App\Modules\IAM\Models\Capability;
use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCapabilityGrant;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\LegacyArchiveItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005), C-48: every row a user command inserts carries its command's identity, unique
// together in its table, so a replayed command cannot insert a second copy of any row — even after the
// command log has expired (DATABASE §1, §26). A row no command inserted carries neither half.

uses(RefreshDatabase::class);

dataset('command-written tables', [
    'users' => [fn (array $identity) => User::factory()->create($identity), 'users'],
    'user_roles' => [fn (array $identity) => DB::table('user_roles')->insert(['user_id' => User::factory()->create()->id, 'role_id' => Role::factory()->create()->id, 'granted_at' => now(), ...$identity]), 'user_roles'],
    'user_capability_grants' => [fn (array $identity) => UserCapabilityGrant::factory()->for(User::factory())->for(Capability::factory())->create($identity), 'user_capability_grants'],
    'user_company_grants' => [fn (array $identity) => UserCompanyGrant::factory()->create($identity), 'user_company_grants'],
    'correction_cases' => [fn (array $identity) => CorrectionCase::factory()->create($identity), 'correction_cases'],
    'import_batches' => [fn (array $identity) => ImportBatch::factory()->create($identity), 'import_batches'],
    'legacy_archive_items' => [fn (array $identity) => LegacyArchiveItem::factory()->create($identity), 'legacy_archive_items'],
]);

it('refuses a second row of one command line (C-48)', function (Closure $insert, string $table) {
    $commandId = (string) Str::uuid7();

    $insert(['command_id' => $commandId, 'command_line_no' => 1]);
    $insert(['command_id' => $commandId, 'command_line_no' => 2]);

    Refusal::assert(Refusal::UNIQUE, "{$table}_c48_command_identity", fn () => $insert(['command_id' => $commandId, 'command_line_no' => 1]));

    expect(DB::table($table)->where('command_id', $commandId)->count())->toBe(2);
})->with('command-written tables');

it('refuses half a command identity (C-48)', function (Closure $insert, string $table) {
    Refusal::assert(Refusal::CHECK, "{$table}_c48_command_identity_pair", fn () => $insert(['command_id' => (string) Str::uuid7()]));
    Refusal::assert(Refusal::CHECK, "{$table}_c48_command_identity_pair", fn () => $insert(['command_line_no' => 1]));
})->with('command-written tables');
