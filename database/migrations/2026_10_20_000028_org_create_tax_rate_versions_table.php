<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `tax_rate_versions` (CF): effective-dated configured rates (DIR-024 D-1 item 3; §12) —
// a rate in `numeric(9,6)` percent, the configured base as an exact fraction of two integers and the
// rounding mode; a version's `[from, to)` range never overlaps another of its tax type (C-52), and an
// empty range, which would overlap nothing, is refused. Each transaction snapshots what it applied
// (BR-SN-01).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rate_versions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'tax_type_id', 'tax_types');
            $table->decimal('rate_percent', 9, 6);
            $table->integer('base_numerator')->default(1);
            $table->integer('base_denominator')->default(1);
            $table->text('rounding_mode');
            $table->rawColumn('effective', 'daterange');
            Columns::commandIdentity($table, 'tax_rate_versions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('tax_rate_versions')
            ->codes('tax_rate_versions_rounding_mode_code', 'rounding_mode', ['HALF_UP', 'DOWN'])
            ->check('tax_rate_versions_base_denominator_positive', 'base_denominator > 0')
            ->check('tax_rate_versions_effective_not_empty', 'not isempty(effective)')
            ->exclude('tax_rate_versions_c52_no_overlap', 'tax_type_id with =, effective with &&')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['rate_percent', 'base_numerator', 'base_denominator', 'rounding_mode', 'effective', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('tax_rate_versions');
    }
};
