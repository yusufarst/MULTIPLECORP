<?php

use App\Modules\CAT\Models\Product;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\FIN\Models\Payment;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\Location;
use App\Modules\ORG\Models\Company;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandId;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Clock;
use Tests\Support\Commands\TestCommands;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-013): the envelope on a sample command (CONCURRENCY_IDEMPOTENCY §6, §10–§13): a commit and its
// replay, never twice; another actor's or another content's identity a CONFLICT disclosing nothing; a refusal
// committing only its terminal row; a missing guard, a defect and a spent budget FAILED with nothing recorded;
// a lock set that grew restarting the action; a driven identity recording only COMMITTED; SF-DATE and C-50.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = Authorization::owner()->id;
    $this->product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $this->product, 'on_hand_qty' => 3]);
    $this->dispatch = fn (string $id, int $actor, int $quantity = 1) => TestCommands::move($id, $actor, null, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -$quantity]]]);
    $this->onHand = fn (): string => (string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty');
});

it('commits once and replays the recorded outcome to the same intent (CI-03, CI-05; HO-02)', function () {
    $id = (string) Str::uuid7();

    $first = ($this->dispatch)($id, $this->owner);
    $again = ($this->dispatch)($id, $this->owner);

    expect($first->kind)->toBe(Outcome::COMMITTED)->and($first->replayed)->toBeFalse()
        ->and($again->kind)->toBe(Outcome::COMMITTED)->and($again->replayed)->toBeTrue()
        ->and(($this->onHand)())->toBe('2.000')
        ->and(DB::table('command_log')->where('command_id', $id)->value('state'))->toBe('COMMITTED')
        ->and(DB::table('command_log')->where('command_id', $id)->value('expires_at'))->not->toBeNull();
});

it('answers another actor\'s or another content\'s identity with a CONFLICT that discloses nothing (CI-05; DP-16)', function () {
    $id = (string) Str::uuid7();
    ($this->dispatch)($id, $this->owner);

    $other = ($this->dispatch)($id, Authorization::owner()->id);
    $changed = ($this->dispatch)($id, $this->owner, 2);

    expect([$other->kind, $other->code, $other->resultType, $other->state])->toBe([Outcome::CONFLICT, 'CI-05', null, []])
        ->and([$changed->kind, $changed->code])->toBe([Outcome::CONFLICT, 'CI-05'])
        ->and(($this->onHand)())->toBe('2.000');
});

it('re-authorizes a replay under the actor\'s row: a revoked capability answers the denial, never the recorded outcome (CI-05; TL-06)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $id = (string) Str::uuid7();
    $move = fn () => TestCommands::move($id, $admin->id, $company->id, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]);

    expect($move()->kind)->toBe(Outcome::COMMITTED);

    Authorization::revoke($admin, 'stock.dispatch');

    expect($move)->toThrow(AuthorizationException::class);
});

it('commits a refusal\'s terminal row and nothing of the action (D-CC-05; SQ-02, SQ-04)', function () {
    $id = (string) Str::uuid7();

    $refused = ($this->dispatch)($id, $this->owner, 4);
    $replayed = ($this->dispatch)($id, $this->owner, 4);

    expect([$refused->kind, $refused->code])->toBe([Outcome::REJECTED, 'C-04'])
        ->and([$replayed->kind, $replayed->code, $replayed->replayed])->toBe([Outcome::REJECTED, 'C-04', true])
        ->and(($this->onHand)())->toBe('3.000')
        ->and(DB::table('command_log')->where('command_id', $id)->value('outcome_code'))->toBe('C-04');
});

it('answers a missing guard FAILED with nothing recorded, and runs the same identity again later (GU-02; SQ-18; INT-017)', function () {
    $id = (string) Str::uuid7();
    $missing = Product::factory()->create()->id;
    $failed = TestCommands::move($id, $this->owner, null, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $missing], 'deltas' => ['on_hand_qty' => -1]]]);

    expect([$failed->kind, $failed->code])->toBe([Outcome::FAILED, 'SQ-18 guard missing'])
        ->and(DB::table('command_log')->where('command_id', $id)->exists())->toBeFalse();

    DB::table('stock_product_balances')->insert(['product_id' => $missing, 'on_hand_qty' => 1]);

    expect(TestCommands::move($id, $this->owner, null, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $missing], 'deltas' => ['on_hand_qty' => -1]]])->kind)->toBe(Outcome::COMMITTED);
});

