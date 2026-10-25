<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use App\Support\Command\CommandId;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Commands\TestCommands;
use Tests\Support\RefreshDatabase;

// TL-04 and TL-06 (TASK-013): replay and outcomes through a sample command route (CI-01–CI-06; HO-02; IP-01–
// IP-04): a second press, or a retry after a lost answer or a sign-in in place, returns what already ran, never
// twice; replay re-authorizes the current actor, so a revoked grant answers the denial a fresh command gets,
// never the recorded outcome; a malformed identity is a validation answer.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $this->product, 'on_hand_qty' => 5]);

    Route::middleware(['web', 'auth', 'capability:stock.dispatch'])->post('/__test/dispatch', function (Request $request) {
        $commandId = CommandId::fromClient((string) $request->input('command_id'));
        $outcome = TestCommands::move($commandId, (int) $request->user()?->getAuthIdentifier(), $this->company->id, 'stock.dispatch', [
            ['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -(int) $request->input('quantity', 1)]],
        ]);

        return response()->json(['outcome' => $outcome->kind, 'code' => $outcome->code, 'replayed' => $outcome->replayed]);
    })->withoutMiddleware(ValidateCsrfToken::class);

    $this->admin = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $this->onHand = fn (): string => (string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty');
});

it('returns what already ran to a second press and to a retry after a lost answer, never twice (CI-05; 15c, 19a)', function () {
    $id = (string) Str::uuid7();

    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id])->assertOk()->assertJson(['outcome' => 'COMMITTED', 'replayed' => false]);
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id])->assertOk()->assertJson(['outcome' => 'COMMITTED', 'replayed' => true]);

    expect(($this->onHand)())->toBe('4.000');
});

it('replays to the same account after it signs in again in place (CI-05; 401 and 419)', function () {
    $id = (string) Str::uuid7();
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id]);

    auth()->logout();
    $this->postJson('/__test/dispatch', ['command_id' => $id])->assertUnauthorized();
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id])->assertJson(['outcome' => 'COMMITTED', 'replayed' => true]);

    expect(($this->onHand)())->toBe('4.000');
});

it('re-authorizes a replay: a revoked grant answers the denial, not the recorded outcome (CI-05; DP-14, DP-16; TL-06)', function () {
    $id = (string) Str::uuid7();
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id])->assertOk();

    DB::table('user_company_grants')->where('user_id', $this->admin->id)->update(['revoked_at' => now()]);
    Authorization::forget();

    // The route's own gate refuses first now that the account holds no company (CS-05); the replay never answers.
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id])->assertForbidden();
});

it('answers another account replaying an identity with a CONFLICT that names nothing (DP-16; TL-06)', function () {
    $id = (string) Str::uuid7();
    $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => $id]);
    $other = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);

    $this->actingAs($other)->postJson('/__test/dispatch', ['command_id' => $id])->assertExactJson(['outcome' => 'CONFLICT', 'code' => 'CI-05', 'replayed' => true]);
});

it('refuses a malformed or driven identity from a request (CI-01)', function () {
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->admin)->postJson('/__test/dispatch', ['command_id' => CommandId::driven('job', 'x:1:1')]))
        ->toThrow(InvalidArgumentException::class);
});
