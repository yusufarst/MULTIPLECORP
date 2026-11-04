<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\CAT\Actions\AddAlternateUnit;
use App\Modules\CAT\Actions\ChangeDefaultPrice;
use App\Modules\CAT\Actions\CreateProduct;
use App\Modules\CAT\Actions\RegisterBarcode;
use App\Modules\CAT\Actions\UpdateProduct;
use App\Modules\CAT\Queries\ProductCatalog;
use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * A current product (IMP-01) with its base unit, serial policy, barcode, an alternate unit and its default prices,
 * each through CAT's ordinary command — CreateProduct, the serial policy before any stock (L-35), RegisterBarcode,
 * AddAlternateUnit, ChangeDefaultPrice — as the row's steps under derived identities, the row committed with the last.
 */
final class ProductKind implements ImportKind
{
    public const CODE = 'PRODUCT';

    public function __construct(
        private readonly CreateProduct $create,
        private readonly UpdateProduct $update,
        private readonly RegisterBarcode $barcodes,
        private readonly AddAlternateUnit $units,
        private readonly ChangeDefaultPrice $prices,
        private readonly ProductCatalog $catalog,
    ) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function grouped(): bool
    {
        return false;
    }

    public function companyBound(): bool
    {
        return false;
    }

    public function normalize(Cells $head, array $lines): array
    {
        $kind = $head->choice('jenis', ['barang' => 'GOODS', 'goods' => 'GOODS', 'jasa' => 'SERVICE', 'service' => 'SERVICE'], true);
        $scale = $head->integer('desimal') ?? 0;
        $serialized = $head->flag('serial');

        if ($scale > 3) {
            $head->fail('desimal', 'choice');
        }

        if ($serialized && ($kind === 'SERVICE' || $scale !== 0)) {
            $head->fail('serial', 'serial_policy');
        }

        $alternate = $head->text('satuan_lain', false, 20);
        $ratio = $alternate === null ? null : $head->integer('rasio', true, 2);

        return [
            'kind' => $kind, 'sku' => $head->text('sku', false, 60), 'name' => $head->text('nama', true, 200),
            'unit_code' => $head->text('satuan', true, 20), 'unit_name' => $head->text('nama_satuan', false, 60), 'scale' => $scale, 'serialized' => $serialized,
            'barcode' => $head->text('barcode', false, 64), 'alternate' => $alternate, 'alternate_name' => $head->text('nama_satuan_lain', false, 60), 'ratio' => $ratio,
            'purchase_price' => $head->decimal('harga_beli', 2, false, true), 'selling_price' => $head->decimal('harga_jual', 2, false, true),
        ];
    }

    public function steps(ImportRowData $row): array
    {
        // The product the first step created, read through CAT's query class (ARCHITECTURE §3).
        $product = static function (array $done): string {
            $publicId = DB::table('products')->where('id', References::result($done, 0, 'sku'))->value('public_id');

            return is_string($publicId) ? $publicId : throw new RowRefused('sku', 'reference');
        };
        $steps = [fn (string $commandId, int $actor): Outcome => $this->create->run($commandId, $actor, [
            'kind' => $row->text('kind'), 'sku' => $row->nullable('sku'), 'name' => $row->text('name'),
            'unit_code' => $row->text('unit_code'), 'unit_name' => $row->nullable('unit_name') ?? $row->text('unit_code'), 'quantity_scale' => (int) $row->text('scale'),
        ])];

        if (($row->payload['serialized'] ?? false) === true) {
            $steps[] = function (string $commandId, int $actor, array $done) use ($product): Outcome {
                $found = $this->catalog->find($product($done)) ?? throw new RowRefused('sku', 'reference');

                return $this->update->policy($commandId, $actor, $found, $found->lock_version, true, 0);
            };
        }

        if ($row->nullable('barcode') !== null) {
            $steps[] = fn (string $commandId, int $actor, array $done): Outcome => $this->barcodes->run($commandId, $actor, $this->catalog->find($product($done)) ?? throw new RowRefused('sku', 'reference'), 'MANUFACTURER', $row->text('barcode'));
        }

        if ($row->nullable('alternate') !== null) {
            $steps[] = fn (string $commandId, int $actor, array $done): Outcome => $this->units->run($commandId, $actor, $this->catalog->find($product($done)) ?? throw new RowRefused('sku', 'reference'), $row->text('alternate'),
                $row->nullable('alternate_name') ?? $row->text('alternate'), (int) $row->text('ratio'));
        }

        foreach (['purchase_price' => 'PURCHASE_DEFAULT', 'selling_price' => 'SELLING_DEFAULT'] as $field => $kind) {
            if ($row->nullable($field) !== null) {
                $steps[] = $this->price($product, $kind, $row->text($field), $row);
            }
        }

        return $steps;
    }

    public function totals(array $payload): array
    {
        return [];
    }

    /**
     * @param  Closure(array<mixed>): string  $product
     * @return Closure(string, int, list<Outcome>): Outcome
     */
    private function price(Closure $product, string $kind, string $amount, ImportRowData $row): Closure
    {
        return fn (string $commandId, int $actor, array $done): Outcome => $this->prices->run($commandId, $actor, $this->catalog->find($product($done)) ?? throw new RowRefused('sku', 'reference'), $kind, $amount, ClientKind::reason($row));
    }
}