it('restarts an action whose lock set grew under its locks, with the union of its sets (§5; RY-03)', function () {
    $other = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $other, 'on_hand_qty' => 1]);
    $runs = 0;
    $type = 'test.restart';

    $outcome = app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) use (&$runs, $other) {
        $runs++;
        $context->lock((new LockSet)->add('stock_product_balances', ['product_id' => $this->product]));
        // Re-derived under the locks: the action also needs the other product.
        $context->require((new LockSet)->add('stock_product_balances', ['product_id' => $other]));
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $other], 'deltas' => ['on_hand_qty' => -1]]]);

        return new CommandResult;
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)->and($runs)->toBe(2);
});

it('records only the COMMITTED outcome of a driven identity, its refusal left to its driver row (CI-08)', function () {
    $refused = TestCommands::move(CommandId::driven('job', 'test:1:1'), $this->owner, null, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -4]]]);

    expect($refused->kind)->toBe(Outcome::REJECTED)
        ->and(DB::table('command_log')->count())->toBe(0);
});

it('refuses a future business date at the command and a stale prefilled one, an earlier real date accepted (SF-DATE; C-50; ST-08)', function () {
    Clock::freeze('utc-previous-day');
    $type = 'test.dated';
    $run = fn (string $date, bool $prefilled) => app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, ['business_date' => $date, 'prefilled' => $prefilled])), function ($context) use ($date, $prefilled) {
        $context->lock(new LockSet);
        BusinessDateRule::check($date, $prefilled);

        return new CommandResult;
    });

    expect([$run('2026-02-02', false)->kind, $run('2026-02-02', false)->code])->toBe([Outcome::REJECTED, 'C-50'])
        ->and([$run('2026-01-31', true)->kind, $run('2026-01-31', true)->code])->toBe([Outcome::CONFLICT, 'ST-08'])
        ->and($run('2026-01-15', false)->kind)->toBe(Outcome::COMMITTED)
        ->and($run('2026-02-01', true)->kind)->toBe(Outcome::COMMITTED);
});

it('refuses a direct write of a future business date by C-50\'s trigger, an insert and a change alike', function () {
    $future = now('Asia/Jakarta')->addDays(2)->toDateString();
    // A draft document version's date is one the runtime may change (DATABASE §28).
    $draft = DocumentVersion::factory()->create();

    Refusal::assert(Refusal::CHECK, 'c50_business_date_not_future', fn () => Payment::factory()->create(['business_date' => $future]));
    Refusal::assert(Refusal::CHECK, 'c50_business_date_not_future', fn () => DB::table('document_versions')->where('id', $draft->id)->update(['business_date' => $future]));
    expect(DB::table('document_versions')->where('id', $draft->id)->update(['business_date' => now('Asia/Jakarta')->subDays(3)->toDateString()]))->toBe(1);
});

it('keeps a command\'s outcome when work after its commit fails, and logs it (SQ-21)', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged) {
        $logged[] = $message->message;
    });
    $type = 'test.after';

    $outcome = app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) {
        $context->lock(new LockSet);
        $context->afterCommit(fn () => throw new RuntimeException('dispatch failed'));

        return new CommandResult;
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and($logged)->toContain('Work after a command\'s commit failed; its outcome stands (SQ-21).');
});

it('refuses a client identity that is not version 4 or 7 (CI-01)', function (string $id) {
    CommandId::fromClient($id);
})->with(['a v5' => [CommandId::driven('job', 'x:1:1')], 'nil' => ['00000000-0000-0000-0000-000000000000'], 'upper case' => [strtoupper((string) Str::uuid7())], 'malformed' => ['42']])
    ->throws(InvalidArgumentException::class);

it('lets a refusal raised by the action itself commit only its row (Refused)', function () {
    $type = 'test.refuse';
    $outcome = app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) {
        $context->lock((new LockSet)->add('stock_product_balances', ['product_id' => $this->product]));
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]);

        throw Refused::conflict('ST-01', ['lock_version' => 3]);
    });

    expect([$outcome->kind, $outcome->code, $outcome->state])->toBe([Outcome::CONFLICT, 'ST-01', ['lock_version' => 3]])
        ->and(($this->onHand)())->toBe('3.000');
});

