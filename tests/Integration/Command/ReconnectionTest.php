<?php

use App\Modules\CAT\Models\Product;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\GuardUpdate;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-013): HO-20's FU-10 behaviour — outside a transaction the framework reconnects by itself when its
// session is lost, so a session-level registration key taken before the transaction is silently gone with the
// old session; FU-10's registration therefore takes its keys and its transaction on one session it verifies.
// And SQ-16: a session lost under a command ends its transaction, and the envelope runs the action again on a
// new session, its effect once.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('reconnects by itself outside a transaction, losing a session-level advisory key (HO-20; FU-10)', function () {
    DB::select('select pg_advisory_lock(7101, 42)');
    $session = (int) DB::scalar('select pg_backend_pid()');

    // Another session of the same role ends the first one, as a lost connection would.
    $other = DB::connection(SecurityEventRecorder::CONNECTION);
    $other->select('select pg_terminate_backend(?)', [$session]);

    expect((int) DB::scalar('select pg_backend_pid()'))->not->toBe($session)
        ->and($other->scalar('select pg_try_advisory_lock(7101, 42)'))->toBeTrue();

    $other->select('select pg_advisory_unlock(7101, 42)');
});

it('runs a command again on a new session when its session is lost before COMMIT, its effect once (SQ-16; RY-01)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner()->id;
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 3]);
    $sessions = [];
    $type = 'test.lost';
    $id = (string) Str::uuid7();

    $outcome = app(CommandEnvelope::class)->run(new CommandRequest($id, $type, $owner, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) use (&$sessions, $product) {
        $context->lock((new LockSet)->add('stock_product_balances', ['product_id' => $product]));
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $product], 'deltas' => ['on_hand_qty' => -1]]]);
        $sessions[] = (int) $context->connection->scalar('select pg_backend_pid()');

        if (count($sessions) === 1) {
            DB::connection(SecurityEventRecorder::CONNECTION)->select('select pg_terminate_backend(?, 5000)', [$sessions[0]]);
        }

        return new CommandResult;
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and($sessions)->toHaveCount(2)
        ->and($sessions[1])->not->toBe($sessions[0])
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('2.000')
        ->and(DB::table('command_log')->where('command_id', $id)->value('state'))->toBe('COMMITTED');
});
