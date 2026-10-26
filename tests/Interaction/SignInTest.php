<?php

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Support\Command\CommandId;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Commands\TestCommands;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-017): INT-101, INT-108 and INT-109 on the server's side, and INT-010 with the real sign-in — after the
// session ended, signing in again in place lets the pending command be retried with its `command_id`: the same
// account gets what already ran, never twice; another account gets a CONFLICT that names nothing (CI-01–CI-09;
// HO-02; UXH-11; architecture challenge 19a; acceptance criterion 7, with TASK-013 and TASK-016).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);

    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $this->product, 'on_hand_qty' => 5]);
    Route::middleware(['web', 'auth', 'capability:stock.dispatch'])->post('/__test/dispatch', function (Request $request) {
        $outcome = TestCommands::move(CommandId::fromClient((string) $request->input('command_id')), (int) $request->user()?->getAuthIdentifier(), $this->company->id, 'stock.dispatch', [
            ['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]],
        ]);

        return response()->json(['outcome' => $outcome->kind, 'code' => $outcome->code, 'replayed' => $outcome->replayed]);
    })->withoutMiddleware(ValidateCsrfToken::class);

    $this->admin = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $this->admin->forceFill(['email' => 'dimas@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')])->save();
    $this->other = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $this->other->forceFill(['email' => 'bayu@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')])->save();
});

afterEach(fn () => @unlink($this->hotFile));

function signInAgain(string $email): void
{
    auth()->forgetGuards();
    test()->postJson('/masuk', ['email' => $email, 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
}

it('replays the pending command to the same account after it signs in again in place (INT-010; criterion 7)', function () {
    $id = (string) Str::uuid7();
    signInAgain('dimas@contoh.test');
    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertJson(['outcome' => 'COMMITTED', 'replayed' => false]);

    // The session ends; the retry meets 401 and the dialog signs in again (UXH-11).
    $this->postJson('/keluar')->assertNoContent();
    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertUnauthorized()->assertJsonPath('message', __('messages.msg_11'));
    signInAgain('dimas@contoh.test');

    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertJson(['outcome' => 'COMMITTED', 'replayed' => true]);
    expect((string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty'))->toBe('4.000');
});

it('answers another account signing in on that page with a CONFLICT that names nothing (INT-010; criterion 7)', function () {
    $id = (string) Str::uuid7();
    signInAgain('dimas@contoh.test');
    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertOk();
    $this->postJson('/keluar');
    signInAgain('bayu@contoh.test');

    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertExactJson(['outcome' => 'CONFLICT', 'code' => 'CI-05', 'replayed' => true]);
    expect((string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty'))->toBe('4.000');
});

it('shows PG-001 only without a session and answers ROUTE-101 by its one generic answer (INT-101)', function () {
    $this->get('/masuk')->assertOk();
    $this->postJson('/masuk', ['email' => 'dimas@contoh.test', 'password' => 'salah'])->assertStatus(422)->assertJsonPath('message', __('messages.msg_19'));
    $this->postJson('/masuk', ['email' => '', 'password' => ''])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

    signInAgain('dimas@contoh.test');
    $this->get('/masuk')->assertRedirect('/');
});

it('logs out to PG-001 and confirms the password only with a session (INT-108, INT-109)', function () {
    $this->postJson('/keluar')->assertUnauthorized();
    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertUnauthorized();

    signInAgain('dimas@contoh.test');
    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertOk()->assertJson(['confirmed' => true]);
    $this->post('/keluar')->assertRedirect(url('/masuk'));
});

it('lets no account stand in for another — no impersonation route or token (AU-17)', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => preg_match('/impersonat|login-as|masuk-sebagai|api\/token|register|daftar/', $uri) === 1);

    expect($uris)->toBeEmpty()
        ->and(User::query()->count())->toBe(2);
});