it('refuses to commit an action that never re-authorized through its first lock (RV-01; §6 phase B)', function () {
    $id = (string) Str::uuid7();
    $type = 'test.unlocked';

    expect(fn () => app(CommandEnvelope::class)->run(new CommandRequest($id, $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) {
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]);

        return new CommandResult;
    }))->toThrow(LogicException::class, 'RV-01');

    expect(($this->onHand)())->toBe('3.000')
        ->and(DB::table('command_log')->where('command_id', $id)->exists())->toBeFalse();
});

it('re-authorizes a refusal raised before the first lock: a revoked actor gets the denial, never a recorded refusal (RV-01; SQ-22)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $type = 'test.early';
    $refuse = fn (string $id) => app(CommandEnvelope::class)->run(new CommandRequest($id, $type, $admin->id, $company->id, 'stock.dispatch', Fingerprint::of($type, null, [])), function () {
        BusinessDateRule::check(now('Asia/Jakarta')->addDay()->toDateString(), false);

        return new CommandResult;
    });

    expect([$refuse($first = (string) Str::uuid7())->kind, DB::table('command_log')->where('command_id', $first)->value('outcome_code')])->toBe([Outcome::REJECTED, 'C-50']);

    Authorization::revoke($admin, 'stock.dispatch');
    $second = (string) Str::uuid7();

    expect(fn () => $refuse($second))->toThrow(AuthorizationException::class)
        ->and(DB::table('command_log')->where('command_id', $second)->exists())->toBeFalse();
});

it('answers a replay whose recorded company left the actor\'s scope as a missing record (CI-05; AZ-11)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $id = (string) Str::uuid7();
    $move = fn () => TestCommands::move($id, $admin->id, $company->id, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]);

    expect($move()->kind)->toBe(Outcome::COMMITTED);

    DB::table('user_company_grants')->where('user_id', $admin->id)->update(['revoked_at' => now()]);
    Authorization::forget();

    expect($move)->toThrow(ModelNotFoundException::class)
        ->and(($this->onHand)())->toBe('2.000');
});

it('restarts, never fails, when a later lock re-derives a row sorting before one held (§5 step 4; LR-01)', function () {
    $runs = 0;
    $type = 'test.staged';

    $outcome = app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) use (&$runs) {
        $runs++;
        $context->lock((new LockSet)->add('stock_product_balances', ['product_id' => $this->product]));
        // Read under the product's lock: the action also needs a project row, LK-01, before it.
        $context->lock((new LockSet)->add('projects', ['id' => 999_999]));
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]);

        return new CommandResult;
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)->and($runs)->toBe(2)->and(($this->onHand)())->toBe('2.000');
});

it('writes the business audit in the command\'s transaction: committed with it, gone with its refusal (LG-01; criterion 13)', function () {
    $type = 'test.audited';
    $run = fn (bool $refuse) => app(CommandEnvelope::class)->run(new CommandRequest($id = (string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, ['refuse' => $refuse])), function ($context) use ($refuse, $id) {
        $context->lock(new LockSet);
        app(AuditRecorder::class)->record('test.audited', AuthorityClass::OwnerOnly, $this->owner, null, commandId: $id);

        return $refuse ? throw Refused::rejected('C-04') : new CommandResult;
    });

    $run(false);
    $run(true);

    expect(DB::table('audit_events')->where('action', 'test.audited')->count())->toBe(1);
});

it('answers a driven identity whose fact already exists "already done", COMMITTED (SQ-07; CI-08)', function () {
    $type = 'test.redriven';
    $outcome = app(CommandEnvelope::class)->run(new CommandRequest(CommandId::driven('import', 'batch:1:7'), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) {
        $context->lock(new LockSet);
        $previous = new PDOException('duplicate key value violates unique constraint "payments_c48_command_identity"');
        (new ReflectionProperty(Exception::class, 'code'))->setValue($previous, '23505');

        throw new QueryException('pgsql', 'insert into payments', [], $previous);
    });

    expect([$outcome->kind, $outcome->replayed])->toBe([Outcome::COMMITTED, true])
        ->and(DB::table('command_log')->count())->toBe(0);
});

