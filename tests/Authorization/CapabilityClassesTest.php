<?php

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Support\Audit\AuthorityClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-011): SM-04 by capability class (PERMISSIONS_MATRIX §4–§5, §9): the Owner holds every
// capability; an Admin holds an ADM or ADM_PLUS capability only by its own grant with its prerequisites and a
// company in scope, one ADM_PLUS grant conferring no other (RG-08); no OWNER_ONLY capability takes effect for
// an Admin, granted or not (OD-02, RG-02); and SM-05's reconciliation finds such a grant (OD-16).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
});

/**
 * The capability and its prerequisites, transitively.
 *
 * @return list<string>
 */
function withPrerequisites(string $code): array
{
    return array_values(array_unique([...array_merge(...array_map(withPrerequisites(...), CapabilityCatalogue::prerequisites($code))), $code]));
}

it('enforces each capability by its class', function (string $code) {
    $owner = Authorization::owner();
    $admin = Authorization::admin([$this->company->id], withPrerequisites($code));
    $ungranted = Authorization::admin([$this->company->id], array_values(array_diff(withPrerequisites($code), [$code])));

    expect(Gate::forUser($owner)->allows($code, $this->company->id))->toBeTrue()
        ->and(Gate::forUser($ungranted)->allows($code, $this->company->id))->toBeFalse()
        ->and(Gate::forUser($admin)->allows($code, $this->company->id))->toBe(! CapabilityCatalogue::isOwnerOnly($code))
        ->and(Gate::forUser($admin)->allows($code, Company::factory()->create()->id))->toBeFalse();
})->with(CapabilityCatalogue::codes());

it('confers no ADM_PLUS capability through another (RG-08)', function () {
    $admin = Authorization::admin([$this->company->id], ['stock.view', 'stock.reverse']);
    $others = array_filter(CapabilityCatalogue::codes(), fn (string $code): bool => $code !== 'stock.reverse' && CapabilityCatalogue::classOf($code) === AuthorityClass::AdmPlus);

    foreach ($others as $code) {
        expect(Gate::forUser($admin)->allows($code))->toBeFalse();
    }

    expect(Gate::forUser($admin)->allows('stock.reverse'))->toBeTrue();
});

it('denies every gate to a guest and to an account without a role (default deny)', function () {
    $nobody = User::factory()->create();

    foreach (CapabilityCatalogue::codes() as $code) {
        expect(Gate::allows($code))->toBeFalse()
            ->and(Gate::forUser($nobody)->allows($code))->toBeFalse();
    }
});

it('finds an OWNER_ONLY capability outside the OWNER role in the OD-16 reconciliation (SM-05)', function () {
    $reconcile = fn (): array => DB::select(<<<'SQL'
        select 'user grant' as source, user_capability_grants.user_id as subject
        from user_capability_grants join capabilities on capabilities.id = user_capability_grants.capability_id
        where capabilities.authority_class = 'OWNER_ONLY' and user_capability_grants.revoked_at is null
        union all
        select 'role', role_capabilities.role_id
        from role_capabilities join capabilities on capabilities.id = role_capabilities.capability_id
        join roles on roles.id = role_capabilities.role_id
        where capabilities.authority_class = 'OWNER_ONLY' and not (roles.is_builtin and roles.code = 'OWNER')
        SQL);

    expect($reconcile())->toBe([]);

    $admin = Authorization::admin([$this->company->id], ['projects.force_complete']);

    expect($reconcile())->toHaveCount(1)
        ->and(Gate::forUser($admin)->allows('projects.force_complete'))->toBeFalse();
});
