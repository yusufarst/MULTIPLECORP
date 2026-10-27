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

    public const ARRANGE = 'CAT.ArrangeProductImage';

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

    /**
     * An attached image made the primary — the list's thumbnail — or moved one place earlier or later (DATABASE §4.4;
     * §19; DIR-089 (4)): what V1 changes of an image, which stays with its product. The product row at LK-08 serializes
     * every change of its images; the primary passes from one row to the other in two statements, the old one first,
     * so the partial unique key never sees two.
     *
     * @param  'primary'|'earlier'|'later'  $move
     */
    public function arrange(string $commandId, int $actorUserId, Product $product, string $filePublicId, string $move): Outcome
    {
        return $this->commands->run($commandId, self::ARRANGE, $actorUserId, ['product' => $product->public_id, 'file' => $filePublicId, 'move' => $move],
            function (CommandContext $context) use ($product, $filePublicId, $move): CommandResult {
                $context->lock((new LockSet)->add('products', ['id' => $product->id]));
                $images = ProductImage::query()->join('file_objects', 'file_objects.id', '=', 'product_images.file_object_id')->where('product_images.product_id', $product->id)
                    ->orderBy('product_images.sort_order')->orderBy('product_images.id')->get(['product_images.*', 'file_objects.public_id as file_public_id'])->all();
                $images = array_values($images);
                $index = array_search(strtolower($filePublicId), array_map(fn (ProductImage $image): string => (string) $image->getAttribute('file_public_id'), $images), true);

                if (! is_int($index)) {
                    throw new ModelNotFoundException;
                }

                $image = $images[$index];

                if ($move === 'primary') {
                    if (! $image->is_primary) {
                        foreach ($images as $other) {
                            if ($other->is_primary) {
                                $this->commands->update($context, 'product_images', $other->id, $other->lock_version, ['is_primary' => false]);
                            }
                        }

                        $this->commands->update($context, 'product_images', $image->id, $image->lock_version, ['is_primary' => true]);
                    }
                } else {
                    $neighbour = $images[$move === 'earlier' ? $index - 1 : $index + 1] ?? null;

                    if ($neighbour === null) {
                        throw Refused::rejected('WF-MD-04', ['reason' => 'image_edge']);
                    }

                    $this->commands->update($context, 'product_images', $image->id, $image->lock_version, ['sort_order' => $neighbour->sort_order === $image->sort_order ? $neighbour->sort_order + ($move === 'earlier' ? -1 : 1) : $neighbour->sort_order]);
                    $this->commands->update($context, 'product_images', $neighbour->id, $neighbour->lock_version, ['sort_order' => $image->sort_order]);
                }

                $this->commands->record($context, 'CAT.ProductImageArranged', 'product_image', $image->id, $product->public_id, ['file' => strtolower($filePublicId)], ['file' => strtolower($filePublicId), 'move' => $move]);

                return new CommandResult('product', $product->id);
            });
    }
}
