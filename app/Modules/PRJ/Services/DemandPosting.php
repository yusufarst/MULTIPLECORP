<?php

namespace App\Modules\PRJ\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\GuardUpdate;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * PRJ's posting service of a line guard's served quantities for the tiers above it (ARCHITECTURE §7; DATABASE §9): INV's
 * reservations move a warehouse line's reserved quantity through it and FUL's handovers a service line's handed-over
 * quantity, each line once per command by its net delta (GU-01), the mode's CHECK set refusing a move beyond the line's
 * demand (C-05; C-30).
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
        $this->post($context, 'reserved_qty_base', $deltas);
    }

    /**
     * The service lines' handed-over quantities moved by their net deltas (L-39; C-30).
     *
     * @param  array<int, string>  $deltas  project line => signed base quantity
     */
    public function handedOver(CommandContext $context, array $deltas): void
    {
        $this->post($context, 'handed_over_qty_base', $deltas);
    }

    /** The project's invoiced sales value moved by its net delta within the cap's CHECK (L-16; C-31), the one writer of the column. */
    public function invoiced(CommandContext $context, int $projectId, string $delta): void
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', $delta) !== 1) {
            throw new InvalidArgumentException("{$delta} is no invoiced value's delta (DATABASE §9).");
        }

        if (! BigDecimal::of($delta)->isZero()) {
            GuardUpdate::apply($context->connection, 'project_commercial_balances', [['key' => ['project_id' => $projectId], 'deltas' => ['invoiced_sales_value' => $delta]]]);
        }
    }

    /** @param  array<int, string>  $deltas */
    private function post(CommandContext $context, string $column, array $deltas): void
    {
        $rows = [];

        foreach ($deltas as $line => $delta) {
            if (preg_match('/^-?\d+(\.\d+)?$/', $delta) !== 1) {
                throw new InvalidArgumentException("{$delta} is no {$column} delta (DATABASE §9).");
            }

            if (! BigDecimal::of($delta)->isZero()) {
                $rows[] = ['key' => ['project_line_id' => $line], 'deltas' => [$column => $delta]];
            }
        }

        if ($rows !== []) {
            GuardUpdate::apply($context->connection, 'project_line_balances', $rows);
        }
    }
}
