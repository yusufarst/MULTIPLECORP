<?php

use App\Modules\IAM\Models\User;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\INV\Services\ReservationLifecycle;
use App\Modules\INV\Services\ReservationPredicate;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Actions\AnswerQuotation;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\ReviseQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\RecordingParticipant;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-04 (TASK-038; AX-13; AX-14; AX-27; L-21; BR-QUO-02; criterion 11): INV's lifecycle participant inside PRJ's own
// commands — a re-confirmation lowering a line's demand lowers its reservation, "Batalkan Sisa" and "Ubah Cara Pemenuhan"
// release the remainder's reservation, a project cancellation releases every active one —, each in PRJ's transaction, a
// failure after it rolling the whole command back; and predicate 8's reservation part.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
    $this->world->stock('20');
    $this->actor = User::query()->findOrFail($this->world->actorId);
});

afterEach(fn () => @unlink($this->hotFile));

function lifecycleAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** @return array{0: string, 1: string} the reservation's state and remaining quantity */
function lifecycleReservation(int $reservation): array
{
    $row = DB::table('reservations')->where('id', $reservation)->sole();

    return [(string) $row->state, (string) $row->remaining_qty_base];
}

/** A revision of `qty` drafted, issued and approved by the client: the project's QUOTATION_APPROVAL confirmation. */
function lifecycleApproved(int $actor, Project $project, string $qty, bool $revise): void
{

    if ($revise) {
        expect(ReservationWorld::word(app(ReviseQuotation::class)->run((string) Str::uuid(), $actor, $project->refresh(), QuotationRecords::latest($project->id)['public_id'])))->toBe('COMMITTED');
    } else {
        app(SaveQuotationDraft::class)->run((string) Str::uuid(), $actor, $project, null, null, null, [], []);
    }

    $revision = QuotationRecords::latest($project->id);
    $line = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->sole();
    $valid = CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString();
    expect(ReservationWorld::word(app(SaveQuotationDraft::class)->run((string) Str::uuid(), $actor, $project->refresh(), $revision['public_id'], $revision['lock_version'], $valid,
        [(int) $line->id => ['quantity' => $qty, 'quantity_base' => "{$qty}.000", 'price' => '20000', 'description' => 'Buku paket']], ['v' => 1])))->toBe('COMMITTED');
    $revision = QuotationRecords::latest($project->id);
    expect(ReservationWorld::word(app(IssueQuotation::class)->run((string) Str::uuid(), $actor, $project->refresh(), $revision['public_id'], $revision['lock_version'], BusinessDate::today(), true)))->toBe('COMMITTED')
        ->and(ReservationWorld::word(app(AnswerQuotation::class)->run((string) Str::uuid(), $actor, $project->refresh(), $revision['public_id'], true, 'SPK diterima', BusinessDate::today(), true)))->toBe('COMMITTED');
}

