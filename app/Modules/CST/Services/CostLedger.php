<?php

namespace App\Modules\CST\Services;

use App\Support\Command\CommandContext;
use App\Support\GuardUpdate;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * One posting's writer of CST rows (ARCHITECTURE §7; DATABASE §4.8): the append-only cost rows — `lot_cost_entries`,
 * `cost_attributions`, `expenses` — each with the command's identity and its own line, and the guard deltas they
 * imply, combined per row and written at the end in one statement per guard table (GU-01), so no intermediate state is
 * ever checked and a CHECK the posting breaks refuses it whole (SQ-04).
 */
final class CostLedger
{
    /** @var array<string, int> the next command line per table */
    private array $lines = [];

    /** @var array<string, array<string, array{key: array<string, int>, deltas: array<string, BigDecimal>}>> table => row => deltas */
    private array $deltas = [];

    public function __construct(private readonly CommandContext $context) {}

    /** @param  array<string, mixed>  $row */
    public function entry(array $row): int
    {
        return $this->insert('lot_cost_entries', $row);
    }

    /** @param  array<string, mixed>  $row */
    public function attribution(array $row): int
    {
        return $this->insert('cost_attributions', $row);
    }

    /** @param  array<string, mixed>  $row */
    public function expense(array $row): int
    {
        return $this->insert('expenses', $row);
    }

    /**
     * A guard row's signed deltas, added to what this posting already moves on that row.
     *
     * @param  array<string, int>  $key
     * @param  array<string, string>  $deltas  column => signed decimal
     */
    public function move(string $table, array $key, array $deltas): void
    {
        $identity = json_encode($key, JSON_THROW_ON_ERROR);
        $row = $this->deltas[$table][$identity] ?? ['key' => $key, 'deltas' => []];

        foreach ($deltas as $column => $delta) {
            $row['deltas'][$column] = ($row['deltas'][$column] ?? BigDecimal::zero())->plus($delta);
        }

        $this->deltas[$table][$identity] = $row;
    }

    /**
     * The deltas this posting moves on a row so far, for a read that must see them before the flush.
     *
     * @param  array<string, int>  $key
     */
    public function moved(string $table, array $key, string $column): BigDecimal
    {
        return $this->deltas[$table][json_encode($key, JSON_THROW_ON_ERROR)]['deltas'][$column] ?? BigDecimal::zero();
    }

    /** Writes every guard's combined deltas, one statement per table; rows whose deltas are all zero are not written. */
    public function flush(): void
    {
        foreach ($this->deltas as $table => $rows) {
            $columns = [];

            foreach ($rows as $row) {
                $columns = [...$columns, ...array_keys($row['deltas'])];
            }

            $columns = array_values(array_unique($columns));
            $statement = [];

            foreach ($rows as $row) {
                $deltas = [];

                foreach ($columns as $column) {
                    $deltas[$column] = (string) ($row['deltas'][$column] ?? BigDecimal::zero());
                }

                if (array_filter($deltas, fn (string $delta): bool => ! BigDecimal::of($delta)->isZero()) !== []) {
                    $statement[] = ['key' => $row['key'], 'deltas' => $deltas];
                }
            }

            if ($statement !== []) {
                GuardUpdate::apply($this->context->connection, $table, $statement);
            }
        }

        $this->deltas = [];
    }

    /** @param  array<string, mixed>  $row */
    private function insert(string $table, array $row): int
    {
        $commandId = $this->context->request->commandId;
        $this->lines[$table] ??= DB::table($table)->where('command_id', $commandId)->count() + 1;

        return (int) DB::table($table)->insertGetId([
            ...$row,
            'command_id' => $commandId,
            'command_line_no' => $this->lines[$table]++,
            'recorded_at' => $this->context->now(),
            'recorded_by' => $this->context->request->actorUserId,
        ]);
    }
}
