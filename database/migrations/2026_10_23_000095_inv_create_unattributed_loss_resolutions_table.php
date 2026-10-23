<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_resolutions` (AD) and §5.8: the economic resolution of a pending case
// (AX-37) — the Owner's attribution, or the definitive evidence's (DIR-027 item 10) —, posted by one
// movement; a resolution in error is reversed once and exactly, the corrected one superseding it within its
// case (CM-27; C-37).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_loss_resolutions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'unattributed_loss_case_id', 'unattributed_loss_cases');
            $table->text('resolution_kind');
            Columns::reference($table, 'stock_movement_id', 'stock_movements')->unique();
            $table->bigInteger('supersedes_resolution_id')->nullable()->unique();
            Columns::user($table, 'decided_by');
            Columns::businessDate($table);
            $table->text('reason');
            Columns::commandIdentity($table, 'unattributed_loss_resolutions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('unattributed_loss_resolutions')
            ->unique('unattributed_loss_resolutions_case_key', ['id', 'unattributed_loss_case_id'])
            ->foreign('unattributed_loss_resolutions_c37_supersedes_foreign', ['supersedes_resolution_id', 'unattributed_loss_case_id'], 'unattributed_loss_resolutions', ['id', 'unattributed_loss_case_id'])
            ->codes('unattributed_loss_resolutions_kind_code', 'resolution_kind', ['OWNER_ATTRIBUTION', 'EVIDENCE_IDENTIFIED'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('unattributed_loss_resolutions');
    }
};
