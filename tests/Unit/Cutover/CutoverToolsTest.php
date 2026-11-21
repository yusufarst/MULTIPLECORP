<?php

use App\Modules\ImportOrchestration\Services\RehearsalBatches;
use App\Modules\ORG\Services\FileGoLive;
use App\Support\Performance\HotQueries;
use App\Support\Performance\PostImportRefresh;

// TL-03 (TASK-077; IMP-13; RL-05; HO-32; PFM-03): the rehearsal generator's batches from counts alone — their kinds in
// IMP-03's order, their row counts, every company's seed rows, invented values only, a kind over 30,000 rows split and a
// cited kind without its count refused —; go-live's day read only as a real Y-m-d; and a plan's shape read without a
// value of the data.

/** @return list<array<string, string>> */
function rehearsalRows(string $csv): array
{
    $lines = array_values(array_filter(explode("\n", $csv)));
    $header = str_getcsv(array_shift($lines), ',', '"', '');

    return array_map(fn (string $line): array => array_combine($header, str_getcsv($line, ',', '"', '')), $lines);
}

it('writes FX-09-shaped batches in IMP-03\'s order from counts alone, each with its companies\' seed rows (IMP-13; IMP-07)', function () {
    $files = RehearsalBatches::generate(
        ['PRODUCT' => 30, 'LOCATION' => 3, 'CLIENT' => 7, 'SUPPLIER' => 2, 'OPENING_STOCK' => 40, 'OPEN_PROJECT' => 5, 'OPEN_PURCHASE' => 4, 'OPENING_RECEIVABLE' => 6],
        ['CTH', 'LAT'], '2025-12-31', ['PROJECT' => 120, 'PURCHASE' => 300],
    );

    expect(array_keys($files))->toBe(['01-LOCATION-01.csv', '02-PRODUCT-01.csv', '03-CLIENT-01.csv', '04-SUPPLIER-01.csv', '05-OPENING_STOCK-01.csv', '06-OPEN_PROJECT-01.csv', '07-OPEN_PURCHASE-01.csv', '08-OPENING_RECEIVABLE-01.csv']);

    $stock = rehearsalRows($files['05-OPENING_STOCK-01.csv']);
    $records = array_values(array_filter($stock, fn (array $row): bool => $row['nomor_jenis'] === ''));
    $seeds = array_values(array_filter($stock, fn (array $row): bool => $row['nomor_jenis'] !== ''));
    $projects = array_values(array_filter(rehearsalRows($files['06-OPEN_PROJECT-01.csv']), fn (array $row): bool => $row['nomor_jenis'] === ''));

    expect($records)->toHaveCount(40)
        ->and(array_unique(array_column($records, 'perusahaan')))->toEqualCanonicalizing(['CTH', 'LAT'])
        ->and(collect($records)->every(fn (array $row): bool => preg_match('/^LAT-\d+$/', $row['produk']) === 1 && preg_match('/^RAK-LAT-[1-3]$/', $row['lokasi']) === 1))->toBeTrue()
        ->and($seeds)->toHaveCount(4)
        ->and(collect($seeds)->map(fn (array $row): string => "{$row['perusahaan']}|{$row['nomor_jenis']}|{$row['nomor_periode']}|{$row['nomor_terakhir']}")->sort()->values()->all())
        ->toBe(['CTH|PROJECT|2025|120', 'CTH|PURCHASE|2025|300', 'LAT|PROJECT|2025|120', 'LAT|PURCHASE|2025|300'])
        ->and($projects)->toHaveCount(10)
        ->and(count(array_unique(array_column($projects, 'id'))))->toBe(5)
        ->and(rehearsalRows($files['02-PRODUCT-01.csv'])[0])->toMatchArray(['id' => 'P1', 'sku' => 'LAT-1', 'nama' => 'Barang Latihan 1', 'jenis' => 'barang']);

    // Invented values only: every name the generator writes is a "Latihan" one.
    foreach ($files as $csv) {
        foreach (rehearsalRows($csv) as $row) {
            foreach (['nama', 'judul'] as $column) {
                if (($row[$column] ?? '') !== '') {
                    expect($row[$column])->toContain('Latihan');
                }
            }
        }
    }
});

