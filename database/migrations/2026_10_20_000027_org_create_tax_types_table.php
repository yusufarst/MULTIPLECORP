<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `tax_types` (CF): tax kinds are data, never code constants (BR-FIN-17; FS-13; §12).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            Columns::id($table);
            $table->text('code');
            $table->text('name');
            $table->text('category');
            $table->boolean('is_active')->default(true);
            Columns::commandIdentity($table, 'tax_types');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('tax_types')
            ->unique('tax_types_code_unique', ['code'])
            ->codes('tax_types_category_code', 'category', ['OUTPUT_TAX', 'INPUT_TAX', 'WITHHOLDING', 'COLLECTION', 'OTHER'])
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'category', 'is_active', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('tax_types');
    }
};
