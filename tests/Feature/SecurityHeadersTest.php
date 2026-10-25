<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;

beforeEach(function () {
    // A Vite dev-server hot file, so that the root view emits its script tags without a build.
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);

    config(['inertia.testing.ensure_pages_exist' => false]);

    Route::middleware('web')->get('/__test/page', fn () => Inertia::render('Test'));
    Route::middleware('web')->get('/__test/hilang', fn () => abort(404));
});

afterEach(function () {
    @unlink($this->hotFile);
});

function nonceOf(string $html): string
{
    expect(preg_match('/<meta property="csp-nonce" nonce="([^"]+)">/', $html, $match))->toBe(1);

    return $match[1];
}

it('sends the WS-08 policy with the nonce of the page', function () {
    $response = $this->get('/__test/page')->assertOk();

    $html = $response->getContent();
    $nonce = nonceOf($html);

    preg_match_all('/<script type="module"[^>]*>/', $html, $scripts);

    expect($nonce)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($response->headers->get('Content-Security-Policy'))->toBe(expectedContentSecurityPolicy($nonce))
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($scripts[0])->not->toBeEmpty()
        ->each->toContain("nonce=\"{$nonce}\"");
});

it('uses a fresh nonce for every response', function () {
    $first = nonceOf($this->get('/__test/page')->getContent());
    $second = nonceOf($this->get('/__test/page')->getContent());

    expect($first)->not->toBe($second);
});

it('sends the policy on Inertia responses', function () {
    $version = (new HandleInertiaRequests)->version(Request::create('/'));

    $response = $this->get('/__test/page', array_filter([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ]))->assertOk()->assertHeader('X-Inertia', 'true');

    expect($response->headers->get('Content-Security-Policy'))->toStartWith("default-src 'self'; script-src 'self' 'nonce-")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sends the policy and no-store on error pages', function () {
    // A guest's unknown address goes to sign-in (TASK-016); an error answered inside a route is the error page.
    $response = $this->get('/__test/hilang')->assertNotFound();

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('marks responses no-store', function () {
    expect($this->get('/__test/page')->headers->get('Cache-Control'))->toContain('no-store');
});
