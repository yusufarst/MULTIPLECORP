<?php

use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-04 (TASK-038; ROUTE-025; ROUTE-184; ROUTE-185; AX-01; AX-02; WF-INV-02; criteria 1–4, 6–8, 12, 13): "Reservasi" binds
// usable stock to a confirmed warehouse line — the product guard's RESERVED and the line guard's reserved quantity moved
// together, no stock moved —, refused with the current availability (C-04) or the line's remainder (C-05), never for an
// unconfirmed, drop-ship or service line; "Kurangi Reservasi" and "Lepas" with their reason, never above what remains; a
// consumed reservation never reactivated; PG-025 by branch and tab, another company's reservation by its marker only.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
});

afterEach(fn () => @unlink($this->hotFile));

function reservationAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function reserveLine(object $test, int $line, string $quantity, ?int $actor = null): TestResponse
{
    $project = (string) DB::table('projects')->where('id', DB::table('project_lines')->where('id', $line)->value('project_id'))->value('public_id');

    return $test->actingAs(User::query()->findOrFail($actor ?? $test->world->actorId))->post('/gudang/reservasi', [
        'command_id' => (string) Str::uuid(), 'proyek' => $project, 'baris' => ReservationWorld::handle($line), 'jumlah' => $quantity,
    ]);
}

function reduceReservation(object $test, int $reservation, array $body, ?int $actor = null): TestResponse
{
    return $test->actingAs(User::query()->findOrFail($actor ?? $test->world->actorId))
        ->post('/gudang/reservasi/'.RowHandle::of('reservations', $reservation).'/kurangi', ['command_id' => (string) Str::uuid(), 'proyek' => ReservationWorld::projectOf($reservation), ...$body]);
}

it('reserves for a confirmed warehouse line: RESERVED and the line\'s reserved quantity move together, nothing else (annex 7; AC-22; BR-RSV-02; criteria 1, 13)', function () {
    $this->world->stock('8', '2');
    $first = ReservationWorld::line($this->world->project());
    $second = ReservationWorld::line($this->world->project());
    reserveLine($this, $first, '3')->assertRedirect('/gudang/reservasi');
    $before = ['movements' => DB::table('stock_movements')->count(), 'entries' => DB::table('stock_movement_entries')->count(), 'lots' => DB::table('inventory_lots')->count(),
        'costs' => DB::table('cost_attributions')->count(), 'version' => DB::table('stock_product_balances')->where('product_id', $this->world->productId())->value('version')];

    // ON HAND 10, UNUSABLE 2 and RESERVED 3: AVAILABLE 5; reserving 2 leaves RESERVED 5 and AVAILABLE 3.
    reserveLine($this, $second, '2', $this->world->actorId);
    $reservation = ReservationWorld::active($second);

    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED', 'text' => 'Reservasi tersimpan.'])
        ->and(ReservationWorld::guard($this->world->productId()))->toEqual((object) ['on_hand_qty' => '10.000', 'unusable_qty' => '2.000', 'reserved_qty' => '5.000'])
        ->and(DB::table('project_line_balances')->where('project_line_id', $second)->value('reserved_qty_base'))->toBe('2.000')
        ->and(DB::table('reservations')->where('id', $reservation)->first(['state', 'remaining_qty_base', 'company_id']))
        ->toEqual((object) ['state' => 'ACTIVE', 'remaining_qty_base' => '2.000', 'company_id' => $this->world->company->id])
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->pluck('kind')->all())->toBe(['CREATE'])
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationCreated')->where('entity_id', $reservation)->count())->toBe(1)
        // No stock moved, no lot, cost or count basis touched (BR-RSV-02; GU-05; BR-FIN-12).
        ->and(['movements' => DB::table('stock_movements')->count(), 'entries' => DB::table('stock_movement_entries')->count(), 'lots' => DB::table('inventory_lots')->count(),
            'costs' => DB::table('cost_attributions')->count(), 'version' => DB::table('stock_product_balances')->where('product_id', $this->world->productId())->value('version')])->toBe($before);

    // A further "Reservasi" of the line increases its one ACTIVE reservation (CM-27).
    reserveLine($this, $second, '1');
    expect(DB::table('reservations')->where('project_line_id', $second)->count())->toBe(1)
        ->and(DB::table('reservations')->where('id', $reservation)->value('remaining_qty_base'))->toBe('3.000')
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->pluck('kind')->all())->toBe(['CREATE', 'INCREASE'])
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationIncreased')->count())->toBe(1);
});

it('refuses more than the current availability with it in words, and commits what is available (annex 8; C-04; ST-06; criterion 2)', function () {
    $this->world->stock('8', '2');
    $line = ReservationWorld::line($this->world->project());

    reserveLine($this, $line, '10');
    expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-04', 'text' => 'Stok tersedia tidak cukup. Tersedia saat ini: 8.'])
        ->and(DB::table('reservations')->count())->toBe(0);

    reserveLine($this, $line, '8');
    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('8.000');
});

