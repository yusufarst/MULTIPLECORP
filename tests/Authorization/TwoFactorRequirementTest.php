<?php

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-018; SECURITY AU-18 K1; H8-17): which accounts must use TOTP, on the resolver's own inputs — the OWNER
// role whatever its grants; an effective ADM_PLUS capability; `finance.view`, `cost.view`, `profit.view` or
// `evidence.view` —, a grant counting only while its capability is effective, so one disabled by a revoked
// prerequisite counts again only once the prerequisite is granted again; an inactive account is never high-risk.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->resolver = app(EffectiveCapabilities::class);
});

function requiresTotp(User $user): bool
{
    Authorization::forget();

    return app(EffectiveCapabilities::class)->requiresTotp($user->id);
}

it('requires TOTP of an Owner, whatever its grants (K1 criterion 1)', function () {
    expect(requiresTotp(Authorization::owner()))->toBeTrue();
});

it('requires TOTP of an effective ADM_PLUS capability, and not once its prerequisite is revoked (K1 criterion 2; RG-06)', function () {
    $admin = Authorization::admin([$this->company->id]);
    expect(requiresTotp($admin))->toBeFalse();

    Authorization::grant($admin, 'stock.view');
    Authorization::grant($admin, 'stock.reverse');
    expect(requiresTotp($admin))->toBeTrue();

    Authorization::revoke($admin, 'stock.view');
    expect(requiresTotp($admin))->toBeFalse();

    Authorization::grant($admin, 'stock.view');
    expect(requiresTotp($admin))->toBeTrue();
});

it('requires TOTP of each of the four views of money, cost, profit and evidence (K1 criterion 3)', function (array $grants) {
    $admin = Authorization::admin([$this->company->id]);

    foreach ($grants as $capability) {
        Authorization::grant($admin, $capability);
    }

    expect(requiresTotp($admin))->toBeTrue();
})->with([
    'finance.view' => [['finance.view']],
    'cost.view' => [['cost.view']],
    'profit.view' => [['finance.view', 'cost.view', 'profit.view']],
    'evidence.view' => [['evidence.view']],
]);

it('counts a view that needs a company only with a company in scope, and never an inactive account (K1; CS-05)', function () {
    $admin = Authorization::admin();
    Authorization::grant($admin, 'finance.view');
    expect(requiresTotp($admin))->toBeFalse();

    Authorization::grantCompany($admin, $this->company->id);
    expect(requiresTotp($admin))->toBeTrue();

    $admin->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
    expect(requiresTotp($admin))->toBeFalse();
});
