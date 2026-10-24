<?php

namespace App\Modules\INV\Http\Resources;

use App\Http\Resources\ProjectedResource;
use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\Projection;
use App\Modules\INV\Models\InventoryLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lot as a page receives it (PERMISSIONS_MATRIX PJ-01, PJ-02, PJ-04, PJ-23; table 48 `inventory_lots`):
 * its opaque code and product to every `stock.view` holder, pool-wide; its source company named within
 * scope and the relation marker outside it; its acquisition kind, business date and acquired quantity —
 * that company's own warehouse facts — to `stock.view` for a source company in scope; its flagged cost
 * basis to `cost.view` of the source company. A lot of a company outside scope ends at the physical view,
 * its source company never read. Its remainders come from the balance tables' physical-only query class.
 */
class InventoryLotResource extends ProjectedResource
{
    /**
     * The relations to eager load for the viewer: the product, and the source company only within scope.
     *
     * @return array<int|string, mixed>
     */
    public static function relationsFor(Authority $viewer): array
    {
        return [
            'product',
            'sourceCompany' => fn (BelongsTo $relation) => $viewer->scope->apply($relation->getQuery(), 'companies.id'),
        ];
    }

    protected function projection(Authority $viewer): array
    {
        /** @var InventoryLot $lot */
        $lot = $this->resource;
        $source = $lot->source_company_id;

        return [
            'lot_code' => self::field(Audience::pool(), fn () => $lot->lot_code),
            'product' => self::field(Audience::pool(), fn () => ['public_id' => $lot->product?->public_id, 'name' => $lot->product?->name]),
            'source_company' => self::field(Audience::pool(), fn () => Projection::company($viewer, $source, fn () => [
                'public_id' => $lot->sourceCompany?->public_id,
                'code' => $lot->sourceCompany?->code,
                'name' => $lot->sourceCompany?->legal_name,
            ])),
            'acquisition_kind' => self::field(Audience::family('stock.view', $source), fn () => $lot->acquisition_kind),
            'business_date' => self::field(Audience::family('stock.view', $source), fn () => $lot->business_date->toDateString()),
            'acquired_qty_base' => self::field(Audience::family('stock.view', $source), fn () => $lot->acquired_qty_base),
            'cost_basis_flagged' => self::field(Audience::family('cost.view', $source), fn () => $lot->cost_basis_flagged),
        ];
    }
}
