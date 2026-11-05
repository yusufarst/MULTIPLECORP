<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Modules\PUR\Actions\RecordOpeningPurchase;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * An open purchase (IMP-01; IMP-03; IMP-07): its header on the first of its rows — company, supplier committed
 * earlier, the open project committed earlier for a project purchase, the legacy date and number and the supplier's
 * reference — and one line per row sharing its identity with its remaining quantity, price, drop-ship flag and, for a
 * project purchase, the project's line number; through PUR's RecordOpeningPurchase, whose legacy number is kept when
 * it fits the scheme (`DIR-079` CB-01).
 */
final class OpenPurchaseKind implements ImportKind
{
    public const CODE = 'OPEN_PURCHASE';

    public function __construct(private readonly RecordOpeningPurchase $record) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function grouped(): bool
    {
        return true;
    }

    public function companyBound(): bool
    {
        return true;
    }

    public function normalize(Cells $head, array $lines): array
    {
        $rows = [];
        $project = $head->text('proyek', false, 100);

        foreach ($lines as $line) {
            $rows[] = [
                'product' => $line->text('produk', true, 100), 'quantity' => $line->decimal('jumlah', 3, true, true, false), 'unit' => $line->text('satuan', false, 20),
                'price' => $line->decimal('harga', 2, true, true), 'dropship' => $line->flag('langsung'), 'project_line' => $line->integer('baris_proyek', $project !== null, 1),
                'description' => $line->text('keterangan', false, 500),
            ];
            array_push($head->errors, ...$line === $head ? [] : $line->errors);
        }

        return [
            'supplier' => $head->text('pemasok', true, 100), 'project' => $project, 'date' => $head->date('tanggal', true),
            'number' => $head->text('nomor', false, 60), 'reference' => $head->text('referensi', false, 100), 'lines' => $rows,
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $project = $row->nullable('project') === null ? null : References::imported(OpenProjectKind::CODE, $row->text('project'), 'proyek', $row->companyId);
            $lines = [];

            foreach ($row->lines() as $line) {
                $text = static fn (string $key): string => is_scalar($line[$key] ?? null) ? (string) $line[$key] : '';
                $product = References::productRow(References::product($text('product'), 'produk'), 'produk');

                $demand = $project === null ? null : DB::table('project_lines')->where('project_id', $project)->where('line_no', (int) $text('project_line'))->value('id');

                if ($project !== null && ! is_numeric($demand)) {
                    throw new RowRefused('baris_proyek', 'reference');
                }

                $lines[] = [
                    'handle' => null, 'kind' => $product['kind'] === 'GOODS' ? PurchaseEntry::GOODS : PurchaseEntry::NONSTOCK, 'product' => $product['id'],
                    'line' => is_numeric($demand) ? (int) $demand : null, 'mode' => ($line['dropship'] ?? false) === true ? PurchaseEntry::DROPSHIP : PurchaseEntry::WAREHOUSE,
                    'quantity' => $text('quantity'), 'unit' => References::unit($product['id'], $text('unit'), 'satuan')['unit'],
                    'price' => $text('price'), 'description' => $text('description'), 'taxes' => [],
                ];
            }

            $entry = new PurchaseEntry((int) $row->companyId, $project, References::supplier($row->text('supplier'), 'pemasok'), $row->text('date'), false, $row->nullable('reference'), $lines, []);

            return $this->record->run($commandId, $actor, $row->id, $row->nullable('number'), $entry, $row->payload);
        }];
    }

    public function totals(array $payload): array
    {
        return [];
    }
}
