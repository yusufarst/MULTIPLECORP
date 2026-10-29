<?php

use App\Modules\IAM\Models\User;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-06 (TASK-038; AZ-01; IG-01; SM-01; SM-04; CS-03; PJ-04; criteria 10, 12): ROUTE-025 and ROUTE-184 with `stock.reserve`
// and its prerequisites, ROUTE-185 with `stock.reserve`; without it the page and both commands refused and PG-011's
// "Reservasi" absent; another company's line or reservation unknown; a cut on a reservation outside the actor's grants
// refused and routed with one audit event per company (AZ-08; SQ-23).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
    $this->world->stock('10');
    $this->project = $this->world->project();
    $this->line = ReservationWorld::line($this->project);
    expect(ReservationWorld::reserve($this->world->actorId, $this->line, '2'))->toBe('COMMITTED');
    $this->reservation = ReservationWorld::active($this->line);
});

afterEach(fn () => @unlink($this->hotFile));

it('refuses the page and both commands without stock.reserve, and PG-011 offers no "Reservasi" (AZ-01; IG-01; criterion 12)', function () {
    $viewer = Authorization::admin([$this->world->company->id], ['projects.view', 'stock.view', 'projects.manage']);

    $this->actingAs($viewer)->get('/gudang/reservasi')->assertForbidden();
    $this->actingAs($viewer)->post('/gudang/reservasi', ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'baris' => ReservationWorld::handle($this->line), 'jumlah' => '1'])->assertForbidden();
    $this->actingAs($viewer)->post('/gudang/reservasi/'.RowHandle::of('reservations', $this->reservation).'/kurangi', ['command_id' => (string) Str::uuid(), 'lepas' => true, 'alasan' => 'Uji'])->assertForbidden();
    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('fulfilment.lines.0.can.reserve', false));
    $this->actingAs(User::query()->findOrFail($this->world->actorId))->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('fulfilment.lines.0.can.reserve', true));

    expect(DB::table('reservations')->where('id', $this->reservation)->value('remaining_qty_base'))->toBe('2.000');
});

it('answers another company\'s line or reservation as unknown, and a page of no branch without rows (SM-01; SM-04; CS-03)', function () {
    $stranger = ReservationWorld::make($this->world);

    $this->actingAs(User::query()->findOrFail($stranger->actorId))->post('/gudang/reservasi', [
        'command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'baris' => ReservationWorld::handle($this->line), 'jumlah' => '1',
    ])->assertNotFound();
    $this->actingAs(User::query()->findOrFail($stranger->actorId))->post('/gudang/reservasi/'.RowHandle::of('reservations', $this->reservation).'/kurangi', [
        'command_id' => (string) Str::uuid(), 'lepas' => true, 'alasan' => 'Uji',
    ])->assertNotFound();
    $this->actingAs(User::query()->findOrFail($stranger->actorId))->get('/gudang/reservasi')->assertInertia(fn (AssertableInertia $page) => $page->has('branches', 1)->has('rows', 0));

    expect(DB::table('reservations')->where('id', $this->reservation)->value('remaining_qty_base'))->toBe('2.000');
});

it('refuses and routes a cut on another company\'s reservation, one audit event per company committed with the refusal (AZ-08; SQ-23; QS-20; criterion 10)', function () {
    $other = ReservationWorld::make($this->world);
    $theirs = ReservationWorld::line($other->project());
    expect(ReservationWorld::reserve($other->actorId, $theirs, '3'))->toBe('COMMITTED');
    $foreign = ReservationWorld::active($theirs);
    $lot = (int) DB::table('inventory_lots')->value('id');

    // 10 on hand, 5 reserved: turning 7 unusable leaves 2 short; the actor chooses the other company's reservation.
    expect(ReservationWorld::reduceSupply($this->world->actorId, $this->world->company->id, $this->world->productId(), $lot, $this->world->locationId, '7', [$foreign => '2']))->toBe('REJECTED AZ-08')
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationCutRouted')->pluck('company_id')->all())->toBe([$other->company->id])
        ->and(DB::table('reservations')->where('id', $foreign)->value('remaining_qty_base'))->toBe('3.000')
        ->and(ReservationWorld::guard($this->world->productId()))->toEqual((object) ['on_hand_qty' => '10.000', 'unusable_qty' => '0.000', 'reserved_qty' => '5.000']);
});
