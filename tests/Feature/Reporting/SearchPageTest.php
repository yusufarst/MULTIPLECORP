<?php

use App\Modules\ORG\Models\Company;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04, TL-06 (TASK-059; acceptance criteria 4, 5): ROUTE-009 `Reporting ShowSearch`, PG-009 *Cari* — every active
// account (S0), the page without a term or a result whatever the address carries (IA §11; DP-02), and the scan field's
// lookup offered only with ROUTE-282's `stock.view` (S1).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
});

it('shows PG-009 to every active account, scanning with stock.view only (ROUTE-009; S0; S1)', function () {
    $this->actingAs(Authorization::owner())->get('/cari')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/Cari')->where('can.scan', true));

    $admin = Authorization::admin([Company::factory()->create()->id]);
    Authorization::revoke($admin, 'stock.view');

    $this->actingAs($admin)->get('/cari')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/Cari')->where('can.scan', false));
    $this->actingAs($admin)->postJson('/gudang/pindai', ['kode' => 'KRT-A4'])->assertForbidden();
});

it('refuses a guest (ROUTE-009)', function () {
    $this->get('/cari')->assertRedirect('/masuk');
});

it('keeps a term out of the page whatever the address carries (DP-02; H7-10)', function () {
    $this->actingAs(Authorization::owner())->get('/cari?q=KRT-A4&cari=KRT-A4')->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Reporting/Cari')->missing('q')->missing('cari')->missing('term')->missing('results');
            // Only the address the request itself carried names the term; no prop of the page does.
            expect(json_encode($page->toArray()['props']))->not->toContain('KRT-A4');
        });
});
