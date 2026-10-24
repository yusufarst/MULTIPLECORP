<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\Request;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-011): IG-01's presence rule on a sample control — the page receives the account's own
// effective capabilities (SECURITY WS-07), so a control is present with its capability and absent without
// it, never disabled; the server still decides every request (AZ-01).

uses(RefreshDatabase::class);

beforeEach(fn () => Authorization::catalogue());

/** The capabilities shared with a page for the given account. */
function sharedCapabilities(?User $user): array
{
    $request = Request::create('/beranda');
    $request->setUserResolver(fn () => $user);

    return app(HandleInertiaRequests::class)->share($request)['auth']()['capabilities'];
}

it('shares the capability that makes a control present, and not one the account lacks', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['projects.view', 'projects.complete']);

    expect(sharedCapabilities($admin))->toBe(['projects.complete', 'projects.view'])
        ->and(sharedCapabilities($admin))->not->toContain('projects.force_complete')
        ->and(sharedCapabilities(Authorization::owner()))->toContain('projects.force_complete')
        ->and(sharedCapabilities(null))->toBe([]);
});
