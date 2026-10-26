<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-06 (TASK-023; PERMISSIONS_MATRIX PJ-17; SECURITY AU-13; SM-02; criterion 1): every route of PG-065 is SELF — it
// takes no account identifier, so it reaches the signed-in account's own row and no other —, and a guest is sent to
// sign-in, changing nothing.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
});

afterEach(fn () => @unlink($this->hotFile));

it('declares every PG-065 route SELF, with no account identifier in its address', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'akun'));

    expect($routes)->toHaveCount(9)
        ->and($routes->every(fn ($route) => in_array('access:self', $route->gatherMiddleware(), true) && $route->parameterNames() === []))->toBeTrue();
});

it('changes only the signed-in account\'s own row, another account untouched (SELF; PJ-17)', function () {
    $mine = TwoFactor::enrol(Authorization::admin());
    $theirs = TwoFactor::enrol(Authorization::admin());
    $before = (array) DB::table('users')->where('id', $theirs->id)->first();

    $this->actingAs($mine)->withSession([RequireStepUp::CONFIRMED_AT => time()])->put('/akun/bahasa', ['locale' => 'en', 'user_id' => $theirs->id, 'id' => $theirs->id]);
    $this->actingAs($mine)->post('/akun/kode-pemulihan/baru', ['code' => TwoFactor::code(), 'user_id' => $theirs->id]);
    $this->actingAs($mine)->delete('/akun/verifikasi-dua-langkah', ['code' => TwoFactor::code(offset: 1), 'user_id' => $theirs->id]);

    expect((array) DB::table('users')->where('id', $theirs->id)->first())->toBe($before)
        ->and(User::query()->findOrFail($mine->id)->locale)->toBe('en');
});

it('sends a guest to sign-in from every PG-065 route, changing nothing', function (string $method, string $url) {
    $this->json($method, $url, ['locale' => 'en', 'code' => '123456'])->assertUnauthorized();
    $this->get('/akun')->assertRedirect('/masuk');
})->with([
    ['PUT', '/akun/kata-sandi'], ['POST', '/akun/verifikasi-dua-langkah'], ['DELETE', '/akun/verifikasi-dua-langkah'], ['POST', '/akun/kode-pemulihan'],
    ['POST', '/akun/kode-pemulihan/baru'], ['POST', '/akun/google'], ['DELETE', '/akun/google'], ['PUT', '/akun/bahasa'],
]);
