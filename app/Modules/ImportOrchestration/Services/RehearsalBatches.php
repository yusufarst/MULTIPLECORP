<?php

namespace App\Modules\ImportOrchestration\Services;

use InvalidArgumentException;

/**
 * The import rehearsal's synthetic batches (TASK-077; IMP-13; RL-05; DEP-05): FX-09-shaped sources in TASK-057's batch
 * format, made from counts alone — never from a real row — for the rehearsal on staging that measures the commit's
 * duration and sizes the chunks and the freeze. Every value is invented ("Latihan"); each batch carries its companies'
 * PROJECT and PURCHASE seed rows as every real batch does (IMP-07; HO-32); a kind over SourceTable's 30,000 rows is
 * split into parts. The order is IMP-03's: masters, then opening lots, open projects, open purchases, receivables.
 * Opening credits and archive items cite uploaded evidence and archive files, so they are rehearsed by hand.
 */
final class RehearsalBatches
{
    public const ORDER = ['LOCATION', 'PRODUCT', 'CLIENT', 'SUPPLIER', 'OPENING_STOCK', 'OPEN_PROJECT', 'OPEN_PURCHASE', 'OPENING_RECEIVABLE'];

    public const MAXIMUM_ROWS = 30_000;

    private const SEED_COLUMNS = ['nomor_jenis', 'nomor_periode', 'nomor_terakhir'];

    /**
     * @param  array<string, int>  $counts  rows per kind (OPEN_PROJECT: projects, each with two lines)
     * @param  list<string>  $companies  the batch scope's company codes
     * @param  array<string, int>  $seeds  the last legacy number per numbered type, for the cutoff's year
     * @return array<string, string> file name => CSV, in IMP-03's order
     */
    public static function generate(array $counts, array $companies, string $cutoff, array $seeds = ['PROJECT' => 0, 'PURCHASE' => 0]): array
    {
        if ($companies === [] || preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff) !== 1) {
            throw new InvalidArgumentException('Name at least one company code and the cutoff as Y-m-d.');
        }

        foreach (array_keys($counts) as $kind) {
            if (! in_array($kind, self::ORDER, true)) {
                throw new InvalidArgumentException("{$kind} is not a kind the rehearsal generates: ".implode(', ', self::ORDER).'.');
            }
        }

        foreach (['OPENING_STOCK' => ['PRODUCT', 'LOCATION'], 'OPEN_PROJECT' => ['PRODUCT', 'CLIENT'], 'OPEN_PURCHASE' => ['PRODUCT', 'SUPPLIER'], 'OPENING_RECEIVABLE' => ['CLIENT']] as $kind => $needs) {
            foreach ($needs as $need) {
                if (($counts[$kind] ?? 0) > 0 && ($counts[$need] ?? 0) < 1) {
                    throw new InvalidArgumentException("{$kind} cites {$need}: give {$need} a count too.");
                }
            }
        }

        $year = substr($cutoff, 0, 4);
        $files = [];
        $step = 0;

        foreach (self::ORDER as $kind) {
            $count = $counts[$kind] ?? 0;

            if ($count < 1) {
                continue;
            }

            $step++;
            $rows = [];

            for ($n = 1; $n <= $count; $n++) {
                foreach (self::rows($kind, $n, $counts, $companies, $year) as $row) {
                    $rows[] = $row;
                }
            }

            // An even part size keeps an open project's two lines together.
            foreach (array_chunk($rows, max(2, self::MAXIMUM_ROWS - 2 * count($companies) * max(1, count($seeds)))) as $part => $chunk) {
                $files[sprintf('%02d-%s-%02d.csv', $step, $kind, $part + 1)] = self::csv($chunk, $companies, $year, $seeds);
            }
        }

