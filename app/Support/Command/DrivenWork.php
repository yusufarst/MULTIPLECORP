<?php

namespace App\Support\Command;

use Closure;

/**
 * The work a driver of a driven command adds to it (CI-01; CI-08; AX-28): the import pipeline invokes a module's
 * ordinary action under the row's deterministic identity and registers, for that identity, the writes its row owes —
 * the row claimed under its lock, its state and result —, which the envelope runs inside the action's transaction
 * after the action's own effects, so the fact and its row commit together or not at all.
 */
final class DrivenWork
{
    /** @var array<string, Closure(CommandContext, CommandResult): void> */
    private static array $drivers = [];

    /**
     * @template T
     *
     * @param  Closure(CommandContext, CommandResult): void  $driver
     * @param  Closure(): T  $run
     * @return T
     */
    public static function within(string $commandId, Closure $driver, Closure $run): mixed
    {
        self::$drivers[$commandId] = $driver;

        try {
            return $run();
        } finally {
            unset(self::$drivers[$commandId]);
        }
    }

    /** The driver's writes for this command, when one drives it. */
    public static function finish(CommandContext $context, CommandResult $result): void
    {
        $driver = self::$drivers[$context->request->commandId] ?? null;

        if ($driver !== null) {
            $driver($context, $result);
        }
    }
}
