<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `products` (MD) and §7: the goods or service flag decides stock applicability (BR-INV-11);
// the base unit is the smallest counted unit (L-31) and a base quantity has at most the product's scale
// — 0 for counted and serialized goods, which are integer pieces (BR-INV-03). Every quantity-bearing fact
// references (`id`, `quantity_scale`), so a fractional quantity of a counted product is unstorable and the
// scale cannot change while a row references it (§1, §7). No historical row keys on `is_serialized`, so
// the L-35 switch stays possible. An empty minimum means none; one that is set holds the product's scale
// and is read by QS-17 (IX-14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('kind');
            $table->text('sku')->nullable();
            $table->text('name');
            Columns::reference($table, 'base_unit_id', 'units');
            $table->smallInteger('quantity_scale')->default(0);
            $table->boolean('is_serialized')->default(false);
            Columns::quantity($table, 'min_stock_qty_base')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'products');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('products')
            ->codes('products_kind_code', 'kind', ['GOODS', 'SERVICE'])
            ->check('products_quantity_scale_range', 'quantity_scale between 0 and 3')
            ->check('products_serialized_counted', "not is_serialized or (kind = 'GOODS' and quantity_scale = 0)")
            ->check('products_minimum_at_scale', 'min_stock_qty_base is null or min_stock_qty_base = round(min_stock_qty_base, quantity_scale)')
            // The scale key of §1, which every quantity-bearing fact references.
            ->unique('products_scale_key', ['id', 'quantity_scale'])
            ->uniqueIndex('products_sku_unique', 'lower(sku)')
            ->trigramIndex('products_name_trigram', 'name')
            ->index('products_ix17_name_prefix', 'lower(name) text_pattern_ops')
            // IX-14: the products that carry a minimum (QS-17).
            ->index('products_ix14_minimum', 'id', 'min_stock_qty_base is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'sku', 'name', 'quantity_scale', 'is_serialized', 'min_stock_qty_base',
                'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('products');
    }
};