it('never reserves an unconfirmed, drop-ship or service line, and offers "Reservasi" only on a confirmed warehouse line (BR-QUO-03; BR-RSV-01; BR-INV-11; criterion 3)', function () {
    $this->world->stock('20');
    $draft = ReservationWorld::line($this->world->project(['WAREHOUSE'], '5', false));
    reserveLine($this, $draft, '1');
    expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-RSV-01', 'text' => 'Baris ini belum dikonfirmasi pada proyek yang berjalan, jadi belum dapat direservasi.']);

    $project = $this->world->project(['WAREHOUSE', 'DROPSHIP', 'SERVICE'], '5');
    foreach ([2, 3] as $number) {
        reserveLine($this, ReservationWorld::line($project, $number), '1');
        expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-RSV-01', 'text' => 'Hanya baris dengan pemenuhan dari gudang yang dapat direservasi.']);
    }

    expect(DB::table('reservations')->count())->toBe(0);
    $this->actingAs(User::query()->findOrFail($this->world->actorId))->get("/proyek/{$project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fulfilment.lines.0.can.reserve', true)->where('fulfilment.lines.0.reservable', '5.000')
        ->where('fulfilment.lines.1.can.reserve', false)->where('fulfilment.lines.2.can.reserve', false));
});

it('refuses more than the line\'s remainder with the current remainder (L-04; C-05; criterion 4)', function () {
    $this->world->stock('20');
    $line = ReservationWorld::line($this->world->project());
    reserveLine($this, $line, '6');

    reserveLine($this, $line, '5');
    expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-05', 'text' => 'Melebihi sisa baris yang belum direservasi. Sisa saat ini: 4.'])
        ->and(DB::table('reservations')->where('project_line_id', $line)->value('remaining_qty_base'))->toBe('6.000');

    // A quantity outside the product's scale or not above zero is answered at its field (MSG-12; C-09).
    reserveLine($this, $line, '1.5')->assertSessionHasErrors(['jumlah' => 'Jumlah memakai lebih banyak angka desimal daripada yang diizinkan produk ini.']);
    reserveLine($this, $line, '0')->assertSessionHasErrors(['jumlah' => 'Isi jumlah lebih dari nol.']);
});

it('reduces and releases with a reason, never above what remains (AX-02; C-05; criterion 6)', function () {
    $this->world->stock('10');
    $line = ReservationWorld::line($this->world->project());
    reserveLine($this, $line, '5');
    $reservation = ReservationWorld::active($line);

    reduceReservation($this, $reservation, ['jumlah' => '2'])->assertSessionHasErrors('alasan');
    reduceReservation($this, $reservation, ['jumlah' => '6', 'alasan' => 'Klien menunda']);
    expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-05', 'text' => 'Melebihi sisa reservasi. Sisa saat ini: 5.']);

    reduceReservation($this, $reservation, ['jumlah' => '2', 'alasan' => 'Klien menunda'])->assertRedirect('/gudang/reservasi');
    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED', 'text' => 'Reservasi dikurangi.'])
        ->and(DB::table('reservations')->where('id', $reservation)->value('remaining_qty_base'))->toBe('3.000')
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->orderByDesc('id')->first(['kind', 'qty_delta_base', 'reason']))
        ->toEqual((object) ['kind' => 'DECREASE', 'qty_delta_base' => '-2.000', 'reason' => 'Klien menunda'])
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationReduced')->value('reason'))->toBe('Klien menunda');

    reduceReservation($this, $reservation, ['lepas' => true, 'alasan' => 'Proyek ditunda']);
    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED', 'text' => 'Reservasi dilepas.'])
        ->and(DB::table('reservations')->where('id', $reservation)->first(['state', 'remaining_qty_base']))->toEqual((object) ['state' => 'RELEASED', 'remaining_qty_base' => '0.000'])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000')
        ->and(DB::table('project_line_balances')->where('project_line_id', $line)->value('reserved_qty_base'))->toBe('0.000')
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationReleased')->value('reason'))->toBe('Proyek ditunda');

    // A command from a stale page on the ended reservation is refused in place with what remains (C-05; NV-04).
    reduceReservation($this, $reservation, ['lepas' => true, 'alasan' => 'Lagi']);
    expect(reservationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-05', 'text' => 'Melebihi sisa reservasi. Sisa saat ini: 0.'])
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->count())->toBe(3);
});

