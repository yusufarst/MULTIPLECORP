<?php

use App\Modules\INV\Models\Reservation;
use App\Modules\INV\Queries\Reservations;
use App\Modules\PRJ\Models\ProjectLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-15 (TASK-038; QB-12; PFM-01; PFM-02; PFM-20): PG-025 reads a page in the same number of statements for two
// reservations and a full page, its product's pool alike; "Reservasi" writes in the same number of statements whatever
// the project's lines.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
    $this->project = $this->world->project();
});

afterEach(fn () => @unlink($this->hotFile));

function reservationStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** `count` reservations of the world's product, each on a line of its own of the world's project. */
function reservationRows(object $test, int $count): void
{
    $next = (int) DB::table('project_lines')->where('project_id', $test->project->id)->max('line_no');

    foreach (range(1, $count) as $offset) {
        $line = ProjectLine::factory()->create(['project_id' => $test->project->id, 'product_id' => $test->world->productId(), 'line_no' => $next + $offset])->id;
        Reservation::factory()->create(['project_line_id' => $line, 'remaining_qty_base' => '1.000']);
    }
}

it('reads PG-025 in the same number of statements for two reservations and a full page, and its product\'s pool alike (QB-12; PFM-01; PFM-02)', function () {
    $pool = $this->world->goods['product']->public_id;
    $read = fn (?string $product): int => reservationStatements(fn () => app(Reservations::class)->screen($this->world->actorId, ['cabang' => null, 'tab' => null, 'produk' => $product, 'urut' => null, 'setelah' => null]));
    reservationRows($this, 2);
    $read(null);
    $few = [$read(null), $read($pool)];
    reservationRows($this, Reservations::PAGE + 5);
    $screen = app(Reservations::class)->screen($this->world->actorId, ['cabang' => null, 'tab' => null, 'produk' => null, 'urut' => null, 'setelah' => null]);

    expect($few[0])->toBeLessThanOrEqual(6)
        ->and([$read(null), $read($pool)])->toBe($few)
        ->and($screen['rows'])->toHaveCount(Reservations::PAGE)
        ->and($screen['more'])->toBe((string) Reservations::PAGE);
});

it('writes a reservation in the same number of statements whatever the project\'s lines (PFM-20)', function () {
    $this->world->stock('100');
    $first = ReservationWorld::line($this->project);
    $reserve = fn (int $line): int => reservationStatements(fn () => expect(ReservationWorld::reserve($this->world->actorId, $line, '1'))->toBe('COMMITTED'));
    $few = $reserve($first);
    $wide = $this->world->project(array_fill(0, 12, 'WAREHOUSE'), '2');

    expect($reserve(ReservationWorld::line($wide, 7)))->toBe($few)
        ->and($few)->toBeLessThanOrEqual(40);
});