        return $files;
    }

    /**
     * The source rows of the n-th record of a kind.
     *
     * @param  array<string, int>  $counts
     * @param  list<string>  $companies
     * @return list<array<string, string>>
     */
    private static function rows(string $kind, int $n, array $counts, array $companies, string $year): array
    {
        $company = $companies[($n - 1) % count($companies)];
        $product = 'LAT-'.(1 + ($n - 1) % max(1, $counts['PRODUCT'] ?? 1));
        $month = sprintf('%02d', 1 + ($n - 1) % 12);

        return match ($kind) {
            'LOCATION' => [['id' => "L{$n}", 'kode' => "RAK-LAT-{$n}", 'nama' => "Rak Latihan {$n}"]],
            'PRODUCT' => [['id' => "P{$n}", 'jenis' => 'barang', 'sku' => "LAT-{$n}", 'nama' => "Barang Latihan {$n}", 'satuan' => 'PCS', 'harga_beli' => '100000', 'harga_jual' => '125000']],
            'CLIENT' => [['id' => "K{$n}", 'nama' => "Klien Latihan {$n}", 'unit' => 'Unit Latihan', 'alamat' => "Jl. Latihan {$n}", 'kota' => 'Kota Latihan', 'pic_nama' => "PIC Latihan {$n}"]],
            'SUPPLIER' => [['id' => "V{$n}", 'nama' => "Pemasok Latihan {$n}", 'kota' => 'Kota Latihan']],
            'OPENING_STOCK' => [['id' => "S{$n}", 'perusahaan' => $company, 'produk' => $product, 'lokasi' => 'RAK-LAT-'.(1 + ($n - 1) % max(1, $counts['LOCATION'] ?? 1)), 'jumlah' => '10', 'biaya' => '1000000']],
            'OPEN_PROJECT' => [
                ['id' => "J{$n}", 'perusahaan' => $company, 'klien' => 'K'.(1 + ($n - 1) % max(1, $counts['CLIENT'] ?? 1)), 'judul' => "Pengadaan Latihan {$n}", 'produk' => $product, 'jumlah' => '5', 'harga' => '125000'],
                ['id' => "J{$n}", 'perusahaan' => $company, 'produk' => $product, 'jumlah' => '2', 'harga' => '120000', 'keterangan' => 'Baris latihan'],
            ],
            'OPEN_PURCHASE' => [['id' => "B{$n}", 'perusahaan' => $company, 'pemasok' => 'V'.(1 + ($n - 1) % max(1, $counts['SUPPLIER'] ?? 1)), 'tanggal' => "{$year}-{$month}-15", 'produk' => $product, 'jumlah' => '3', 'harga' => '100000']],
            'OPENING_RECEIVABLE' => [['id' => "F{$n}", 'perusahaan' => $company, 'klien' => 'K'.(1 + ($n - 1) % max(1, $counts['CLIENT'] ?? 1)), 'nomor_faktur' => "LAT/INV/{$year}/{$n}", 'tanggal_tagih' => "{$year}-{$month}-01", 'nilai' => '1500000']],
            default => [],
        };
    }

    /**
     * The CSV of the rows with every company's seed rows appended: one header of all columns, comma-delimited, UTF-8.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $companies
     * @param  array<string, int>  $seeds
     */
    private static function csv(array $rows, array $companies, string $year, array $seeds): string
    {
        foreach ($companies as $company) {
            foreach ($seeds as $type => $last) {
                $rows[] = ['id' => '', 'perusahaan' => $company, 'nomor_jenis' => $type, 'nomor_periode' => $year, 'nomor_terakhir' => (string) $last];
            }
        }

        $columns = ['id'];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (! in_array($column, $columns, true) && ! in_array($column, self::SEED_COLUMNS, true)) {
                    $columns[] = $column;
                }
            }
        }

        if (! in_array('perusahaan', $columns, true)) {
            $columns[] = 'perusahaan';
        }

        $columns = [...$columns, ...self::SEED_COLUMNS];
        $lines = [implode(',', $columns)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn (string $column): string => self::cell($row[$column] ?? ''), $columns));
        }

        return implode("\n", $lines)."\n";
    }

    private static function cell(string $value): string
    {
        return str_contains($value, ',') || str_contains($value, '"') ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