it('splits a kind over SourceTable\'s 30,000 rows and refuses a cited kind without its count or an unknown kind', function () {
    $files = RehearsalBatches::generate(['PRODUCT' => 35_000], ['CTH'], '2025-12-31');

    expect(array_keys($files))->toBe(['01-PRODUCT-01.csv', '01-PRODUCT-02.csv'])
        ->and(collect($files)->sum(fn (string $csv): int => count(array_filter(rehearsalRows($csv), fn (array $row): bool => $row['nomor_jenis'] === ''))))->toBe(35_000)
        ->and(collect($files)->every(fn (string $csv): bool => count(rehearsalRows($csv)) <= RehearsalBatches::MAXIMUM_ROWS))->toBeTrue()
        ->and(fn () => RehearsalBatches::generate(['OPENING_STOCK' => 5, 'PRODUCT' => 2], ['CTH'], '2025-12-31'))->toThrow(InvalidArgumentException::class, 'LOCATION')
        ->and(fn () => RehearsalBatches::generate(['OPENING_CREDIT' => 5], ['CTH'], '2025-12-31'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RehearsalBatches::generate(['PRODUCT' => 5], [], '2025-12-31'))->toThrow(InvalidArgumentException::class);
});

it('writes each seed row with the period its company\'s scheme uses, a monthly one included (IMP-07; NM-03)', function () {
    $files = RehearsalBatches::generate(['PRODUCT' => 2], ['CTH', 'LAT'], '2025-12-31', [
        ['company' => 'CTH', 'type' => 'PROJECT', 'period' => '2025-12', 'last' => 9],
        ['company' => 'LAT', 'type' => 'PROJECT', 'period' => '2025', 'last' => 40],
    ]);
    $seeds = array_values(array_filter(rehearsalRows($files['01-PRODUCT-01.csv']), fn (array $row): bool => $row['nomor_jenis'] !== ''));

    expect(array_map(fn (array $row): string => "{$row['perusahaan']}|{$row['nomor_jenis']}|{$row['nomor_periode']}|{$row['nomor_terakhir']}", $seeds))
        ->toBe(['CTH|PROJECT|2025-12|9', 'LAT|PROJECT|2025|40']);
});

it('reads go-live only as a real Y-m-d day, and a missing file as unrecorded (NM-03)', function () {
    expect(FileGoLive::valid('2026-02-01'))->toBe('2026-02-01')
        ->and(FileGoLive::valid('2026-02-30'))->toBeNull()
        ->and(FileGoLive::valid('01/02/2026'))->toBeNull()
        ->and(FileGoLive::valid(''))->toBeNull()
        ->and((new FileGoLive(sys_get_temp_dir().'/tidak-ada-'.bin2hex(random_bytes(4))))->day())->toBeNull();
});

it('reads a plan\'s shape — nodes, relations and indexes — without its conditions or values (PFM-03)', function () {
    $plan = ['Node Type' => 'Limit', 'Plans' => [['Node Type' => 'Index Scan', 'Relation Name' => 'projects', 'Index Name' => 'projects_ix11_company_state',
        'Index Cond' => '(company_id = 7)', 'Plans' => [['Node Type' => 'Seq Scan', 'Relation Name' => 'companies', 'Filter' => "(code = 'RAHASIA')"]]]]];

    $nodes = PostImportRefresh::nodes($plan);

    expect($nodes)->toBe([
        ['type' => 'Limit', 'relation' => '', 'index' => ''],
        ['type' => 'Index Scan', 'relation' => 'projects', 'index' => 'projects_ix11_company_state'],
        ['type' => 'Seq Scan', 'relation' => 'companies', 'index' => ''],
    ])->and(json_encode($nodes))->not->toContain('RAHASIA')->not->toContain('= 7')
        ->and(count(HotQueries::REGISTER))->toBeGreaterThan(80);
});
