<?php

namespace App\Modules\Reporting\Exports;

use Illuminate\Support\Facades\File;
use LogicException;
use ZipArchive;

/**
 * A streaming XLSX writer over the platform's ZipArchive (FL-09; QB-15; TASK-057's reader the same way: no package):
 * each worksheet's rows appended to its own temporary part as they arrive, so memory holds one row, then the package
 * assembled once. Typed cells only — a number as a number, a text as an inline string neutralized by CellText —, never a
 * formula, a shared string table or an external reference.
 */
final class XlsxWriter
{
    /** @var list<array{name: string, path: string}> */
    private array $sheets = [];

    /** @var resource|null */
    private $current = null;

    public function __construct(private readonly string $directory) {}

    public function sheet(string $name): void
    {
        $this->closeSheet();
        $path = $this->directory.'/sheet'.(count($this->sheets) + 1).'.xml';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new LogicException('An export part could not be opened.');
        }

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        $this->current = $handle;
        $this->sheets[] = ['name' => self::sheetName($name, count($this->sheets) + 1), 'path' => $path];
    }

    /**
     * One row: each cell a number (`true`) or a text (`false`), empty when null.
     *
     * @param  list<?string>  $cells
     * @param  list<bool>  $numeric
     */
    public function row(array $cells, array $numeric = []): void
    {
        if ($this->current === null) {
            throw new LogicException('A row is written to a sheet.');
        }

        $xml = '<row>';

        foreach ($cells as $index => $cell) {
            if ($cell === null || $cell === '') {
                $xml .= '<c/>';
            } elseif (($numeric[$index] ?? false) && is_numeric($cell)) {
                $xml .= '<c t="n"><v>'.$cell.'</v></c>';
            } else {
                $xml .= '<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars(CellText::neutral($cell), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
        }

        fwrite($this->current, $xml.'</row>');
    }

    /** Assembles the package at `$target`. */
    public function finish(string $target): void
    {
        $this->closeSheet();
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new LogicException('An export package could not be created.');
        }

        $overrides = '';
        $entries = '';
        $relations = '';

        foreach ($this->sheets as $index => $sheet) {
            $number = $index + 1;
            $zip->addFile($sheet['path'], "xl/worksheets/sheet{$number}.xml");
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$number}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $entries .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."\" sheetId=\"{$number}\" r:id=\"rId{$number}\"/>";
            $relations .= "<Relationship Id=\"rId{$number}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$number}.xml\"/>";
        }

        $styles = count($this->sheets) + 1;
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$entries.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations."<Relationship Id=\"rId{$styles}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/></Relationships>");
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');

        if ($zip->close() !== true) {
            throw new LogicException('An export package could not be written.');
        }

        foreach ($this->sheets as $sheet) {
            File::delete($sheet['path']);
        }
    }

    private function closeSheet(): void
    {
        if ($this->current !== null) {
            fwrite($this->current, '</sheetData></worksheet>');
            fclose($this->current);
            $this->current = null;
        }
    }

    /** A worksheet name Excel accepts: no `[]:*?/\`, at most 31 characters, unique by its number when cut. */
    private static function sheetName(string $name, int $number): string
    {
        $name = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name));

        return mb_substr($name === '' ? "Lembar {$number}" : $name, 0, 28).($number > 1 && mb_strlen($name) > 28 ? " {$number}" : '');
    }
}
