<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.11 `admin_requirement_waivers` (AD): TIDAK BERLAKU on an eligible requirement (BR-ADM-02), a
// decision with its reason and actor, corrected only by a linear chain of superseding decisions on the same
// requirement (L-32); `reopened_by_correction` marks the waiver a material correction reopened (BR-ADM-03).
// The requirement is of the waiver's company (C-32).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_requirement_waivers', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('admin_requirement_id');
            $table->bigInteger('company_id');
            $table->text('reason');
            Columns::user($table, 'decided_by');
            $table->bigInteger('supersedes_waiver_id')->nullable();
            $table->boolean('reopened_by_correction')->default(false);
            Columns::commandIdentity($table, 'admin_requirement_waivers');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('admin_requirement_waivers')
            ->unique('admin_requirement_waivers_requirement_key', ['admin_requirement_id', 'id'])
            ->unique('admin_requirement_waivers_linear_chain', ['supersedes_waiver_id'])
            ->foreign('admin_requirement_waivers_c32_requirement_foreign', ['company_id', 'admin_requirement_id'], 'admin_requirements', ['company_id', 'id'])
            ->foreign('admin_requirement_waivers_supersedes_foreign', ['admin_requirement_id', 'supersedes_waiver_id'], 'admin_requirement_waivers', ['admin_requirement_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('admin_requirement_waivers');
    }
};
