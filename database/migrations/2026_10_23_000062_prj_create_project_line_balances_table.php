<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `project_line_balances` (GB) and §9: the per-line conservation guard (L-04; PX-03, PX-08),
// posted only by DemandPosting with its facts (§3.1). Every quantity is at least zero, and the mode's
// CHECK set of §9 holds at every commit (C-05, C-30): a warehouse line's reserved, dispatched and cancelled
// quantity within its demand, its delivered within its dispatched and its drop-ship and handover columns
// zero, a drop-ship line's confirmed and
// cancelled within its demand, its delivered within its confirmed and its reserved and dispatched zero,
// a service line's handed over and cancelled within its demand and every other column zero, as §9 states
// each mode's set. The mode is its line's (key).
return new class extends Migration
{
    private const QUANTITIES = [
        'demand_qty_base', 'reserved_qty_base', 'dispatched_net_qty_base', 'dropship_confirmed_net_qty_base', 'handed_over_qty_base',
        'delivered_net_qty_base', 'cancelled_qty_base',
    ];

    public function up(): void
    {
        Schema::create('project_line_balances', function (Blueprint $table) {
            $table->bigInteger('project_line_id')->primary();
            $table->text('fulfillment_mode');

            foreach (self::QUANTITIES as $quantity) {
                Columns::quantity($table, $quantity)->default(0);
            }

            Columns::recordedAt($table);
        });

        TableRules::for('project_line_balances')
            ->foreign('project_line_balances_line_mode_foreign', ['project_line_id', 'fulfillment_mode'], 'project_lines', ['id', 'fulfillment_mode'])
            ->codes('project_line_balances_fulfillment_mode_code', 'fulfillment_mode', ['WAREHOUSE', 'DROPSHIP', 'SERVICE'])
            ->check('project_line_balances_c05_not_negative', implode(' and ', array_map(static fn (string $quantity): string => "{$quantity} >= 0", self::QUANTITIES)))
            ->check('project_line_balances_c30_warehouse', "fulfillment_mode <> 'WAREHOUSE' or (
                reserved_qty_base + dispatched_net_qty_base + cancelled_qty_base <= demand_qty_base
                and delivered_net_qty_base <= dispatched_net_qty_base
                and dropship_confirmed_net_qty_base = 0 and handed_over_qty_base = 0)")
            ->check('project_line_balances_c30_dropship', "fulfillment_mode <> 'DROPSHIP' or (
                dropship_confirmed_net_qty_base + cancelled_qty_base <= demand_qty_base
                and delivered_net_qty_base <= dropship_confirmed_net_qty_base
                and reserved_qty_base = 0 and dispatched_net_qty_base = 0)")
            ->check('project_line_balances_c30_service', "fulfillment_mode <> 'SERVICE' or (
                handed_over_qty_base + cancelled_qty_base <= demand_qty_base
                and reserved_qty_base = 0 and dispatched_net_qty_base = 0 and dropship_confirmed_net_qty_base = 0 and delivered_net_qty_base = 0)")
            // IX-14: the lines with an open remainder — demand not yet served, or shipped and not yet delivered — for a scope of all companies (QS-01, QS-06).
            ->index('project_line_balances_ix14_open', 'project_line_id', 'demand_qty_base > reserved_qty_base + dispatched_net_qty_base + dropship_confirmed_net_qty_base + handed_over_qty_base + cancelled_qty_base or delivered_net_qty_base < dispatched_net_qty_base + dropship_confirmed_net_qty_base')
            ->grant('select, insert')
            ->grantUpdate(self::QUANTITIES);
    }

    public function down(): void
    {
        Schema::drop('project_line_balances');
    }
};