it('lowers a reservation above the demand a re-confirmation pins, in its transaction (AX-13; BR-QUO-02; criterion 11)', function () {
    DocumentTypeFactory::ensure('DOC-01');
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::scheme($this->world->company, SequenceType::Doc01, "{$year}-01-01", 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($this->world->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $seller = Authorization::admin([$this->world->company->id], [...ReservationWorld::CAPABILITIES, 'quotation.manage', 'documents.issue'])->id;
    $project = $this->world->project(['WAREHOUSE'], '10', false);
    lifecycleApproved($seller, $project, '12', false);
    $line = ReservationWorld::line($project);
    expect(ReservationWorld::reserve($this->world->actorId, $line, '10'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($line);

    // The client approves a revision of 6: the reservation fits the new demand, never above it (C-05).
    lifecycleApproved($seller, $project, '6', true);

    expect(lifecycleReservation($reservation))->toBe(['ACTIVE', '6.000'])
        ->and(DB::table('project_line_balances')->where('project_line_id', $line)->first(['demand_qty_base', 'reserved_qty_base']))->toEqual((object) ['demand_qty_base' => '6.000', 'reserved_qty_base' => '6.000'])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('6.000')
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->orderByDesc('id')->first(['kind', 'qty_delta_base', 'reason']))
        ->toEqual((object) ['kind' => 'DECREASE', 'qty_delta_base' => '-4.000', 'reason' => 'Konfirmasi ulang menurunkan permintaan baris.']);
});

it('releases the cancelled remainder\'s reservation with "Batalkan Sisa", and all of it with the whole remainder (AX-14; SF-CANCEL-LINE; criterion 11)', function () {
    $project = $this->world->project();
    $line = ReservationWorld::line($project);
    expect(ReservationWorld::reserve($this->world->actorId, $line, '5'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($line);
    $cancel = fn (string $quantity) => $this->actingAs($this->actor)->post("/proyek/{$project->public_id}/batalkan-sisa", [
        'command_id' => (string) Str::uuid(), 'baris' => [['baris' => ReservationWorld::handle($line), 'jumlah' => $quantity]], 'alasan' => 'Klien membatalkan sebagian',
        'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);

    // 10 demanded, 5 reserved: cancelling 7 leaves 3 to fill, and the reservation fits it.
    $cancel('7');
    expect(lifecycleAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(lifecycleReservation($reservation))->toBe(['ACTIVE', '3.000'])
        ->and(DB::table('project_line_balances')->where('project_line_id', $line)->first(['cancelled_qty_base', 'reserved_qty_base']))->toEqual((object) ['cancelled_qty_base' => '7.000', 'reserved_qty_base' => '3.000'])
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->orderByDesc('id')->value('reason'))->toBe('Sisa baris dibatalkan atau dipindahkan.');

    $cancel('3');
    expect(lifecycleReservation($reservation))->toBe(['RELEASED', '0.000'])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000');
});

it('releases the reservation of a remainder "Ubah Cara Pemenuhan" splits off (L-21; AX-14; criterion 11)', function () {
    $project = $this->world->project();
    $line = ReservationWorld::line($project);
    expect(ReservationWorld::reserve($this->world->actorId, $line, '4'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($line);

    $this->actingAs($this->actor)->post("/proyek/{$project->public_id}/ubah-pemenuhan", [
        'command_id' => (string) Str::uuid(), 'baris' => ReservationWorld::handle($line), 'cara' => 'DROPSHIP', 'alasan' => 'Pemasok mengirim langsung',
    ]);

    expect(lifecycleAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(lifecycleReservation($reservation))->toBe(['RELEASED', '0.000'])
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->orderByDesc('id')->value('kind'))->toBe('RELEASE')
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000');
});

it('rolls the whole PRJ command back when a participant after INV\'s fails (AX-14; criterion 11)', function () {
    $project = $this->world->project();
    $line = ReservationWorld::line($project);
    expect(ReservationWorld::reserve($this->world->actorId, $line, '5'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($line);
    RecordingParticipant::register();
    RecordingParticipant::$fail = true;

    $this->actingAs($this->actor)->post("/proyek/{$project->public_id}/batalkan-sisa", [
        'command_id' => (string) Str::uuid(), 'baris' => [['baris' => ReservationWorld::handle($line), 'jumlah' => '8']], 'alasan' => 'Klien membatalkan', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);

    expect(lifecycleAnswer()['kind'] ?? null)->not->toBe('COMMITTED')
        ->and(lifecycleReservation($reservation))->toBe(['ACTIVE', '5.000'])
        ->and(DB::table('reservation_events')->where('reservation_id', $reservation)->count())->toBe(1)
        ->and(DB::table('project_line_balances')->where('project_line_id', $line)->value('cancelled_qty_base'))->toBe('0.000');
});

it('releases every active reservation of a cancelled project, and predicate 8 holds once none is active (AX-27; BR-PRJ-01; WF-PRJ-03; criterion 11)', function () {
    $project = $this->world->project(['WAREHOUSE', 'WAREHOUSE']);
    [$first, $second] = [ReservationWorld::line($project, 1), ReservationWorld::line($project, 2)];
    expect(ReservationWorld::reserve($this->world->actorId, $first, '3'))->toBe('COMMITTED')
        ->and(ReservationWorld::reserve($this->world->actorId, $second, '2'))->toBe('COMMITTED');
    $state = fn () => app(ReservationPredicate::class)->states([$project->id], true)[$project->id][Predicates::STOCK];

    expect($state()->met())->toBeFalse()
        ->and($state()->items[0]->key)->toBe('projects.item.reservations_active')
        ->and($state()->items[0]->params)->toBe(['jumlah' => '2']);

    // TASK-056 builds the cancellation; PRJ calls the participant inside it, under LK-01 (AX-27).
    $locks = DB::table('project_lines')->where('project_id', $project->id)->pluck('id')
        ->reduce(fn (LockSet $set, mixed $line): LockSet => $set->union(InventoryPosting::lineLocks((int) $line)), new LockSet);
    expect(ReservationWorld::word(ReservationWorld::run($this->world->actorId, $this->world->company->id, $locks, static function (CommandContext $context) use ($project): void {
        app(ReservationLifecycle::class)->cancelled($context, $project->id);
    })))->toBe('COMMITTED');

    expect(DB::table('reservations')->where('state', 'ACTIVE')->count())->toBe(0)
        ->and(DB::table('reservation_events')->where('reason', 'Proyek dibatalkan.')->pluck('kind')->all())->toBe(['RELEASE', 'RELEASE'])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000')
        ->and($state()->met())->toBeTrue();
});
