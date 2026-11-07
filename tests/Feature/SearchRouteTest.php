<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-016; acceptance criteria 2–4): ROUTE-281 `Reporting Search` — every active account, the term in the
// request body and never in the address, at most 100 characters, a JSON answer outside any page state, and
// WS-12's search limiter answering the 61st search in a minute with MSG-20 (TM-43).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->viewer = Authorization::admin([Company::factory()->create()->id]);
});

it('answers an active account with JSON results for a term in the body (INT-005)', function () {
    $paper = Product::factory()->create(['sku' => 'KRT-A4', 'name' => 'Kertas A4']);

    $this->actingAs($this->viewer)
        ->postJson('/cari', ['q' => 'KRT-A4'])
        ->assertOk()
        ->assertJsonPath('exact.0.id', $paper->public_id)
        ->assertHeaderMissing('X-Inertia');
});

it('refuses a guest, a term over 100 characters and a type outside the whitelist (WS-03; PF-23)', function () {
    $this->postJson('/cari', ['q' => 'kertas'])->assertUnauthorized();

    $this->actingAs($this->viewer)->postJson('/cari', ['q' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->actingAs($this->viewer)->postJson('/cari', ['q' => 'kertas', 'jenis' => ['users']])->assertUnprocessable();
    $this->actingAs($this->viewer)->postJson('/cari', ['q' => str_repeat('a', 100)])->assertOk();
});

it('takes no term from the address (DP-02)', function () {
    // ROUTE-281 is a POST; a GET of `/cari` is PG-009's view (TASK-059), which reads no term and answers none.
    $hot = (string) tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    $this->actingAs($this->viewer)->get('/cari?q=kertas')->assertOk()->assertInertia(function (AssertableInertia $page) {
        $page->component('Reporting/Cari')->missing('exact')->missing('groups');
        expect(json_encode($page->toArray()['props']))->not->toContain('kertas');
    });
    @unlink($hot);
});

it('answers the 61st search in a minute with MSG-20, per account (WS-12; TM-43; criterion 4)', function () {
    RateLimiter::clear('search:'.$this->viewer->id);

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($this->viewer)->postJson('/cari', ['q' => 'kertas'])->assertOk();
    }

    $this->actingAs($this->viewer)
        ->postJson('/cari', ['q' => 'kertas'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.');

    $other = Authorization::admin([Company::factory()->create()->id]);
    $this->actingAs($other)->postJson('/cari', ['q' => 'kertas'])->assertOk();
});