it('lists a residual reservation of a completed project as "perlu dilepas", released by command only (PX-02; FS-09; QS-16; criterion 7)', function () {
    $this->world->stock('10');
    $project = $this->world->project();
    $line = ReservationWorld::line($project);
    reserveLine($this, $line, '4');
    DB::table('projects')->where('id', $project->id)->update(['state' => 'COMPLETED_NORMAL', 'lock_version' => DB::raw('lock_version + 1')]);
    ProjectStateTransition::factory()->create(['project_id' => $project->id, 'from_state' => 'ACTIVE', 'to_state' => 'COMPLETED_NORMAL', 'transition_kind' => 'COMPLETE_NORMAL', 'decided_by' => $this->world->actorId]);
    $actor = User::query()->findOrFail($this->world->actorId);

    $this->actingAs($actor)->get('/gudang/reservasi')->assertInertia(fn (AssertableInertia $page) => $page->component('INV/Reservations')->has('rows', 0));
    $this->actingAs($actor)->get('/gudang/reservasi?tab=dilepas')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('rows', 1)->where('rows.0.residual', true)->where('rows.0.may', ['reserve' => false, 'reduce' => true, 'release' => true])->where('rows.0.project.number', $project->project_number));

    reduceReservation($this, ReservationWorld::active($line), ['lepas' => true, 'alasan' => 'Proyek selesai']);
    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED']);
    $this->actingAs($actor)->get('/gudang/reservasi?tab=riwayat')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.state', 'RELEASED'));
});

it('never reactivates a consumed reservation: reserving the line again makes a new one (L-07; CM-27; criterion 8)', function () {
    $this->world->stock('10');
    $line = ReservationWorld::line($this->world->project());
    reserveLine($this, $line, '3');
    $consumed = ReservationWorld::active($line);

    expect(ReservationWorld::consume($this->world->actorId, $line, '3'))->toBe('COMMITTED')
        ->and(DB::table('reservations')->where('id', $consumed)->value('state'))->toBe('CONSUMED');

    reserveLine($this, $line, '2');
    expect(reservationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(ReservationWorld::active($line))->not->toBe($consumed)
        ->and(DB::table('reservations')->where('id', $consumed)->first(['state', 'remaining_qty_base']))->toEqual((object) ['state' => 'CONSUMED', 'remaining_qty_base' => '0.000'])
        ->and(DB::table('reservation_events')->where('reservation_id', ReservationWorld::active($line))->pluck('kind')->all())->toBe(['CREATE']);
});

it('shows PG-025 by branch, another company\'s reservation of the pool by its quantity and marker only (CS-08; PJ-04; criterion 12)', function () {
    $this->world->stock('20');
    $other = ReservationWorld::make($this->world);
    $mine = ReservationWorld::line($this->world->project());
    $theirs = ReservationWorld::line($other->project());
    reserveLine($this, $mine, '3');
    reserveLine($this, $theirs, '4', $other->actorId);
    $actor = User::query()->findOrFail($this->world->actorId);

    $this->actingAs($actor)->get('/gudang/reservasi')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('INV/Reservations')->has('branches', 1)->where('branch', $this->world->company->public_id)->has('rows', 1)
        ->where('rows.0.quantity', '3.000')->where('rows.0.may', ['reserve' => true, 'reduce' => true, 'release' => true])->where('more', null));

    $pool = $this->world->goods['product']->public_id;
    $this->actingAs($actor)->get("/gudang/reservasi?produk={$pool}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('product.name', $this->world->goods['product']->name)->where('filters.produk', $pool)->has('rows', 2)
        ->where('rows.0.quantity', '4.000')->where('rows.0.handle', null)->where('rows.0.project', null)->where('rows.0.line', null)->where('rows.0.company', null)
        ->where('rows.0.may', ['reserve' => false, 'reduce' => false, 'release' => false])
        ->where('rows.1.quantity', '3.000')->where('rows.1.project.number', DB::table('projects')->where('id', DB::table('project_lines')->where('id', $mine)->value('project_id'))->value('project_number')));

    // Nothing of the other company's project shows through the tabs or the sort: its reservation stays an ACTIVE row of
    // the pool, after the branch's own by number, whatever its project's state (PJ-04).
    DB::table('projects')->where('id', DB::table('project_lines')->where('id', $theirs)->value('project_id'))->update(['state' => 'COMPLETED_NORMAL', 'lock_version' => DB::raw('lock_version + 1')]);
    $this->actingAs($actor)->get("/gudang/reservasi?produk={$pool}&urut=proyek")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('rows', 2)->where('rows.0.quantity', '3.000')->where('rows.1.quantity', '4.000')->where('rows.1.residual', false));
    $this->actingAs($actor)->get("/gudang/reservasi?produk={$pool}&tab=dilepas")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

    // The other company's reservation is unknown to a command of this account (SM-01; CS-03).
    reduceReservation($this, ReservationWorld::active($theirs), ['lepas' => true, 'alasan' => 'Uji'])->assertNotFound();
    reserveLine($this, $theirs, '1')->assertNotFound();
    expect(DB::table('reservations')->where('project_line_id', $theirs)->value('remaining_qty_base'))->toBe('4.000');
});
