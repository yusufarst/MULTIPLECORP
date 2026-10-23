<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `product_images` (MD): the thumbnails of the product lists (OB §30), each an immutable S0
// file; at most one primary image per product. Images are live references, never snapshotted (§14). The
// file is a plain key, held to a product-image file of no company by 2026_10_23_000052 (DIR-089 (5)); an
// image has no end of life in V1 (DIR-089 (4); §19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'product_id', 'products');
            Columns::reference($table, 'file_object_id', 'file_objects');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            Columns::commandIdentity($table, 'product_images');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index(['product_id', 'sort_order']);
            $table->index('file_object_id');
        });

        TableRules::for('product_images')
            ->uniqueIndex('product_images_one_primary', 'product_id', 'is_primary')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['sort_order', 'is_primary', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('product_images');
    }
};
