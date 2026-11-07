<?php

use App\Modules\Reporting\Exports\CellText;
use App\Modules\Reporting\Exports\XlsxWriter;
use App\Modules\Reporting\Reports\Period;
use Illuminate\Support\Facades\File;

// TL-03 (TASK-060; FL-09; TM-16; TM-46; GR-19; acceptance criterion 6): every text cell whose first character could start a
// formula is neutralized, its full-width variants included; numbers stay typed numbers and nothing is ever written as a
// formula; the period presets are bound to the calendar months and a range is ordered and bounded.

it('prefixes a single quote to a text starting with a formula character or a full-width variant, and leaves others (FL-09)', function (string $text, string $expected) {
    expect(CellText::neutral($text))->toBe($expected);
})->with([
    'equals' => [implode('', ['=', 'SUM(A1)']), "'=SUM(A1)"],
    'plus' => ['+62 812', "'+62 812"],
    'minus' => [implode('', ['-', '2+3']), "'-2+3"],
    'at' => ['@cmd', "'@cmd"],
    'tab' => ["\tx", "'\tx"],
    'carriage return' => ["\rx", "'\rx"],
    'line feed' => ["\nx", "'\nx"],
    'full-width equals' => ["\u{FF1D}1+1", "'\u{FF1D}1+1"],
    'full-width plus' => ["\u{FF0B}1", "'\u{FF0B}1"],
    'full-width minus' => ["\u{FF0D}1", "'\u{FF0D}1"],
    'full-width at' => ["\u{FF20}x", "'\u{FF20}x"],
    'plain' => ['CV Arunika Jaya', 'CV Arunika Jaya'],
    'a quote inside' => ['Toko "Maju"', 'Toko "Maju"'],
    'a control character dropped' => ["A\u{0001}B", 'AB'],
]);

it('writes numbers as typed numbers and text as inline strings, never a formula (FL-09; QB-15)', function () {
    $directory = sys_get_temp_dir().'/xlsx-test-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($directory);
    $writer = new XlsxWriter($directory);
    $writer->sheet('Laporan: Penjualan/2026');
    $writer->row(['Nomor', 'Nilai'], [false, true]);
    $writer->row([implode('', ['=', 'cmd|calc']), '-1500.50'], [false, true]);
    $writer->row(['12abc', 'bukan angka'], [false, true]);
    $writer->finish($directory.'/out.xlsx');

    $zip = new ZipArchive;
    expect($zip->open($directory.'/out.xlsx'))->toBeTrue();
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $zip->close();
    File::deleteDirectory($directory);

    expect($sheet)->not->toContain('<f>')->not->toContain('<f ')
        ->toContain('<c t="n"><v>-1500.50</v></c>')
        ->toContain('<t xml:space="preserve">&apos;=cmd|calc</t>')
        // A cell asked to be numeric but not a number stays text.
        ->toContain('<t xml:space="preserve">bukan angka</t>')
        ->and($workbook)->toContain('name="Laporan  Penjualan 2026"');
});

it('binds the presets to whole calendar months and orders and bounds a custom range (GR-19; PF-27)', function () {
    expect(Period::of(null, null, null, '2026-11-07')->keys())->toBe(['periode' => 'bulan-ini', 'dari' => '2026-11-01', 'sampai' => '2026-11-30'])
        ->and(Period::of('bulan-lalu', null, null, '2026-03-31')->keys())->toBe(['periode' => 'bulan-lalu', 'dari' => '2026-02-01', 'sampai' => '2026-02-28'])
        ->and(Period::of('kuartal-ini', null, null, '2026-11-07')->keys())->toBe(['periode' => 'kuartal-ini', 'dari' => '2026-10-01', 'sampai' => '2026-12-31'])
        ->and(Period::of('tahun-ini', null, null, '2026-11-07')->keys())->toBe(['periode' => 'tahun-ini', 'dari' => '2026-01-01', 'sampai' => '2026-12-31'])
        ->and(Period::of('rentang', '2026-10-31', '2026-10-01', '2026-11-07')->keys())->toBe(['periode' => 'rentang', 'dari' => '2026-10-01', 'sampai' => '2026-10-31'])
        ->and(Period::of('rentang', '2020-01-01', '2026-10-31', '2026-11-07')->keys())->toBe(['periode' => 'rentang', 'dari' => '2025-10-31', 'sampai' => '2026-10-31'])
        // Text that is no date, or a preset that is no preset, answers as this month.
        ->and(Period::of('rentang', "2026-10-01' or 1=1", '2026-10-31', '2026-11-07')->preset)->toBe('bulan-ini')
        ->and(Period::of('semua', null, null, '2026-11-07')->preset)->toBe('bulan-ini')
        ->and(Period::of('rentang', '2026-02-30', '2026-03-01', '2026-11-07')->preset)->toBe('bulan-ini');
});
