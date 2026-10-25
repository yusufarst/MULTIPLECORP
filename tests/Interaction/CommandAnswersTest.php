<?php

use App\Modules\CAT\Models\Product;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Commands\TestCommands;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-013): the server answers of the shared interactions INT-015–INT-017: a duplicate warning refuses
// with the current matches and admits the new intent that acknowledges them with a reason, refusing again a
// match that appeared meanwhile (IP-05); a CONFLICT replays as recorded, never re-applied without a new intent
// (IP-03); a FAILED command resent under the same `command_id` runs once (IP-04).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = Authorization::owner()->id;
});

function warned(int $actor, array $matches, array $acknowledged, ?string $reason): Outcome
{
    $type = 'test.warned';

    return app(CommandEnvelope::class)->run(new CommandRequest((string) Str::uuid7(), $type, $actor, null, 'payment.record', Fingerprint::of($type, null, ['acknowledged' => $acknowledged, 'reason' => $reason])), function ($context) use ($matches, $acknowledged, $reason) {
        $context->lock(new LockSet);
        DuplicateWarning::check('BD-02', $matches, $acknowledged, $reason);

        return new CommandResult;
    });
}

it('refuses a likely duplicate with its matches and admits the intent that acknowledges them (INT-015; IP-05)', function () {
    $first = warned($this->owner, ['m-1'], [], null);
    $acknowledged = warned($this->owner, ['m-1'], ['m-1'], 'Transfer terpisah dari klien');
    $appeared = warned($this->owner, ['m-1', 'm-2'], ['m-1'], 'Transfer terpisah dari klien');

    expect([$first->kind, $first->code, $first->state])->toBe([Outcome::REJECTED, 'BD-02', ['matches' => ['m-1']]])
        ->and($acknowledged->kind)->toBe(Outcome::COMMITTED)
        ->and([$appeared->kind, $appeared->state])->toBe([Outcome::REJECTED, ['matches' => ['m-1', 'm-2']]]);
});

it('replays a CONFLICT as recorded, never applying the change without a new intent (INT-016; IP-03)', function () {
    $id = (string) Str::uuid7();
    $type = 'test.stale';
    $run = fn () => app(CommandEnvelope::class)->run(new CommandRequest($id, $type, $this->owner, null, 'projects.manage', Fingerprint::of($type, null, ['lock_version' => 1])), function ($context) {
        $context->lock(new LockSet);

        throw Refused::conflict('ST-01', ['lock_version' => 2]);
    });

    $first = $run();
    $again = $run();

    expect([$first->kind, $first->code, $first->replayed])->toBe([Outcome::CONFLICT, 'ST-01', false])
        ->and([$again->kind, $again->code, $again->replayed])->toBe([Outcome::CONFLICT, 'ST-01', true]);
});

it('runs a FAILED command once when it is resent under the same identity (INT-017; IP-04)', function () {
    $product = Product::factory()->create()->id;
    $id = (string) Str::uuid7();
    $move = [['table' => 'stock_product_balances', 'key' => ['product_id' => $product], 'deltas' => ['on_hand_qty' => -1]]];

    expect(TestCommands::move($id, $this->owner, null, 'stock.dispatch', $move)->kind)->toBe(Outcome::FAILED);

    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 3]);

    expect(TestCommands::move($id, $this->owner, null, 'stock.dispatch', $move)->kind)->toBe(Outcome::COMMITTED)
        ->and(TestCommands::move($id, $this->owner, null, 'stock.dispatch', $move)->replayed)->toBeTrue()
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('2.000');
});
