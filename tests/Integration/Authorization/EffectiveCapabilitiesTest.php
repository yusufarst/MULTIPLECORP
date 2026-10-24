<?php

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CatalogueSync;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-011): the effective-capability and company-scope resolver of SECURITY EN-02 over TASK-005's
// tables (PERMISSIONS_MATRIX RG-01–RG-07, RG-04's default deny, OD-16's anomaly; AZ-03; PF-).

uses(RefreshDatabase::class);

beforeEach(fn () => Authorization::catalogue());

function authority(int|User $user): Authority
{
    return app(EffectiveCapabilities::class)->for($user);
}

it('writes the catalogue once — 80 capabilities, the two built-in roles, every capability for OWNER only', function () {
    $again = app(CatalogueSync::class)->run();

    expect($again)->toBe(['capabilities' => 0, 'roles' => 0, 'role_capabilities' => 0])
        ->and(DB::table('capabilities')->pluck('authority_class', 'code')->all())->toEqual(array_map(fn (array $row): string => $row[0], CapabilityCatalogue::CAPABILITIES))
        ->and(DB::table('roles')->orderBy('code')->pluck('is_builtin', 'code')->all())->toBe(['ADMIN_OPERASIONAL' => true, 'OWNER' => true])
        ->and(DB::table('role_capabilities')->join('roles', 'roles.id', '=', 'role_id')->where('roles.code', 'OWNER')->count())->toBe(80)
        ->and(DB::table('role_capabilities')->join('roles', 'roles.id', '=', 'role_id')->where('roles.code', 'ADMIN_OPERASIONAL')->count())->toBe(0);
});

it('refuses a stored capability the catalogue does not list', function () {
    DB::table('capabilities')->insert(['code' => 'stock.teleport', 'authority_class' => 'ADM']);

    app(CatalogueSync::class)->run();
})->throws(RuntimeException::class, 'stock.teleport');

it('gives the Owner every capability and every company (RG-02, CS-01)', function () {
    $owner = Authorization::owner();

    expect(authority($owner)->capabilities)->toHaveCount(80)
        ->and(authority($owner)->scope->all)->toBeTrue();
});

it('gives a new Admin nothing until the Owner grants it, a company grant alone included (RG-04)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin();

    expect(authority($admin)->capabilities)->toBe([])
        ->and(authority($admin)->scope->isEmpty())->toBeTrue();

    Authorization::grantCompany($admin, $company->id);

    expect(authority($admin)->capabilities)->toBe([])
        ->and(authority($admin)->scope->companyIds)->toBe([$company->id]);
});

it('keeps a capability only while its prerequisites and, for a command, a company hold (RG-06; CS-05)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([], ['stock.view', 'finance.view', 'cost.view', 'profit.view', 'products.edit']);

    // Without a company: the pooled view only.
    expect(authority($admin)->capabilities)->toBe(['stock.view']);

    Authorization::grantCompany($admin, $company->id);

    expect(authority($admin)->capabilities)->toBe(['cost.view', 'finance.view', 'products.edit', 'profit.view', 'stock.view']);

    // Revoking a prerequisite silently disables its dependant until it is granted again.
    Authorization::revoke($admin, 'cost.view');

    expect(authority($admin)->capabilities)->toBe(['finance.view', 'products.edit', 'stock.view']);

    Authorization::grant($admin, 'cost.view');

    expect(authority($admin)->has('profit.view'))->toBeTrue();
});

it('ignores and security-logs an OWNER_ONLY capability outside the built-in OWNER role (RG-02; OD-16)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['audit.view', 'scope.all_companies']);
    $role = DB::table('roles')->insertGetId(['code' => 'CUSTOM_SUPERVISOR', 'is_builtin' => false]);
    DB::table('role_capabilities')->insert(['role_id' => $role, 'capability_id' => DB::table('capabilities')->where('code', 'projects.force_complete')->value('id')]);
    DB::table('user_roles')->where('user_id', $admin->id)->update(['role_id' => $role]);
    Authorization::forget();

    expect(authority($admin)->capabilities)->toBe([])
        ->and(authority($admin)->scope->companyIds)->toBe([$company->id])
        ->and(SecurityEvent::query()->where('event_kind', 'AUTHORIZATION_ANOMALY')->orderBy('reason_code')->get()->map(fn (SecurityEvent $event) => [$event->reason_code, $event->details])->all())
        ->toEqualCanonicalizing([
            ['OWNER_ONLY_IN_USER_GRANT', ['capability' => 'audit.view']],
            ['OWNER_ONLY_IN_USER_GRANT', ['capability' => 'scope.all_companies']],
            ['OWNER_ONLY_IN_ROLE', ['capability' => 'projects.force_complete']],
        ]);
});

it('gives a deactivated account nothing, an Owner included (AZ-03)', function () {
    $owner = Authorization::owner();
    DB::table('users')->where('id', $owner->id)->update(['is_active' => false, 'deactivated_at' => now()]);

    expect(authority($owner)->capabilities)->toBe([])
        ->and(authority($owner)->active)->toBeFalse();
});

it('reads the grants once per request, whatever the number of checks (AZ-03; PF-)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['projects.view', 'finance.view']);
    DB::enableQueryLog();
    DB::flushQueryLog();

    foreach (range(1, 100) as $check) {
        authority($admin)->allows('finance.view', $company->id);
    }

    expect(DB::getQueryLog())->toHaveCount(3);

    // Within the request the memo stands; the container's reset between requests and jobs drops it.
    DB::table('user_capability_grants')->where('user_id', $admin->id)->update(['revoked_at' => now()]);

    expect(authority($admin)->has('finance.view'))->toBeTrue();

    app()->forgetScopedInstances();

    expect(authority($admin)->has('finance.view'))->toBeFalse();
});
