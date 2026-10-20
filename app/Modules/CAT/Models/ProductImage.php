<?php

namespace App\Modules\CAT\Models;

use App\Modules\DOC\Models\FileObject;
use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's thumbnail (DATABASE §4.4 `product_images`, MD; OB §30): an immutable S0 file, a live
 * reference never snapshotted (§14).
 *
 * @property int $id
 * @property int $product_id
 * @property int $file_object_id
 * @property int $sort_order
 * @property bool $is_primary
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProductImageFactory::class)]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<FileObject, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'file_object_id' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
