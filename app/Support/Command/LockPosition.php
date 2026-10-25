<?php

namespace App\Support\Command;

use LogicException;

/**
 * The highest canonical position a transaction holds (LR-01): every further lock sorts after it, and a row is
 * locked once (LR-04). A first-use row created at its position advances it too (LR-07).
 */
final class LockPosition
{
    /** @var array{0: int, 1: int, 2: int, 3: array<string, int|string>}|null */
    private ?array $highest = null;

    /** @var array<string, true> */
    private array $held = [];

    public function holds(string $identity): bool
    {
        return isset($this->held[$identity]);
    }

    /**
     * Whether a row sorts before the highest one held — by class, table, key kind, then key.
     *
     * @param  array<string, int|string>  $key
     */
    public function sortsBefore(LockClass $class, int $tableIndex, int $kind, array $key): bool
    {
        if ($this->highest === null) {
            return false;
        }

        [$heldClass, $heldIndex, $heldKind, $heldKey] = $this->highest;
        $order = [$class->value, $tableIndex, $kind] <=> [$heldClass, $heldIndex, $heldKind];

        return $order < 0 || ($order === 0 && LockSet::compareKeys($key, $heldKey) < 0);
    }

    /**
     * @param  array<string, int|string>  $key
     */
    public function advance(LockClass $class, int $tableIndex, int $kind, array $key, string $identity): void
    {
        if ($this->sortsBefore($class, $tableIndex, $kind, $key)) {
            throw new LogicException("A lock on {$identity} sorts before one already held (LR-01).");
        }

        $this->highest = [$class->value, $tableIndex, $kind, $key];
        $this->held[$identity] = true;
    }
}
