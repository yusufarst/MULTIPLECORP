<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `purchase_charge_share_balances` (GB) and §6.1: the cumulative-rounding base of a charge's
// share on a line, posted by CostPosting (§3.1), keyed to the share row of its current allocation version.
// What is posted, re-spread and expensed of the share never exceeds it, so a later receipt posts exactly
// the share still pending (C-21, C-57); a charge or basis correction replaces the version and re-posts.
return new class extends Migration
{
    private const AMOUNTS = ['share_amount', 'posted_amount', 'respread_out_amount', 'expensed_out_amount'];

    public function up(): void
    {
        Schema::create('purchase_charge_share_balances', function (Blueprint $table) {
            $table->bigInteger('purchase_charge_id');
            $table->bigInteger('purchase_line_id');
            $table->integer('allocation_version');

            foreach (self::AMOUNTS as $amount) {
                Columns::money($table, $amount)->default(0);
            }

            Columns::recordedAt($table);
            $table->primary(['purchase_charge_id', 'purchase_line_id']);
        });

        TableRules::for('purchase_charge_share_balances')
            ->foreign('purchase_charge_share_balances_current_share_foreign', ['purchase_charge_id', 'purchase_line_id', 'allocation_version'], 'purchase_charge_line_shares', ['purchase_charge_id', 'purchase_line_id', 'allocation_version'])
            ->check('purchase_charge_share_balances_c21_share_cap', 'posted_amount >= 0 and respread_out_amount >= 0 and expensed_out_amount >= 0 and posted_amount + respread_out_amount + expensed_out_amount <= share_amount')
            ->grant('select, insert')
            ->grantUpdate(['allocation_version', ...self::AMOUNTS]);
    }

    public function down(): void
    {
        Schema::drop('purchase_charge_share_balances');
    }
};
