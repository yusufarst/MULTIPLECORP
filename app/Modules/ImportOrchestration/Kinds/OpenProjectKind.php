<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\CAT\Data\Quantity;
use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Modules\PRJ\Actions\RecordOpeningProject;
use App\Modules\PRJ\Data\LineInput;
use App\Modules\PRJ\Data\ProjectInput;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * An open project (IMP-01; IMP-03): its header on the first of its rows — company, client committed earlier, the
 * client's unit, title and deadline — and one remaining demand line per row sharing its identity — product, remaining
 * quantity in a unit of the product, unit price and fulfilment mode —, through PRJ's RecordOpeningProject with its
 * MIGRATION confirmation at the cutoff.
 */
final class OpenProjectKind implements ImportKind
{
    public const CODE = 'OPEN_PROJECT';

    private const MODES = ['gudang' => 'WAREHOUSE', 'warehouse' => 'WAREHOUSE', 'kirim langsung' => 'DROPSHIP', 'dropship' => 'DROPSHIP', 'jasa' => 'SERVICE', 'service' => 'SERVICE'];

    public function __construct(private readonly RecordOpeningProject $record) {}

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

        foreach ($lines as $line) {
            $rows[] = [
                'product' => $line->text('produk', true, 100), 'quantity' => $line->decimal('jumlah', 3, true, true, false), 'unit' => $line->text('satuan', false, 20),
                'price' => $line->decimal('harga', 2, true, true), 'mode' => $line->choice('cara', self::MODES), 'description' => $line->text('keterangan', false, 500),
            ];
            array_push($head->errors, ...$line === $head ? [] : $line->errors);
        }

        return [
            'client' => $head->text('klien', true, 100), 'unit' => $head->text('unit', false, 200), 'title' => $head->text('judul', true, 200),
            'deadline' => $head->date('tenggat'), 'lines' => $rows,
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $client = References::client($row->text('client'), 'klien');
            $units = DB::table('client_units')->where('client_organization_id', $client)->orderBy('id');
            $unit = ($row->nullable('unit') === null ? $units : $units->whereRaw('lower(name) = ?', [mb_strtolower($row->text('unit'))]))->value('id');

            if (! is_numeric($unit)) {
                throw new RowRefused('unit', 'reference');
            }

            $address = DB::table('client_addresses')->where('client_unit_id', $unit)->orderBy('id')->value('id');
            $contact = DB::table('client_contacts')->where('client_unit_id', $unit)->orderBy('id')->value('id');
            $lines = array_map(fn (array $line): LineInput => self::line($line), $row->lines());
            $input = new ProjectInput((int) $row->companyId, $client, (int) $unit, is_numeric($address) ? (int) $address : null, is_numeric($contact) ? (int) $contact : null,
                'REGULAR', $row->text('title'), $row->nullable('deadline'), null, null, $lines, null, true, ['import' => $row->name(0)]);

            return $this->record->run($commandId, $actor, $row->id, $input, $row->cutoff);
        }];
    }

    public function totals(array $payload): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $line */
    private static function line(array $line): LineInput
    {
        $text = static fn (string $key): string => is_scalar($line[$key] ?? null) ? (string) $line[$key] : '';
        $product = References::productRow(References::product($text('product'), 'produk'), 'produk');
        $unit = References::unit($product['id'], $text('unit'), 'satuan');
        $base = Quantity::toBase($text('quantity'), $unit['ratio']);

        if (! Quantity::atScale($base, $product['scale'])) {
            throw new RowRefused('jumlah', 'scale');
        }

        $mode = $text('mode') !== '' ? $text('mode') : ($product['kind'] === 'SERVICE' ? 'SERVICE' : 'WAREHOUSE');

        return new LineInput(null, $product['id'], $product['kind'], $product['scale'], $text('description') !== '' ? $text('description') : $product['name'],
            $text('quantity'), $unit['unit'], $unit['ratio'], $base, $text('price'), $mode);
    }
}
