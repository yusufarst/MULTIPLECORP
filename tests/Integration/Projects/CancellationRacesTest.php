<?php

use App\Modules\PRJ\Actions\CancelProject;
use App\Modules\PRJ\Models\Project;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-056; AX-27; C-04; C-05; CM-29; CM-31; criterion 6) on real PostgreSQL sessions: a cancellation racing a dispatch for the project meets it at the project's LK-01 — the
// cancellation first refuses the dispatch (L-11), the dispatch first leaves the cancellation only the rest of the reservation to release —; a cancellation racing a receipt on the
// project's purchase lets both commit, the goods arriving as free stock; never a reservation both consumed and released, AVAILABLE never below zero. The reconciliation suite runs
// after every scenario (tests/Pest.php).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'projects.cancel'])->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '1000');
    expect(ReservationWorld::reserve($this->actor, $this->demand['line'], '5'))->toBe('COMMITTED');
});

/** AX-27 as its own command, the project's version read as the page would show it; its word. */
function raceCancel(int $actor, int $projectId): string
{
    $project = Project::query()->findOrFail($projectId);

    return InvoiceWorld::word(app(CancelProject::class)->run((string) Str::uuid(), $actor, $project->id, $project->company_id, $project->public_id, $project->lock_version, 'Klien membatalkan kontrak'));
}

/** The reservations of the line that were both consumed and released: none ever is. */
function raceDoubleSpent(int $lineId): int
{
    return DB::table('reservations as r')->where('r.project_line_id', $lineId)
        ->whereExists(fn ($events) => $events->from('reservation_events as e')->whereColumn('e.reservation_id', 'r.id')->where('e.kind', 'RELEASE')->where('e.reason', 'Proyek dibatalkan.'))
        ->whereExists(fn ($events) => $events->from('reservation_events as e')->whereColumn('e.reservation_id', 'r.id')->where('e.kind', 'CONSUME')->where('e.recorded_at', '>', DB::raw("(select min(x.recorded_at) from reservation_events x where x.reservation_id = r.id and x.kind = 'RELEASE' and x.reason = 'Proyek dibatalkan.')")))
        ->count();
}

it('lets a cancellation and a dispatch for the project end with one winner in both orders, nothing reserved after, AVAILABLE ≥ 0 (AX-27; C-04; C-05; criterion 6)', function (bool $cancelFirst) {
    [$actor, $company, $line, $unit, $lot, $rack, $project] = [$this->actor, $this->company->id, $this->demand['line'], $this->demand['unit'], $this->lot, $this->rack, $this->demand['project']->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $cancelFirst ? raceCancel($actor, $project) : DispatchWorld::dispatch($actor, $company, $line, $unit, '3', [DispatchWorld::pick($lot, $rack, '3')]), 'the first holds the project'),
        'second' => static function () use ($cancelFirst, $actor, $company, $line, $unit, $lot, $rack, $project): string {
            Barrier::await('the first holds the project');

            return $cancelFirst ? DispatchWorld::dispatch($actor, $company, $line, $unit, '3', [DispatchWorld::pick($lot, $rack, '3')]) : raceCancel($actor, $project);
        },
    ]);

    $guard = ReservationWorld::guard($this->demand['product']);
    expect($results['first'])->toBe('COMMITTED')
        ->and($results['second'])->toBe($cancelFirst ? 'REJECTED L-11' : 'COMMITTED')
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('CANCELLED')
        ->and($guard->reserved_qty)->toBe('0.000')
        ->and((float) $guard->on_hand_qty - (float) $guard->unusable_qty - (float) $guard->reserved_qty)->toBeGreaterThanOrEqual(0.0)
        ->and(raceDoubleSpent($line))->toBe(0)
        ->and(DB::table('dispatch_lines')->where('project_line_id', $line)->count())->toBe($cancelFirst ? 0 : 1);
})->with(['the cancellation first' => true, 'the dispatch first' => false]);

it('lets a cancellation and a receipt on the project\'s purchase both commit in both orders, the goods arriving as free stock (AX-27; CM-31; criterion 6)', function (bool $cancelFirst) {
    $purchase = ReceivingWorld::purchase($this->company, $this->demand['product'], $this->demand['unit'], '4', '1000', $this->demand['project']->id, $this->demand['line']);
    $purchaseLine = ReceivingWorld::line($purchase);
    [$actor, $company, $unit, $rack, $project, $purchaseId, $lineId] = [$this->actor, $this->company->id, $this->demand['unit'], $this->rack, $this->demand['project']->id, $purchase->id, $purchaseLine->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $cancelFirst ? raceCancel($actor, $project) : ReceivingWorld::receive($actor, $company, $purchaseId, $lineId, $unit, $rack, '4'), 'the first holds the project'),
        'second' => static function () use ($cancelFirst, $actor, $company, $unit, $rack, $project, $purchaseId, $lineId): string {
            Barrier::await('the first holds the project');

            return $cancelFirst ? ReceivingWorld::receive($actor, $company, $purchaseId, $lineId, $unit, $rack, '4') : raceCancel($actor, $project);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('CANCELLED')
        ->and(ReservationWorld::guard($this->demand['product'])->reserved_qty)->toBe('0.000')
        ->and(raceDoubleSpent($this->demand['line']))->toBe(0);
})->with(['the cancellation first' => true, 'the receipt first' => false]);
