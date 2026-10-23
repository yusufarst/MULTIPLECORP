<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_exposures` (SN + GB) and §5.8: a candidate company's recognition-time
// exposure to a case, immutable, and what the case's resolution lines assign it net of reversals, the only
// column the runtime updates — a company is never assigned more of a case than its exposure (DIR-027 items
// 3/9; AX-37; C-37).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_loss_exposures', function (Blueprint $table) {
            Columns::reference($table, 'unattributed_loss_case_id', 'unattributed_loss_cases');
            Columns::reference($table, 'company_id', 'companies');
            Columns::quantity($table, 'exposure_qty_base');
            Columns::quantity($table, 'assigned_net_qty_base')->default(0);
            Columns::recordedAt($table);
            $table->primary(['unattributed_loss_case_id', 'company_id']);
        });

        TableRules::for('unattributed_loss_exposures')
            ->check('unattributed_loss_exposures_c37_bound', 'exposure_qty_base > 0 and assigned_net_qty_base >= 0 and assigned_net_qty_base <= exposure_qty_base')
            ->grant('select, insert')
            ->grantUpdate(['assigned_net_qty_base']);
    }

    public function down(): void
    {
        Schema::drop('unattributed_loss_exposures');
    }
};
