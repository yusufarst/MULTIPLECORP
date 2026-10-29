<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\INV\Actions\ReleaseReservation;
use App\Modules\INV\Actions\Reserve;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\GuardUpdate;
use App\Support\RowHandle;
use Brick\Math\BigDecimal;
use Closure;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\INV\LocationFactory;
use Database\Factories\INV\StockMovementFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-038's world (TEST_STRATEGY §5): a company with a client, a goods product and a service, an actor holding the
 * reservation family, confirmed projects through PRJ's own commands, and synthetic stock — an opening lot's entries with
 * the product guard moved as INV's posting will move it, so the reconciliation suite reads it as posted stock. Until the
 * dispatch and the supply-reducing commands exist (TASK-041, TASK-039, TASK-042), the consumption and the cut are run here
 * inside the command envelope through `InventoryPosting`, as those commands will run them. Each command answers its outcome
 * as a word, by identifiers only, so a session in another process can run it.
 */
final class ReservationWorld
{
    public const CAPABILITIES = ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'stock.view', 'stock.reserve', 'finance.view', 'evidence.view'];

    public Company $company;

    public int $actorId;

    /** @var array{product: Product, base: Unit, box: ?Unit} */
    public array $goods;

    /** @var array{product: Product, base: Unit, box: ?Unit} */
    public array $service;

    public int $locationId;

    /** @var array{organization: ClientOrganization, unit: ClientUnit, address: ClientAddress, contact: ClientContact} */
    private array $client;

    private function __construct() {}

    /** A world of its own company; `sharing` another world's products and location — the pool every company's projects draw on. */
    public static function make(?self $sharing = null): self
    {
        $world = new self;
        $world->company = Projects::company();
        $world->client = Projects::client();
        $world->goods = $sharing === null ? Projects::product() : $sharing->goods;
        $world->service = $sharing === null ? Projects::product('SERVICE', '250000.00') : $sharing->service;
        $world->locationId = $sharing === null ? LocationFactory::new()->createOne()->id : $sharing->locationId;
        $world->actorId = Authorization::admin([$world->company->id], self::CAPABILITIES)->id;

        return $world;
    }

    public function productId(): int
    {
        return $this->goods['product']->id;
    }

    /**
     * A project of one line per mode — WAREHOUSE and DROPSHIP of the goods, SERVICE of the service —, each of `qty`, and
     * confirmed without a quotation on the client's order evidence (AX-13) unless `confirm` is false.
     *
     * @param  list<string>  $modes
     */
    public function project(array $modes = ['WAREHOUSE'], string $qty = '10', bool $confirm = true): Project
    {
        $products = array_map(fn (string $mode): array => $mode === 'SERVICE' ? $this->service : $this->goods, $modes);
        $lines = Projects::payload($this->company, $this->client, $products)['baris'];

        foreach ($modes as $at => $mode) {
            $lines[$at] = [...$lines[$at], 'mode' => $mode, 'jumlah' => $qty];
        }

        $created = Projects::create($this->actorId, $this->company, $this->client, $products, null, ['baris' => $lines]);
        $project = str_starts_with($created, 'COMMITTED ') ? Project::query()->where('company_id', $this->company->id)->where('project_number', substr($created, 10))->sole() : throw new RuntimeException("The project was {$created}.");

        if ($confirm) {
            $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'CLIENT_SPK'])->id;
            $answer = ConfirmationRaces::confirm($this->actorId, $project->id, $evidence);
            $answer === 'COMMITTED' || throw new RuntimeException("The confirmation was {$answer}.");
        }

        return $project->refresh();
    }

    public static function line(Project $project, int $number = 1): int
    {
        return (int) DB::table('project_lines')->where('project_id', $project->id)->where('line_no', $number)->value('id');
    }

    public static function handle(int $lineId): string
    {
        return RowHandle::of('project_lines', $lineId);
    }

    /** The public identifier of the project whose line the reservation binds — ROUTE-185 names a reservation within it. */
    public static function projectOf(int $reservationId): string
    {
        return (string) DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')
            ->where('r.id', $reservationId)->value('p.public_id');
    }

    /** The line's ACTIVE reservation, or 0. */
    public static function active(int $lineId): int
    {
        return (int) DB::table('reservations')->where('project_line_id', $lineId)->where('state', 'ACTIVE')->value('id');
    }

    /** Synthetic stock: an opening lot of `usable` + `unusable`, its entries and the guards they move. */
    public function stock(string $usable, string $unusable = '0'): int
    {
        return self::opening($this->company->id, $this->productId(), $this->locationId, $usable, $unusable);
    }

    /** The company's opening lot of the product at the location — the browser fixture's stock as well. */
    public static function opening(int $companyId, int $productId, int $locationId, string $usable, string $unusable = '0'): int
    {
        $total = (string) BigDecimal::of($usable)->plus($unusable);
        $lot = InventoryLot::factory()->create([
            'product_id' => $productId, 'source_company_id' => $companyId, 'entered_qty' => $total, 'acquired_qty_base' => $total, 'business_date' => BusinessDate::today(),
        ])->id;
        $movement = StockMovementFactory::post('OPENING', $companyId);

        foreach (['USABLE' => $usable, 'UNUSABLE' => $unusable] as $condition => $qty) {
            if (BigDecimal::of($qty)->isPositive()) {
                self::entry($lot, $productId, $locationId, $movement, 'OPENING', $qty, $condition);
            }
        }

        // The lot's cost guard beside its quantity, no cost recorded: reservations never touch it (BR-RSV-02).
        DB::table('lot_cost_balances')->insert(['lot_id' => $lot, 'remaining_qty_base' => $total]);

        return $lot;
    }

    /** The product's guard: on hand, unusable, reserved — the strings PostgreSQL returns. */
    public static function guard(int $productId): object
    {
        return DB::table('stock_product_balances')->where('product_id', $productId)->first(['on_hand_qty', 'unusable_qty', 'reserved_qty']) ?? (object) [];
    }

    /** ROUTE-184's action as its own command: `COMMITTED`, or the refusal's kind and code. */
    public static function reserve(int $actorUserId, int $lineId, string $qty): string
    {
        $line = DB::table('project_lines as l')->join('projects as p', 'p.id', '=', 'l.project_id')->where('l.id', $lineId)->first(['l.company_id', 'p.public_id']) ?? throw new RuntimeException("No line {$lineId}.");

        return self::word(app(Reserve::class)->run((string) Str::uuid(), $actorUserId, (int) $line->company_id, (string) $line->public_id, $lineId, self::handle($lineId), $qty));
    }

    /**
     * A command's transaction held open once it has run, until another session waits on its locks — the interleaving of
     * M-02's barrier before LK-13 —, then committed.
     *
     * @param  Closure(): string  $command
     */
    public static function hold(Closure $command, string $point): string
    {
        return DB::transaction(static function () use ($command, $point): string {
            $word = $command();
            Barrier::await($point);
            Sessions::awaitBlocked();

            return $word;
        });
    }

    /** ROUTE-185's action as its own command: "Kurangi Reservasi" by `qty`, or "Lepas" without one. */
    public static function release(int $actorUserId, int $reservationId, ?string $qty, string $reason = 'Proyek menunda pengiriman'): string
    {
        $company = (int) DB::table('reservations')->where('id', $reservationId)->value('company_id');

        return self::word(app(ReleaseReservation::class)->run((string) Str::uuid(), $actorUserId, $company, $reservationId, RowHandle::of('reservations', $reservationId), $qty, $reason));
    }

    /**
     * A synthetic dispatch of `qty` to the line (TASK-041 builds the command): its dispatch line, the line's reservation
     * consumed first (AX-03; CONSUME), the product guard's RESERVED lowered by what it consumed, under AX-03's line locks.
     */
    public static function consume(int $actorUserId, int $lineId, string $qty): string
    {
        $company = (int) DB::table('project_lines')->where('id', $lineId)->value('company_id');

        return self::word(self::run($actorUserId, $company, InventoryPosting::lineLocks($lineId), static function (CommandContext $context) use ($lineId, $qty): void {
            $active = self::active($lineId);
            $remaining = $active === 0 ? '0' : (string) DB::table('reservations')->where('id', $active)->value('remaining_qty_base');
            $take = BigDecimal::min(BigDecimal::of($qty), BigDecimal::of($remaining));
            $dispatch = DispatchLine::factory()->create(['project_line_id' => $lineId, 'reservation_id' => $take->isPositive() ? $active : null, 'entered_qty' => $qty, 'qty_base' => $qty,
                'reserved_qty_base' => (string) $take, 'unreserved_qty_base' => (string) BigDecimal::of($qty)->minus($take)])->id;
            $consumed = app(InventoryPosting::class)->consume($context, $lineId, $qty, $dispatch);
            $product = (int) DB::table('project_lines')->where('id', $lineId)->value('product_id');

            if (BigDecimal::of($consumed)->isPositive()) {
                GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $product], 'deltas' => ['reserved_qty' => '-'.$consumed]]]);
            }
        }));
    }

    /**
     * A synthetic supply reduction (TASK-039 and TASK-042 build its commands): `qty` of the lot's usable units turned
     * UNUSABLE by a CONDITION_CHANGE movement, the actor's chosen cuts committed with it through the cut service (IP-20;
     * BR-RSV-04) — none chosen, the product guard's CHECK decides alone (C-04).
     *
     * @param  array<int, string>  $cuts  reservation => quantity cut
     */
    public static function reduceSupply(int $actorUserId, int $companyId, int $productId, int $lot, int $location, string $qty, array $cuts, string $capability = 'stock.reserve'): string
    {
        $locks = InventoryPosting::cutLocks($productId, array_keys($cuts));

        return self::word(self::run($actorUserId, $companyId, $locks, static function (CommandContext $context) use ($productId, $lot, $location, $qty, $cuts, $companyId): void {
            $movement = StockMovementFactory::post('CONDITION_CHANGE', $companyId);
            $reserved = $cuts === [] ? '0' : app(InventoryPosting::class)->cut($context, $productId, $cuts, $qty, $movement, 'INV.ConditionChange');
            // Both entries in one statement, so the movement's balance (C-07) is read with both, whatever constraint mode is set.
            $entry = fn (string $condition, string $delta): array => StockMovementEntry::factory()->make(['lot_id' => $lot, 'product_id' => $productId, 'stock_movement_id' => $movement,
                'movement_type' => 'CONDITION_CHANGE', 'location_id' => $location, 'condition' => $condition, 'qty_delta_base' => $delta])->getAttributes();
            DB::table('stock_movement_entries')->insert([$entry('USABLE', '-'.$qty), $entry('UNUSABLE', $qty)]);
            DB::table('stock_lot_balances')->where(['lot_id' => $lot, 'location_id' => $location, 'condition' => 'USABLE'])->update(['qty' => DB::raw("qty - cast('{$qty}' as numeric)")]);
            self::place($lot, $productId, $location, 'UNUSABLE');
            DB::table('stock_lot_balances')->where(['lot_id' => $lot, 'location_id' => $location, 'condition' => 'UNUSABLE'])->update(['qty' => DB::raw("qty + cast('{$qty}' as numeric)")]);
            // One statement, so the CHECK reads RESERVED and UNUSABLE together (C-04; C-06).
            GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $productId], 'deltas' => ['reserved_qty' => $reserved, 'unusable_qty' => $qty]]]);
        }, $capability));
    }

    /** A posting run in the command envelope as the actor, holding `locks` (CONCURRENCY_IDEMPOTENCY §5). */
    public static function run(int $actorUserId, int $companyId, LockSet $locks, Closure $work, string $capability = 'stock.reserve'): Outcome
    {
        $request = new CommandRequest((string) Str::uuid(), 'test.inventory', $actorUserId, $companyId, $capability, Fingerprint::of('test.inventory', null, ['run' => (string) Str::uuid()]));

        return app(CommandEnvelope::class)->run($request, static function (CommandContext $context) use ($locks, $work): CommandResult {
            $context->lock($locks);
            $work($context);

            return new CommandResult('test', 0);
        });
    }

    public static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }

    private static function entry(int $lot, int $productId, int $location, int $movement, string $type, string $qty, string $condition): void
    {
        StockMovementEntry::factory()->create(['lot_id' => $lot, 'product_id' => $productId, 'stock_movement_id' => $movement, 'movement_type' => $type, 'location_id' => $location,
            'condition' => $condition, 'qty_delta_base' => $qty]);
        self::place($lot, $productId, $location, $condition);
        DB::table('stock_lot_balances')->where(['lot_id' => $lot, 'location_id' => $location, 'condition' => $condition])->update(['qty' => DB::raw("qty + cast('{$qty}' as numeric)")]);

        if (! DB::table('stock_product_balances')->where('product_id', $productId)->exists()) {
            DB::table('stock_product_balances')->insert(['product_id' => $productId]);
        }

        DB::table('stock_product_balances')->where('product_id', $productId)->update([
            'on_hand_qty' => DB::raw("on_hand_qty + cast('{$qty}' as numeric)"),
            'unusable_qty' => DB::raw($condition === 'UNUSABLE' ? "unusable_qty + cast('{$qty}' as numeric)" : 'unusable_qty'),
        ]);
    }

    private static function place(int $lot, int $productId, int $location, string $condition): void
    {
        if (! DB::table('stock_lot_balances')->where(['lot_id' => $lot, 'location_id' => $location, 'condition' => $condition])->exists()) {
            DB::table('stock_lot_balances')->insert(['lot_id' => $lot, 'location_id' => $location, 'condition' => $condition, 'product_id' => $productId]);
        }
    }
}
