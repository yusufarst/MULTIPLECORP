<?php

namespace Tests\Support\Commands;

use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\GuardUpdate;
use Closure;

/**
 * The envelope's test commands (TASK-013; CONCURRENCY_IDEMPOTENCY §18 "on test commands"): each locks its guard
 * rows in canonical order through the envelope and moves them by one guard statement per table, as a posting
 * service will, so the races of M-01, M-02 and the architecture challenge run on real PostgreSQL before their
 * actions exist. A command answers its Outcome kind and code as text, so a session in another process can
 * return it.
 */
final class TestCommands
{
    /**
     * @param  list<array{table: string, key: array<string, int|string>, deltas: array<string, int|string>}>  $moves  in canonical order
     * @param  Closure(): void|null  $whileHeld  run once every lock is held, before the writes
     * @param  Closure(): void|null  $observer  run after each lock statement and after the writes (HO-13)
     */
    public static function move(string $commandId, int $actorUserId, ?int $companyId, string $capability, array $moves, ?Closure $whileHeld = null, string $type = 'test.move', ?Closure $observer = null): Outcome
    {
        $request = new CommandRequest($commandId, $type, $actorUserId, $companyId, $capability, Fingerprint::of($type, null, ['moves' => $moves]));
        $statementObserver = $observer === null ? null : fn (string $table, array $key) => $observer();

        return app(CommandEnvelope::class)->run($request, function ($context) use ($moves, $whileHeld, $observer) {
            $set = new LockSet;

            foreach ($moves as $move) {
                $set->add($move['table'], $move['key']);
            }

            $context->lock($set);
            $whileHeld?->__invoke();

            foreach ($moves as $move) {
                GuardUpdate::apply($context->connection, $move['table'], [['key' => $move['key'], 'deltas' => $move['deltas']]]);
            }

            $observer?->__invoke();

            return new CommandResult('test', null);
        }, $statementObserver);
    }

    /**
     * Takes one unit of the product under its lock and answers the instant the command read after its locks
     * (phase C; §9), for a session to return.
     */
    public static function stamped(string $commandId, int $actorUserId, int $productId): string
    {
        $stamp = '';
        $type = 'test.stamped';

        app(CommandEnvelope::class)->run(new CommandRequest($commandId, $type, $actorUserId, null, 'stock.dispatch', Fingerprint::of($type, null, [])), function ($context) use ($productId, &$stamp) {
            $context->lock((new LockSet)->add('stock_product_balances', ['product_id' => $productId]));
            GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => $productId], 'deltas' => ['on_hand_qty' => -1]]]);
            $stamp = $context->now()->format('Y-m-d H:i:s.u');

            return new CommandResult('test', null);
        });

        return $stamp;
    }

    /** The outcome as `KIND` or `KIND code`, for a session to return. */
    public static function text(Outcome $outcome): string
    {
        return trim($outcome->kind.' '.($outcome->code ?? ''));
    }
}
