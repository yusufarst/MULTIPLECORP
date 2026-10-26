<?php

use App\Http\BackgroundRequest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SessionDeadline;
use App\Http\Responses\SystemAnswer;
use App\Modules\IAM\Queries\ScopedRecords;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-016; acceptance criteria 7–9): the shell's answers outside a page's own (UXH-11) — a 401 to an
// unauthenticated Inertia or JSON request, never a redirect (IP-11); the 419 of an expired session's token the
// same; the step-up's 423 (IP-10); Inertia's own 409 on a GET only; PG-005 answering an unknown, an out-of-scope
// and an Owner-only address alike, a record in scope without the capability as forbidden, and an error with its
// reference code and nothing else (IP-21; AZ-11; WS-10) —; a background request that never writes the session
// (RV-06; RB-05) and the absolute deadline in a header outside the page data (AU-08).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    config(['inertia.testing.ensure_pages_exist' => false]);

    Authorization::catalogue();
    [$this->a, $this->b] = [Company::factory()->create(), Company::factory()->create()];
    $this->admin = Authorization::admin([$this->a->id], ['projects.view']);

    Route::middleware(['web', 'auth', 'capability:finance.view'])->get('/__test/keuangan', fn () => 'keuangan');
    Route::middleware(['web', 'auth', 'capability:audit.view'])->get('/__test/aktivitas', fn () => 'aktivitas');
    Route::middleware(['web', 'auth', 'capability:projects.view'])->get('/__test/perusahaan/{id}', function (string $id) {
        return app(ScopedRecords::class)->find(Company::query(), $id, app(EffectiveCapabilities::class)->for(request()->user()), 'id')->public_id;
    });
    Route::middleware(['web', 'auth', 'access:s0', 'step-up'])->post('/__test/akses', fn () => response()->json(['outcome' => 'COMMITTED']));
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/rusak', fn () => throw new RuntimeException('a hidden detail'));
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/halaman', fn () => 'halaman');
});

afterEach(fn () => @unlink($this->hotFile));

/** An Inertia visit's headers, its asset version the current one, so only a stale version meets Inertia's 409. */
function inertiaVisit(): array
{
    return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];
}

it('answers an unauthenticated Inertia or JSON request 401 with MSG-11, never a redirect (UXH-11; IP-11; criterion 7)', function () {
    $this->get('/__test/halaman', inertiaVisit())->assertUnauthorized()->assertJsonPath('message', __('messages.msg_11'))->assertHeaderMissing('Location');
    $this->getJson('/__test/halaman')->assertUnauthorized();
    $this->postJson('/cari', ['q' => 'kertas'])->assertUnauthorized();
});

it('sends a guest\'s document request to sign-in', function () {
    $this->get('/__test/halaman')->assertRedirect('/masuk');
    $this->get('/tidak-ada')->assertRedirect('/masuk');

    // The sign-in address is PG-001's (TASK-017), never the system page.
    $this->get('/masuk')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SignIn', false));
});

it('answers an expired session\'s token 419 in place, for the shell to sign in again (IP-11)', function () {
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->actingAs($this->admin)->post('/__test/akses', [], inertiaVisit())->assertStatus(419)->assertJsonPath('message', __('messages.msg_11'));
});

it('answers a command needing the step-up 423 with MSG-27 until the password is confirmed (IP-10; AU-15; criterion 8)', function () {
    $this->actingAs($this->admin)->post('/__test/akses', [], inertiaVisit())->assertStatus(423)->assertJsonPath('message', __('messages.msg_27'))->assertHeaderMissing('Location');

    $this->actingAs($this->admin)
        ->withSession([RequireStepUp::CONFIRMED_AT => time() - 600])
        ->post('/__test/akses', [], inertiaVisit())
        ->assertOk();

    $this->actingAs($this->admin)
        ->withSession([RequireStepUp::CONFIRMED_AT => time() - 901])
        ->post('/__test/akses', [], inertiaVisit())
        ->assertStatus(423);
});

it('keeps Inertia\'s asset-version 409 to a GET (CONCURRENCY_IDEMPOTENCY §1)', function () {
    $stale = [...inertiaVisit(), 'X-Inertia-Version' => 'stale'];

    $this->actingAs($this->admin)->get('/__test/halaman', $stale)->assertStatus(409)->assertHeader('X-Inertia-Location');
    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post('/__test/akses', [], $stale)->assertOk();
});

it('answers an unknown, an out-of-scope and an Owner-only address with the same not-found page (IP-21; AZ-11; criterion 9)', function () {
    $answers = [];

    foreach (['/tidak-ada/sama-sekali', "/__test/perusahaan/{$this->b->public_id}", '/__test/perusahaan/01a00000-0000-7000-8000-000000000000', '/__test/aktivitas'] as $address) {
        $response = $this->actingAs($this->admin)->get($address, inertiaVisit())->assertNotFound();
        $page = $response->json();
        $answers[] = [$page['component'], $page['props']['kind'], array_keys($page['props'])];
    }

    expect(array_unique($answers, SORT_REGULAR))->toHaveCount(1)
        ->and($answers[0])->toBe([SystemAnswer::PAGE, 'not_found', ['errors', 'locale', 'auth', 'kind', 'reference']]);
});

it('renders PG-005 in a document request too, with the shell\'s shared props', function () {
    $this->actingAs($this->admin)
        ->get('/tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component(SystemAnswer::PAGE)->where('kind', 'not_found')->where('auth.name', $this->admin->name)->has('reference'));
});

it('answers a record\'s address without the capability as forbidden, with MSG-10\'s page (IP-21)', function () {
    $this->actingAs($this->admin)->get('/__test/keuangan', inertiaVisit())->assertForbidden()->assertJsonPath('props.kind', 'forbidden');
});

it('answers an error with its reference code and no technical detail (WS-10)', function () {
    config(['app.debug' => false]);

    $response = $this->actingAs($this->admin)->get('/__test/rusak', inertiaVisit())->assertServerError();

    expect($response->json('props.kind'))->toBe('error')
        ->and($response->json('props.reference'))->toBeString()->not->toBe('')
        ->and($response->getContent())->not->toContain('a hidden detail')->not->toContain('RuntimeException');
});

it('writes no session for a marked background GET, and every other request writes it (RV-06; RB-05; HO-06)', function () {
    $cookie = config('session.cookie');

    $this->actingAs($this->admin)->get('/__test/halaman')->assertCookie($cookie);
    $this->actingAs($this->admin)->get('/__test/halaman', [BackgroundRequest::HEADER => '1'])->assertCookieMissing($cookie);
    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post('/__test/akses', [], [BackgroundRequest::HEADER => '1'])->assertCookie($cookie);
});

it('sends the absolute deadline in a header outside the page data (AU-08; UXH-11)', function () {
    // An hour ago: a fixed instant would pass AU-07's 12 hours as the calendar moves on.
    $signedIn = time() - 3600;

    $response = $this->actingAs($this->admin)->withSession([SessionDeadline::SIGNED_IN_AT => $signedIn])->get('/tidak-ada', inertiaVisit());

    expect($response->headers->get(SessionDeadline::HEADER))->toBe(gmdate('Y-m-d\TH:i:s\Z', $signedIn + 12 * 3600))
        ->and(json_encode($response->json('props')))->not->toContain(gmdate('Y-m-d\TH:i:s', $signedIn + 12 * 3600))
        ->and(json_encode($response->json('props')))->not->toContain((string) ($signedIn + 12 * 3600));
});
