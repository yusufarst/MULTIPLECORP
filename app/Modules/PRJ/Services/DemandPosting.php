<?php

namespace App\Modules\PRJ\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
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
     * A dispatch's or a restoration's line guard in one statement (GU-01): the net dispatched with the reserved part it consumed.
     */
    public function dispatched(CommandContext $context, int $lineId, string $dispatched, string $reserved = '0'): void
    {
        $deltas = ['dispatched_net_qty_base' => $dispatched];

        if (! BigDecimal::of($reserved)->isZero()) {
            $deltas['reserved_qty_base'] = $reserved;
        }

        GuardUpdate::apply($context->connection, 'project_line_balances', [['key' => ['project_line_id' => $lineId], 'deltas' => $deltas]]);
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

    /**
     * The goods lines' net delivered quantities moved by their net deltas — a delivery, or its exact reversal (AX-09; PX-08; C-30).
     *
     * @param  array<int, string>  $deltas  project line => signed base quantity
     */
    public function delivered(CommandContext $context, array $deltas): void
    {
        $this->post($context, 'delivered_net_qty_base', $deltas);
    }

    /**
     * The drop-ship lines' net confirmed quantities moved by their net deltas — a confirmation, or a return or reversal
     * of one (AX-08; AX-10; PX-08; C-30) —, with their net delivered where the client had received what came back.
     *
     * @param  array<int, string>  $confirmed  project line => signed base quantity
     * @param  array<int, string>  $delivered  project line => signed base quantity
     */
    public function dropship(CommandContext $context, array $confirmed, array $delivered = []): void
    {
        $rows = [];

        // Every row of the one statement names both columns (GU-01); a zero delta leaves its column as it is.
        foreach (array_unique([...array_keys($confirmed), ...array_keys($delivered)]) as $line) {
            $deltas = ['dropship_confirmed_net_qty_base' => $confirmed[$line] ?? '0', 'delivered_net_qty_base' => $delivered[$line] ?? '0'];

            if (! BigDecimal::of($deltas['dropship_confirmed_net_qty_base'])->isZero() || ! BigDecimal::of($deltas['delivered_net_qty_base'])->isZero()) {
                $rows[] = ['key' => ['project_line_id' => $line], 'deltas' => $deltas];
            }
        }

        if ($rows !== []) {
            GuardUpdate::apply($context->connection, 'project_line_balances', $rows);
        }
    }

    /** The project's invoiced sales value moved by its net delta within the cap's CHECK (L-16; C-31), the one writer of the column. */
    /**
     * The confirmed and invoiced sales value of a project's commercial guard; null before any confirmation (L-16).
     *
     * @return array{confirmed: string, invoiced: string}|null
     */
    public static function commercial(int $projectId): ?array
    {
        $row = DB::selectOne('select confirmed_sales_value, invoiced_sales_value from project_commercial_balances where project_id = ?', [$projectId]);

        return is_object($row) ? ['confirmed' => Row::text($row, 'confirmed_sales_value'), 'invoiced' => Row::text($row, 'invoiced_sales_value')] : null;
    }

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
