<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_line_balances` (GB): a purchase line's receipt progress and remainder (CALC-03)
// and its cumulative cost posting (§6.1), posted by PurchasePosting (§3.1). Two separate caps hold at every
// commit: normal receipts, closures and drop-ship confirmations within the ordered quantity, and
// replacement receipts within the replacement expected — replacement headroom never admits a normal
// receipt (C-29; PX-03); a cancellation closes the whole line, so a racing receipt fails the guard (AX-12).
// What lots and attributions carry of the line's goods and non-creditable tax stays within the line's
// current values, net of reversals and corrections (C-57).
return new class extends Migration
{
    private const QUANTITIES = [
        'ordered_qty_base', 'normal_received_net_qty_base', 'closed_qty_base', 'dropship_confirmed_net_qty_base',
        'replacement_expected_qty_base', 'replacement_received_net_qty_base',
    ];

    private const VALUES = ['goods_value', 'noncreditable_tax_value', 'posted_goods_value', 'posted_noncreditable_tax_value'];

    public function up(): void
    {
        Schema::create('purchase_line_balances', function (Blueprint $table) {
            $table->bigInteger('purchase_line_id')->primary();

            foreach (self::QUANTITIES as $quantity) {
                Columns::quantity($table, $quantity)->default(0);
            }

            foreach (self::VALUES as $value) {
                Columns::money($table, $value)->default(0);
            }

            Columns::recordedAt($table);
            $table->foreign('purchase_line_id')->references('id')->on('purchase_lines')->restrictOnDelete();
        });

        TableRules::for('purchase_line_balances')
            ->check('purchase_line_balances_not_negative', implode(' and ', array_map(static fn (string $column): string => "{$column} >= 0", [...self::QUANTITIES, ...self::VALUES])))
            ->check('purchase_line_balances_c29_normal_cap', 'normal_received_net_qty_base + closed_qty_base + dropship_confirmed_net_qty_base <= ordered_qty_base')
            ->check('purchase_line_balances_c29_replacement_cap', 'replacement_received_net_qty_base <= replacement_expected_qty_base')
            ->check('purchase_line_balances_c57_posted_values', 'posted_goods_value <= goods_value and posted_noncreditable_tax_value <= noncreditable_tax_value')
            // The lines with an open remainder (QS-05), for a scope of all companies (§20).
            ->index('purchase_line_balances_open_remainder', 'purchase_line_id', 'normal_received_net_qty_base + closed_qty_base + dropship_confirmed_net_qty_base < ordered_qty_base')
            ->grant('select, insert')
            ->grantUpdate([...self::QUANTITIES, ...self::VALUES]);
    }

    public function down(): void
    {
        Schema::drop('purchase_line_balances');
    }
};
