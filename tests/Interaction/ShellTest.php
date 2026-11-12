<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SessionDeadline;
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

it('gives every authenticated response the session\'s opaque account key outside the page data, distinct for two accounts whose hints match, none for a guest (UXH-02; WS-07; TASK-093)', function () {
    $first = Authorization::admin([$this->b->id], ['projects.view']);
    $second = Authorization::admin([$this->b->id], ['projects.view']);
    $second->forceFill(['name' => $first->name])->save();
    expect(shellHints($second->fresh()))->toBe(shellHints($first));

    $keyOf = fn (object $user): ?string => $this->actingAs($user)->get('/pekerjaan')->headers->get(SessionDeadline::ACCOUNT_HEADER);
    $one = $keyOf($first);
    $other = $keyOf($second->fresh());

    expect($one)->toMatch('/^[0-9a-f]{32}$/')
        ->and($other)->toMatch('/^[0-9a-f]{32}$/')
        ->and($one)->not->toBe($other)
        ->and($keyOf($first))->toBe($one)
        ->and($one)->not->toContain((string) $first->id)
        ->and(shellHints($first))->not->toHaveKey('account');

    // The first load carries it in the root view, as it does the deadline.
    expect($this->actingAs($first)->get('/pekerjaan')->getContent())->toContain('<meta name="session-account" content="'.$one.'">');

    auth()->logout();
    expect($this->get('/masuk')->headers->get(SessionDeadline::ACCOUNT_HEADER))->toBeNull();
});

it('redirects a signed-in session\'s sign-in request without checking its password, the answer the dialog must never read as a success (IP-11; TASK-093)', function () {
    $admin = Authorization::admin([$this->b->id], ['projects.view']);

    $this->actingAs($admin)
        ->postJson('/masuk', ['email' => $admin->email, 'password' => 'bukan-kata-sandinya'])
        ->assertRedirect();
});

it('confirms a signed-in session\'s dialog only for that account\'s own e-mail and password — another account\'s correct credentials refused and recorded as a wrong password is (AU-15; UXH-02; TASK-093)', function () {
    $admin = Authorization::admin([$this->b->id], ['projects.view']);
    $other = Authorization::admin([$this->b->id], ['projects.view']);
    $other->forceFill(['name' => $admin->name])->save();
    $failed = fn (): int => DB::table('security_events')->where('event_kind', 'PASSWORD_CONFIRMATION_FAILED')->count();

    $this->actingAs($admin)
        ->postJson('/konfirmasi-kata-sandi', ['email' => $other->email, 'password' => 'kata-sandi-contoh'])
        ->assertUnprocessable()->assertJsonMissingPath('confirmed')->assertJsonPath('message', __('messages.msg_19'));
    $this->postJson('/konfirmasi-kata-sandi', ['email' => $admin->email, 'password' => 'bukan-kata-sandinya'])
        ->assertUnprocessable()->assertJsonMissingPath('confirmed');
    expect($failed())->toBe(2)
        ->and(session()->has(RequireStepUp::CONFIRMED_AT))->toBeFalse();

    $this->postJson('/konfirmasi-kata-sandi', ['email' => '  '.mb_strtoupper($admin->email).' ', 'password' => 'kata-sandi-contoh'])
        ->assertOk()->assertExactJson(['confirmed' => true]);
});
