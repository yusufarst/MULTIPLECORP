<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-06, TL-14 (TASK-046; AZ-01; RG-06; SM-01; SM-04; SM-25; CS-03; criterion 10): ROUTE-032 with any of its commands' capabilities,
// ROUTE-205 with `dropship.confirm` (ADM), ROUTE-206 with `fulfillment.correct` and ROUTE-207 with `sales_return.record` (ADM_PLUS),
// each control absent without its capability; the cost only with `cost.view`; another company's project and confirmation unknown,
// each command writing nothing.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->owner = DropshipWorld::actor($this->company);
    $this->w = DropshipWorld::make($this->world);
    expect(DropshipWorld::confirm($this->owner->id, $this->w, '3', 'SJ-1'))->toBe('COMMITTED');
    $this->confirmation = DropshipWorld::last();
    $this->page = "/proyek/{$this->w['project']->publicId}/kirim-langsung";
    $proof = (string) DB::table('evidence_documents')->where('id', DropshipWorld::proof($this->company->id))->value('public_id');
    $this->confirm = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'pembelian' => $this->w['purchase']->public_id, 'referensi' => 'SJ-2', 'bukti' => $proof,
        'baris' => [['baris' => RowHandle::of('purchase_lines', $this->w['purchase_line']), 'jumlah' => '1']]];
    $this->correction = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Salah catat', 'tujuan' => 'PEMASOK',
        'baris' => [['baris' => RowHandle::of('dropship_confirmation_lines', $this->confirmation->lines[0]), 'jumlah' => '1']]];
});

afterEach(fn () => @unlink($this->hotFile));

it('refuses PG-032 and every command to an account holding none of their capabilities (AZ-01; SM-04)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'cost.view']);

    $this->actingAs($viewer)->get($this->page)->assertForbidden();
    $this->actingAs($viewer)->post($this->page, $this->confirm)->assertForbidden();
    $this->actingAs($viewer)->post("{$this->page}/{$this->confirmation->public_id}/balikkan", $this->correction)->assertForbidden();
    $this->actingAs($viewer)->post("{$this->page}/{$this->confirmation->public_id}/retur", $this->correction)->assertForbidden();

    expect(DB::table('dropship_confirmations')->count())->toBe(1)->and(DB::table('stock_restorations')->count())->toBe(0);
});

it('shows each control only with its capability: the confirmation with dropship.confirm, the reversal with fulfillment.correct, a return with sales_return.record (RG-06; criterion 10)', function () {
    $confirmer = Authorization::admin([$this->company->id], ['projects.view', 'dropship.confirm', 'evidence.view']);
    $corrector = Authorization::admin([$this->company->id], ['projects.view', 'fulfillment.correct']);
    $returner = Authorization::admin([$this->company->id], ['projects.view', 'stock.view', 'sales_return.record']);

    $this->actingAs($confirmer)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.confirm', true)->where('page.can.reverse', false)->where('page.can.return', false));
    $this->actingAs($corrector)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.confirm', false)->where('page.can.reverse', true)->where('page.can.return', false));
    $this->actingAs($returner)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.confirm', false)->where('page.can.reverse', false)->where('page.can.return', true));

    $this->actingAs($confirmer)->post("{$this->page}/{$this->confirmation->public_id}/balikkan", $this->correction)->assertForbidden();
    $this->actingAs($corrector)->post("{$this->page}/{$this->confirmation->public_id}/retur", $this->correction)->assertForbidden();
    $this->actingAs($returner)->post($this->page, $this->confirm)->assertForbidden();

    expect(DB::table('stock_restorations')->count())->toBe(0)->and(DB::table('dropship_confirmations')->count())->toBe(1);
});

it('shows the actual cost only with cost.view (J-FUL-03; PJ-06; SM-25)', function () {
    $blind = Authorization::admin([$this->company->id], ['projects.view', 'dropship.confirm']);

    $this->actingAs($blind)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.cost', false)->missing('page.confirmations.0.lines.0.cost'));
    $this->actingAs($this->owner)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.cost', true)->where('page.confirmations.0.lines.0.cost', '300000.00'));
});

it('answers another company\'s project and confirmation as unknown, each command writing nothing (SM-01; CS-03; CS-04)', function () {
    $stranger = DropshipWorld::actor(InvoiceWorld::make()->company);

    $this->actingAs($stranger)->get($this->page)->assertNotFound();
    $this->actingAs($stranger)->post($this->page, $this->confirm)->assertNotFound();
    $this->actingAs($stranger)->post("{$this->page}/{$this->confirmation->public_id}/balikkan", $this->correction)->assertNotFound();
    $this->actingAs($stranger)->post("{$this->page}/{$this->confirmation->public_id}/retur", $this->correction)->assertNotFound();

    expect(DB::table('dropship_confirmations')->count())->toBe(1)->and(DB::table('stock_restorations')->count())->toBe(0);
});

it('gives the journey\'s Kirim langsung step its link to PG-032 only with dropship.confirm (NAV-032; PL-3)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);
    $other = InvoiceWorld::make();
    $fresh = DropshipWorld::make($other);
    $confirmer = DropshipWorld::actor($other->company);

    $this->actingAs($viewer)->get("/proyek/{$this->w['project']->publicId}")->assertOk();
    $this->actingAs($confirmer)->get("/proyek/{$fresh['project']->publicId}")->assertInertia(fn (AssertableInertia $page) => $page->where('journey.next.key', 'projects.next.direct')
        ->where('journey.next.href', "/proyek/{$fresh['project']->publicId}/kirim-langsung"));
});
