<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\SystemAnswer;
use App\Modules\IAM\Services\CommandAuthorization;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-016): the shell's interactions on the server's side — INT-003 and INT-004's hints, the account's
// name, capabilities and companies and nothing else (WS-07); INT-007's company key never widening the scope;
// INT-010's session ended answered 401 in place; and a command refused at its in-command re-authorization after
// its company grant was revoked, answered on the open page as IP-21 shows it (SQ-22; architecture challenge 8a;
// acceptance criterion 10). The entries' own rules are the frontend's (`tests/Frontend/shell.test.ts`).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    config(['inertia.testing.ensure_pages_exist' => false]);

    Authorization::catalogue();
    [$this->a, $this->b] = [Company::factory()->create(['code' => 'ARJ']), Company::factory()->create(['code' => 'BTN'])];
});

afterEach(fn () => @unlink($this->hotFile));

function shellHints(mixed $user): array
{
    $request = Request::create('/pekerjaan');
    $request->setUserResolver(fn () => $user);

    return app(HandleInertiaRequests::class)->share($request)['auth']();
}

it('shares only the name, capabilities and companies in scope as the shell\'s hints (INT-003, INT-004; WS-07)', function () {
    $admin = Authorization::admin([$this->b->id], ['projects.view']);
    $hints = shellHints($admin);

    expect(array_keys($hints))->toBe(['name', 'capabilities', 'companies'])
        ->and($hints['name'])->toBe($admin->name)
        ->and($hints['capabilities'])->toBe(['projects.view'])
        ->and(array_column($hints['companies'], 'code'))->toBe(['BTN'])
        ->and(array_column(shellHints(Authorization::owner())['companies'], 'code'))->toBe(['ARJ', 'BTN'])
        ->and(shellHints(null))->toBe(['name' => null, 'capabilities' => [], 'companies' => []]);
});

it('answers a request after the session ended with 401 and MSG-11, in place (INT-010)', function () {
    Route::middleware(['web', 'auth', 'access:s0'])->post('/__test/perintah', fn () => 'done');

    $this->post('/__test/perintah', [], ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
        ->assertUnauthorized()
        ->assertJsonPath('message', __('messages.msg_11'));
});

it('shows a command refused after its company grant was revoked as IP-21\'s not-found page (SQ-22; criterion 10)', function () {
    $admin = Authorization::admin([$this->a->id, $this->b->id], ['projects.view', 'projects.manage']);
    Route::middleware(['web', 'auth', 'capability:projects.manage'])->post('/__test/proyek', function () {
        return DB::transaction(function () {
            app(CommandAuthorization::class)->reauthorize((int) request()->user()?->getAuthIdentifier(), 'projects.manage', $this->a->id);

            return response()->json(['outcome' => 'COMMITTED']);
        });
    });
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];

    $this->actingAs($admin)->post('/__test/proyek', [], $headers)->assertOk();

    DB::table('user_company_grants')->where('user_id', $admin->id)->where('company_id', $this->a->id)->update(['revoked_at' => now()]);
    Authorization::forget();

    // The grant of the command's company alone revoked: the route's capability still takes effect through the other
    // company, so the refusal is the command's own re-authorization inside its transaction (RV-01; SQ-22), shown on
    // the open page as IP-21's not-found page — the company now outside the scope —, nothing committed.
    $response = $this->actingAs($admin)->post('/__test/proyek', [], $headers);

    expect($response->status())->toBe(404)
        ->and($response->json('component'))->toBe(SystemAnswer::PAGE)
        ->and($response->json('props.kind'))->toBe('not_found')
        ->and($response->json('outcome'))->toBeNull();
});
