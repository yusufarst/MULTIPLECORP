<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Services\CatalogCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use Illuminate\Support\Facades\DB;

/**
 * `CAT CreateProduct` (ROUTE-145; INT-145; NX-01; WF-MD-04; J-MD-04; CAP-03): a product or service — `kind` GOODS or
 * SERVICE deciding stock applicability (BR-INV-11) —, its SKU, its name, its base unit as the smallest unit counted or
 * sold with ratio 1 and its quantity scale (L-31; DATABASE §7). A service is never serialized and counts in whole
 * units (DATABASE §4.4). An SKU another product holds answers CONFLICT at its field, as the lost race on its unique key
 * does (BD-16; SQ-08). The product's guard row is born with it (NX-01; D-CC-07).
 */
final class CreateProduct
{
    public const TYPE = 'CAT.CreateProduct';

    public function __construct(private readonly CatalogCommands $commands) {}

    /** @param  array{kind: string, sku: ?string, name: string, unit_code: string, unit_name: string, quantity_scale: int}  $fields */
    public function run(string $commandId, int $actorUserId, array $fields): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, $fields, function (CommandContext $context) use ($fields): CommandResult {
            $context->lock(new LockSet);
            $scale = $fields['kind'] === 'SERVICE' ? 0 : $fields['quantity_scale'];

            if ($fields['sku'] !== null && DB::table('products')->whereRaw('lower(sku) = ?', [mb_strtolower($fields['sku'])])->exists()) {
                throw Refused::conflict('SQ-08', ['field' => 'sku']);
            }

            $unit = $this->commands->unit($context, $fields['unit_code'], $fields['unit_name']);
            $values = ['kind' => $fields['kind'], 'sku' => $fields['sku'], 'name' => $fields['name'], 'base_unit_id' => $unit->id, 'quantity_scale' => $scale, 'is_serialized' => false];
            $product = $this->commands->insert($context, new Product, $values);
            $this->commands->insert($context, new ProductUnit, ['product_id' => $product->id, 'unit_id' => $unit->id, 'ratio_to_base' => 1, 'is_base' => true]);
            GuardUpdate::bear($context->connection, 'stock_product_balances', ['product_id' => $product->id], ['recorded_at' => $context->now()]);
            $this->commands->record($context, 'CAT.ProductCreated', 'product', $product->id, $product->public_id, [], [...$values, 'base_unit' => $unit->code]);

            return new CommandResult('product', $product->id);
        });
    }
}
