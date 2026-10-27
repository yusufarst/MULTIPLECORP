<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\CAT\Services\CatalogCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * `CAT AttachProductImage` (ROUTE-146; INT-146; NX-01; FL-02; FL-03; DIR-089 (4)): an image uploaded first by ROUTE-228
 * under the PRODUCT_IMAGE purpose — its allowlist and stripping the pipeline's — attached to the product, the first
 * one its primary, later ones after it. The file must be the actor's own upload of that purpose and of no company;
 * anything else answers as unknown. V1 removes no image: one stays with its product (DATABASE §4.4; §19).
 */
final class AttachProductImage
{
    public const TYPE = 'CAT.AttachProductImage';

    public function __construct(private readonly CatalogCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Product $product, string $filePublicId): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'file' => $filePublicId],
            function (CommandContext $context) use ($product, $filePublicId): CommandResult {
                $context->lock((new LockSet)->add('products', ['id' => $product->id]));
                $file = DB::table('file_objects')->where('public_id', strtolower($filePublicId))->where('purpose', 'PRODUCT_IMAGE')
                    ->whereNull('company_id')->where('uploaded_by', $context->request->actorUserId)->value('id');

                if (! is_numeric($file)) {
                    throw new ModelNotFoundException;
                }

                $row = Product::query()->findOrFail($product->id);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                if (ProductImage::query()->where('product_id', $row->id)->where('file_object_id', (int) $file)->exists()) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'image_attached']);
                }

                $last = ProductImage::query()->where('product_id', $row->id)->max('sort_order');
                $first = ! ProductImage::query()->where('product_id', $row->id)->exists();
                $image = $this->commands->insert($context, new ProductImage, ['product_id' => $row->id, 'file_object_id' => (int) $file,
                    'sort_order' => (is_numeric($last) ? (int) $last : -1) + 1, 'is_primary' => $first]);
                $this->commands->record($context, 'CAT.ProductImageAttached', 'product_image', $image->id, $row->public_id, [], ['file' => strtolower($filePublicId), 'is_primary' => $first]);

                return new CommandResult('product', $row->id);
            });
    }
}