it('reads its clock only once its locks are granted, and takes no new lock after it (phase C; §9)', function () {
    $type = 'test.clock';
    $run = fn (Closure $action) => app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), $action);
    $product = new LockSet;
    $product->add('stock_product_balances', ['product_id' => $this->product]);

    expect(fn () => $run(function ($context) {
        $context->now();

        return new CommandResult;
    }))->toThrow(LogicException::class, 'phase C');

    expect(fn () => $run(function ($context) use ($product) {
        $context->lock(new LockSet);
        $context->now();
        $context->lock($product);

        return new CommandResult;
    }))->toThrow(LogicException::class, 'phase C');

    $stamps = [];
    $outcome = $run(function ($context) use ($product, &$stamps) {
        $context->lock($product);
        $stamps = [$context->now(), $context->now()];
        // A lock already held after the clock is no new lock.
        $context->lock($product);

        return new CommandResult;
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and($stamps[0])->toBe($stamps[1])
        ->and(DB::table('command_log')->where('command_type', $type)->count())->toBe(1);
});

it('replays a refusal with the current state its rule concerns, resolved after the checks, left out when it no longer resolves (CI-05)', function () {
    $type = 'test.duplicate';
    $id = (string) Str::uuid7();
    $current = ['P-1', 'P-2'];
    $run = fn (?Closure $replayState) => app(CommandEnvelope::class)->run(new CommandRequest($id, $type, $this->owner, null, 'stock.dispatch', Fingerprint::of($type, null, ['acknowledged' => []])), function ($context) {
        $context->lock(new LockSet);
        DuplicateWarning::check('BD-02', ['P-1'], [], null);

        return new CommandResult;
    }, replayState: $replayState);

    $first = $run(null);
    $resolved = $run(fn (Outcome $outcome) => $outcome->code === 'BD-02' ? ['matches' => $current] : null);
    $gone = $run(fn () => null);
    $unresolved = $run(null);

    expect([$first->kind, $first->code, $first->state, $first->replayed])->toBe([Outcome::REJECTED, 'BD-02', ['matches' => ['P-1']], false])
        ->and([$resolved->kind, $resolved->code, $resolved->state, $resolved->replayed])->toBe([Outcome::REJECTED, 'BD-02', ['matches' => ['P-1', 'P-2']], true])
        ->and($gone->state)->toBe([])
        ->and($unresolved->state)->toBe([]);

    // Another actor's identity resolves nothing (DP-16).
    $other = app(CommandEnvelope::class)->run(new CommandRequest($id, $type, Authorization::owner()->id, null, 'stock.dispatch', Fingerprint::of($type, null, ['acknowledged' => []])), fn () => new CommandResult, replayState: fn () => throw new LogicException('resolved for another actor'));

    expect([$other->kind, $other->code, $other->state])->toBe([Outcome::CONFLICT, 'CI-05', []]);
});

it('binds an integer guard key given as its decimal string as the integer, a textual key as text (GU-01)', function () {
    $lot = InventoryLot::factory()->create();
    $location = Location::factory()->create()->id;
    DB::table('stock_lot_balances')->insert(['lot_id' => $lot->id, 'location_id' => $location, 'condition' => 'USABLE', 'product_id' => $lot->product_id, 'qty' => 2]);

    DB::transaction(function () use ($lot, $location) {
        GuardUpdate::apply(DB::connection(), 'stock_product_balances', [['key' => ['product_id' => (string) $this->product], 'deltas' => ['on_hand_qty' => -1]]]);
        GuardUpdate::apply(DB::connection(), 'stock_lot_balances', [['key' => ['lot_id' => (string) $lot->id, 'location_id' => $location, 'condition' => 'USABLE'], 'deltas' => ['qty' => -1]]]);
    });

    expect(($this->onHand)())->toBe('2.000')
        ->and((string) DB::table('stock_lot_balances')->where('lot_id', $lot->id)->value('qty'))->toBe('1.000')
        ->and(fn () => GuardUpdate::apply(DB::connection(), 'stock_product_balances', [['key' => ['product_id' => (string) $this->product], 'deltas' => ['on_hand_qty' => -1]], ['key' => ['product_id' => $this->product], 'deltas' => ['on_hand_qty' => -1]]]))->toThrow(InvalidArgumentException::class, 'once');
});
