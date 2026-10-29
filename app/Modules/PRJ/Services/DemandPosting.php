<?php

namespace App\Modules\PRJ\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\GuardUpdate;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * PRJ's posting service of a line guard's served quantities for the tiers above it (ARCHITECTURE §7; DATABASE §9): INV's
 * reservations move a warehouse line's reserved quantity through it, each line once per command by its net delta
 * (GU-01), the mode's CHECK set refusing a move beyond the line's demand (C-05; C-30).
 */
final class DemandPosting
{
    /**
     * LK-19 for the lines' guards.
     *
     * @param  list<int>  $lineIds
     */
    public static function locks(array $lineIds): LockSet
    {
        $set = new LockSet;

        foreach (array_unique($lineIds) as $line) {
            $set->add('project_line_balances', ['project_line_id' => $line]);
        }

        return $set;
    }

    /**
     * The lines' reserved quantities moved by their net deltas.
     *
     * @param  array<int, string>  $deltas  project line => signed base quantity
     */
    public function reserved(CommandContext $context, array $deltas): void
    {
        $rows = [];

        foreach ($deltas as $line => $delta) {
            if (preg_match('/^-?\d+(\.\d+)?$/', $delta) !== 1) {
                throw new InvalidArgumentException("{$delta} is no reserved quantity's delta (DATABASE §9).");
            }

            if (! BigDecimal::of($delta)->isZero()) {
                $rows[] = ['key' => ['project_line_id' => $line], 'deltas' => ['reserved_qty_base' => $delta]];
            }
        }

        if ($rows !== []) {
            GuardUpdate::apply($context->connection, 'project_line_balances', $rows);
        }
    }
}
