<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\INV\Actions\RecordOpeningStock;
use App\Support\Command\Outcome;

/**
 * Opening stock (IMP-01; BR-INV-12): one lot per row — its company, product, location and condition, quantity in a
 * unit of the product, the lot's total cost and its serials separated by semicolons — through INV's RecordOpeningStock
 * at the batch's cutoff date; its quantity and cost added to its company's control totals.
 */
final class OpeningStockKind implements ImportKind
{
    public const CODE = 'OPENING_STOCK';

    public function __construct(private readonly RecordOpeningStock $record) {}

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
        return true;
    }

    public function normalize(Cells $head, array $lines): array
    {
        $serials = array_values(array_filter(array_map('trim', explode(';', $head->text('serial', false, 2000) ?? '')), fn (string $serial): bool => $serial !== ''));

        if (count($serials) !== count(array_unique(array_map('mb_strtolower', $serials)))) {
            $head->fail('serial', 'repeat');
        }

        return [
            'product' => $head->text('produk', true, 100), 'location' => $head->text('lokasi', true, 40),
            'condition' => $head->choice('kondisi', ['baik' => 'USABLE', 'usable' => 'USABLE', 'rusak' => 'UNUSABLE', 'unusable' => 'UNUSABLE'], false, 'USABLE'),
            'quantity' => $head->decimal('jumlah', 3, true, true, false), 'unit' => $head->text('satuan', false, 20),
            'cost' => $head->decimal('biaya', 2, true, true), 'serials' => $serials,
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $product = References::product($row->text('product'), 'produk');
            $unit = References::unit($product, $row->nullable('unit'), 'satuan');
            $serials = $row->payload['serials'] ?? [];

            return $this->record->run($commandId, $actor, (int) $row->companyId, [
                'import_row' => $row->id, 'product' => $product, 'location' => References::location($row->text('location'), 'lokasi'), 'condition' => $row->text('condition'),
                'quantity' => $row->text('quantity'), 'unit' => $unit['unit'], 'ratio' => $unit['ratio'], 'cost' => $row->text('cost'),
                'serials' => is_array($serials) ? array_values(array_map(fn (mixed $serial): string => is_string($serial) ? $serial : '', $serials)) : [], 'date' => $row->cutoff,
            ]);
        }];
    }

    public function totals(array $payload): array
    {
        return ['stock_qty' => self::scalar($payload['quantity'] ?? null), 'stock_cost' => self::scalar($payload['cost'] ?? null)];
    }

    public static function scalar(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '0';
    }
}
