<?php

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Services\SourceRefused;
use App\Modules\ImportOrchestration\Services\SourceTable;

// TL-03 (TASK-057; FL-06; WF-MIG-01; FX-09): an import source read as data only — a UTF-8 CSV with either delimiter, the first worksheet of an
// XLSX package with its shared strings, a formula cell marked and never evaluated, a DTD refused unread —, its rows keyed by the header with their
// numbers; and a row's cells read as typed values, each unreadable one with its column and reason.

function sourceFile(string $bytes, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'src-').".{$extension}";
    file_put_contents($path, $bytes);

    return $path;
}

/** @param  array<string, string>  $parts */
function xlsxFile(array $parts): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($parts as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return $path;
}

function workbook(string $sheet): array
{
    return [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/data.xml"/></Relationships>',
        'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>id</t></si><si><t>nama</t></si><si><t>jumlah</t></si><si><r><t>Meja </t></r><r><t>Guru</t></r></si></sst>',
        'xl/worksheets/data.xml' => $sheet,
    ];
}

it('reads a UTF-8 CSV with its BOM and either delimiter, rows keyed by the header with their numbers, empty rows skipped (FL-06)', function () {
    $table = new SourceTable;

    expect($table->read(sourceFile("\xEF\xBB\xBFID;Nama\nK1;\"SMA 1; Contoh\"\n\nK2;SMP 2\n", 'csv'), 'csv'))->toBe([
        ['number' => 2, 'cells' => ['id' => 'K1', 'nama' => 'SMA 1; Contoh']],
        ['number' => 4, 'cells' => ['id' => 'K2', 'nama' => 'SMP 2']],
    ])->and($table->read(sourceFile("id,nama\nK1,Satu,lebih\n", 'csv'), 'csv')[0]['cells'])->toHaveKey('#2');
});

it('refuses a source that is not UTF-8, has no header or repeats a column (FL-06)', function (string $bytes, string $reason) {
    expect(fn () => (new SourceTable)->read(sourceFile($bytes, 'csv'), 'csv'))->toThrow(fn (SourceRefused $refused) => expect($refused->reason)->toBe($reason));
})->with([
    'Latin-1' => ["id,nama\nK1,Caf\xE9\n", SourceRefused::ENCODING],
    'empty' => ['', SourceRefused::EMPTY],
    'a repeated column' => ["id,id\nK1,K2\n", SourceRefused::HEADER],
]);

it('reads the first worksheet of an XLSX through its relationships and shared strings, a formula marked and never evaluated (FL-06)', function () {
    $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
        .'<row r="2"><c r="A2" t="inlineStr"><is><t>P1</t></is></c><c r="B2" t="s"><v>3</v></c><c r="C2"><v>12.5</v></c></row>'
        .'<row r="3"><c r="A3" t="inlineStr"><is><t>P2</t></is></c><c r="C3"><f>SUM(C1:C2)</f><v>99</v></c></row>'
        .'</sheetData></worksheet>';
    $rows = (new SourceTable)->read(xlsxFile(workbook($sheet)), 'xlsx');

    expect($rows[0])->toBe(['number' => 2, 'cells' => ['id' => 'P1', 'nama' => 'Meja Guru', 'jumlah' => '12,5']])
        ->and($rows[1]['cells']['jumlah'])->toBe(SourceTable::FORMULA);

    $cells = new Cells($rows[1]['cells']);
    $cells->checkShape();
    expect($cells->errors)->toBe([['field' => 'jumlah', 'reason' => 'formula']]);
});

it('refuses an XLSX part declaring a DTD unread (FL-06; no external entity)', function () {
    $sheet = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>&e;</t></is></c></row></sheetData></worksheet>';

    expect(fn () => (new SourceTable)->read(xlsxFile(workbook($sheet)), 'xlsx'))->toThrow(fn (SourceRefused $refused) => expect($refused->reason)->toBe(SourceRefused::PACKAGE));
});

it('reads typed values and names each unreadable one with its column and reason (WF-MIG-01)', function () {
    $cells = new Cells(['nilai' => '1.250.000,50', 'harga' => '1,250,000.5', 'jumlah' => '2.5', 'tanggal' => '31/12/2025', 'hari' => '46022', 'salah' => '2026-02-31', 'kata' => 'Gudang', 'minus' => '-3']);

    expect($cells->decimal('nilai', 2))->toBe('1250000.50')
        ->and($cells->decimal('harga', 2))->toBe('1250000.50')
        ->and($cells->decimal('jumlah', 0))->toBeNull()
        ->and($cells->date('tanggal'))->toBe('2025-12-31')
        ->and($cells->date('hari'))->toBe('2025-12-31')
        ->and($cells->date('salah'))->toBeNull()
        ->and($cells->choice('kata', ['gudang' => 'WAREHOUSE']))->toBe('WAREHOUSE')
        ->and($cells->decimal('minus', 0, positive: true))->toBeNull()
        ->and($cells->text('hilang', true))->toBeNull()
        ->and($cells->errors)->toBe([
            ['field' => 'jumlah', 'reason' => 'scale'], ['field' => 'salah', 'reason' => 'date'], ['field' => 'minus', 'reason' => 'negative'], ['field' => 'hilang', 'reason' => 'required'],
        ]);
});

it('reads Indonesian thousands as thousands, never as decimals, and a leading zero group as decimals (WF-MIG-01; ER-06)', function () {
    $cells = new Cells(['ribuan' => '250.000', 'jumlah' => '1.500', 'pecahan' => '0.125', 'koma' => '12,5']);

    expect($cells->decimal('ribuan', 2))->toBe('250000.00')
        ->and($cells->decimal('jumlah', 3))->toBe('1500.000')
        ->and($cells->decimal('pecahan', 3))->toBe('0.125')
        ->and($cells->decimal('koma', 2))->toBe('12.50')
        ->and($cells->errors)->toBe([]);
});
