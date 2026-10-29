<?php

use App\Modules\IAM\Models\User;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-08 (TASK-038; IG-01–IG-05; INT-184; INT-185; CI-05; M-09; criterion 14): "Reservasi", "Kurangi Reservasi" and "Lepas"
// server side — each success once, its `command_id` replayed answering the recorded outcome with nothing written twice, the
// same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
    $this->world->stock('10');
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project();
    $this->line = ReservationWorld::line($this->project);
});

afterEach(fn () => @unlink($this->hotFile));

function reservationInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function reservationSentTwice(object $test, string $path, array $body): string
{
    $test->actingAs($test->actor)->post($path, $body);
    $first = (string) (reservationInteractionAnswer()['kind'] ?? '');
    $test->actingAs($test->actor)->post($path, $body);

    return $first.' '.(string) (reservationInteractionAnswer()['kind'] ?? '');
}

it('reserves once for one command_id sent twice, the same identifier with another quantity a CONFLICT (INT-184; CI-05; M-09)', function () {
    $reserve = ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'baris' => ReservationWorld::handle($this->line), 'jumlah' => '3'];

    expect(reservationSentTwice($this, '/gudang/reservasi', $reserve))->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('reservations')->value('remaining_qty_base'))->toBe('3.000')
        ->and(DB::table('reservation_events')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationCreated')->count())->toBe(1)
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('3.000');

    $this->actingAs($this->actor)->post('/gudang/reservasi', [...$reserve, 'jumlah' => '4']);
    expect(reservationInteractionAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT)
        ->and(DB::table('reservations')->value('remaining_qty_base'))->toBe('3.000');
});

it('reduces and releases once each for one command_id sent twice, another reason a CONFLICT (INT-185; CI-05; M-09)', function () {
    expect(ReservationWorld::reserve($this->world->actorId, $this->line, '5'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($this->line);
    $path = '/gudang/reservasi/'.RowHandle::of('reservations', $reservation).'/kurangi';
    $reduce = ['command_id' => (string) Str::uuid(), 'jumlah' => '2', 'alasan' => 'Klien menunda'];

    expect(reservationSentTwice($this, $path, $reduce))->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('reservations')->where('id', $reservation)->value('remaining_qty_base'))->toBe('3.000')
        ->and(DB::table('reservation_events')->where('kind', 'DECREASE')->count())->toBe(1);

    $this->actingAs($this->actor)->post($path, [...$reduce, 'alasan' => 'Alasan lain']);
    expect(reservationInteractionAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);

    $release = ['command_id' => (string) Str::uuid(), 'lepas' => true, 'alasan' => 'Proyek ditunda'];
    $this->actingAs($this->actor)->post($path, $release);
    expect(reservationInteractionAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and(DB::table('reservations')->where('id', $reservation)->value('state'))->toBe('RELEASED');

    // Its response lost, the replay names a reservation no longer ACTIVE and still answers the recorded outcome, nothing written twice.
    $this->actingAs($this->actor)->post($path, $release);
    expect(reservationInteractionAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and(DB::table('reservation_events')->where('kind', 'RELEASE')->count())->toBe(1)
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000');
});
