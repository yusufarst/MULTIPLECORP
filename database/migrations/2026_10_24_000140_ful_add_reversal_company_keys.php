<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A delivery line's and a handover line's reversal held within the original's company as well as to its exact
// quantity (C-30; C-32; §15): each composite key gains `company_id`, so a reversal recorded in one company
// can no longer cite another company's line of the same quantity. The company-free keys they replace go.
return new class extends Migration
{
    /** @var array<string, array{string, string, string, string, string}> table => [reversal column, old key, new key, old foreign, new foreign] */
    private const REVERSALS = [
        'delivery_lines' => ['reverses_delivery_line_id', 'delivery_lines_c30_quantity_key', 'delivery_lines_c32_quantity_key', 'delivery_lines_c30_reverses_foreign', 'delivery_lines_c32_reverses_foreign'],
        'service_handover_lines' => ['reverses_line_id', 'service_handover_lines_c30_quantity_key', 'service_handover_lines_c32_quantity_key', 'service_handover_lines_c30_reverses_foreign', 'service_handover_lines_c32_reverses_foreign'],
    ];

    public function up(): void
    {
        foreach (self::REVERSALS as $table => [$column, $oldKey, $newKey, $oldForeign, $newForeign]) {
            TableRules::for($table)->unique($newKey, ['company_id', 'id', 'qty_base']);
            DB::statement("alter table {$table} drop constraint {$oldForeign}");
            TableRules::for($table)->foreign($newForeign, ['company_id', $column, 'qty_base'], $table, ['company_id', 'id', 'qty_base']);
            DB::statement("alter table {$table} drop constraint {$oldKey}");
        }
    }

    public function down(): void
    {
        foreach (self::REVERSALS as $table => [$column, $oldKey, $newKey, $oldForeign, $newForeign]) {
            TableRules::for($table)->unique($oldKey, ['id', 'qty_base']);
            DB::statement("alter table {$table} drop constraint {$newForeign}");
            TableRules::for($table)->foreign($oldForeign, [$column, 'qty_base'], $table, ['id', 'qty_base']);
            DB::statement("alter table {$table} drop constraint {$newKey}");
        }
    }
};
