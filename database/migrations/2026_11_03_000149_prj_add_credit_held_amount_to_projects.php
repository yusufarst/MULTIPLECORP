<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `DIR-104` (DATABASE §3, §4.5; APPR-028; Change 16): what had arisen for a project's credit at its latest hold, written only by ROUTE-264's `FIN HoldCredit`, its reason on the
// hold's audit event (LG-01). Expand-only, touching no row: every project starts with nothing held.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            Columns::money($table, 'credit_held_amount')->default(0);
        });

        TableRules::for('projects')
            ->check('projects_credit_held_not_negative', 'credit_held_amount >= 0')
            ->grantUpdate(['credit_held_amount']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('credit_held_amount');
        });
    }
};
