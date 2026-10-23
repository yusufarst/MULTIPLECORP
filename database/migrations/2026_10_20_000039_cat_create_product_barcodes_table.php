<?php

use App\Support\Database\Columns;
use App\Support\Database\DatabaseRole;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `product_barcodes` (MD): a code resolves to at most one active product however it is
// typed or scanned (BR-INV-09; C-01) — `code_key` is the printed code without whitespace and in upper
// case, leading zeros kept, and a scan is looked up by the same normalization (§20). A barcode row is
// deactivated, freeing its code (§19). Internal codes come from a database sequence, whose gaps are
// harmless; the sequence belongs to the column it fills, so it goes with the table, and is the runtime's
// to draw from.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'product_id', 'products');
            // As printed.
            $table->text('code');
            $table->text('code_key')->storedAs("upper(regexp_replace(code, '\\s', '', 'g'))");
            $table->text('kind');
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'product_barcodes');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('product_id');
        });

        TableRules::for('product_barcodes')
            ->uniqueIndex('product_barcodes_c01_active_code', 'code_key', 'is_active')
            ->codes('product_barcodes_kind_code', 'kind', ['MANUFACTURER', 'INTERNAL'])
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);

        DB::statement('create sequence product_barcode_internal_codes as bigint owned by product_barcodes.code');
        DB::statement('grant usage on sequence product_barcode_internal_codes to '.DatabaseRole::RUNTIME);
    }

    public function down(): void
    {
        DB::statement('drop sequence if exists product_barcode_internal_codes');
        Schema::drop('product_barcodes');
    }
};
