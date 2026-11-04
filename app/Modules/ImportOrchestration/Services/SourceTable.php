<?php

namespace App\Modules\ImportOrchestration\Services;

use XMLReader;
use ZipArchive;

/**
 * An import source read as data only (SECURITY FL-06; RELEASE_PLAN IMP-12): a UTF-8 CSV or the first worksheet of an
 * XLSX package — its entry count and uncompressed size checked before any part is read, each part streamed with no
 * DTD, entity or network access —, a formula never evaluated, its cell marked so the row is refused; the header row
 * names the columns, each later row keyed by them with its row number. Nothing here runs inside a transaction
 * (ARCHITECTURE §15).
 */
final class SourceTable
{
    /** AS-04's volume with room: the rows a source may hold after its header. */
    public const MAXIMUM_ROWS = 30_000;

    public const MAXIMUM_COLUMNS = 60;

    public const MAXIMUM_CELL = 2000;

    private const MAXIMUM_ENTRIES = 1000;

    private const MAXIMUM_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    /** The mark a formula cell carries in place of its value: the row is refused, the cached value never read. */
    public const FORMULA = "\u{E000}formula";

    /**
     * @return list<array{number: int, cells: array<string, string>}>
     */
    public function read(string $path, string $extension): array
    {
        $grid = strtolower($extension) === 'xlsx' ? $this->xlsx($path) : $this->csv($path);
        $header = array_shift($grid);

        if ($header === null) {
            throw new SourceRefused(SourceRefused::EMPTY);
        }

        $names = array_map(static fn (string $name): string => strtolower(trim($name)), $header['cells']);

        if ($names === [] || count($names) > self::MAXIMUM_COLUMNS || in_array('', $names, true) || count(array_unique($names)) !== count($names)) {
            throw new SourceRefused(SourceRefused::HEADER);
        }

        $rows = [];

        foreach ($grid as $line) {
            if (implode('', $line['cells']) === '') {
                continue;
            }

            if (count($rows) >= self::MAXIMUM_ROWS) {
                throw new SourceRefused(SourceRefused::TOO_MANY_ROWS);
            }

            $cells = [];

            foreach ($names as $index => $name) {
                $cells[$name] = trim($line['cells'][$index] ?? '');
            }

            // A value beyond the header's columns is kept under its position, so the row is refused rather than silently cut.
            foreach (array_slice($line['cells'], count($names), null, true) as $index => $extra) {
                if (trim($extra) !== '') {
                    $cells["#{$index}"] = $extra;
                }
            }

            $rows[] = ['number' => $line['number'], 'cells' => $cells];
        }

        return $rows;
    }

    /** @return list<array{number: int, cells: list<string>}> */
    private function csv(string $path): array
    {
        $content = file_get_contents($path);

        if (! is_string($content)) {
            throw new SourceRefused(SourceRefused::UNREADABLE);
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw new SourceRefused(SourceRefused::ENCODING);
        }

        $first = strtok($content, "\n");
        $delimiter = is_string($first) && substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            throw new SourceRefused(SourceRefused::UNREADABLE);
        }

        fwrite($stream, $content);
        rewind($stream);
        $grid = [];
        $number = 0;

        try {
            while (($fields = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                $number++;

                if ($number > self::MAXIMUM_ROWS + 1) {
                    throw new SourceRefused(SourceRefused::TOO_MANY_ROWS);
                }

                $cells = array_map(static fn (?string $field): string => $field ?? '', $fields);
                $this->checkWidth($cells);
                $grid[] = ['number' => $number, 'cells' => $cells];
            }
        } finally {
            fclose($stream);
        }

        return $grid;
    }

    /** @return list<array{number: int, cells: list<string>}> */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SourceRefused(SourceRefused::UNREADABLE);
        }

        try {
            $this->checkPackage($zip);
            $strings = $this->sharedStrings($zip);

            return $this->sheet($zip, $this->firstSheet($zip), $strings);
        } finally {
            $zip->close();
        }
    }

    private function checkPackage(ZipArchive $zip): void
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAXIMUM_ENTRIES) {
            throw new SourceRefused(SourceRefused::PACKAGE);
        }

        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $total += $stat === false ? PHP_INT_MAX : $stat['size'];

            if ($total > self::MAXIMUM_UNCOMPRESSED_BYTES) {
                throw new SourceRefused(SourceRefused::PACKAGE);
            }
        }
    }

    /** The first worksheet's part, as the workbook's relationships place it. */
    private function firstSheet(ZipArchive $zip): string
    {
        $relation = null;
        $reader = $this->part($zip, 'xl/workbook.xml') ?? throw new SourceRefused(SourceRefused::PACKAGE);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $relation = $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                break;
            }
        }

        $reader->close();
        $relations = $this->part($zip, 'xl/_rels/workbook.xml.rels');

        if ($relation === null || $relations === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $target = null;

        while ($relations->read()) {
            if ($relations->nodeType === XMLReader::ELEMENT && $relations->localName === 'Relationship' && $relations->getAttribute('Id') === $relation) {
                $target = $relations->getAttribute('Target');
                break;
            }
        }

        $relations->close();

        if ($target === null || str_contains($target, '..') || str_contains($target, ':')) {
            return 'xl/worksheets/sheet1.xml';
        }

        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $reader = $this->part($zip, 'xl/sharedStrings.xml');

        if ($reader === null) {
            return [];
        }

        $strings = [];
        $current = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $current !== null) {
                $current .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $current ?? '';
                $current = null;
            }
        }

        $reader->close();

        return $strings;
    }

    /**
     * @param  list<string>  $strings
     * @return list<array{number: int, cells: list<string>}>
     */
    private function sheet(ZipArchive $zip, string $name, array $strings): array
    {
        $reader = $this->part($zip, $name) ?? throw new SourceRefused(SourceRefused::PACKAGE);
        $grid = [];
        $row = null;
        $cells = [];
        $cell = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                $row = (int) $reader->getAttribute('r');
                $cells = [];

                if ($row < 1 || $row > self::MAXIMUM_ROWS + 1) {
                    throw new SourceRefused(SourceRefused::TOO_MANY_ROWS);
                }
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c' && $row !== null) {
                $column = self::column((string) $reader->getAttribute('r'), count($cells));
                $cell = ['column' => $column, 'type' => (string) $reader->getAttribute('t'), 'value' => '', 'formula' => false];

                if ($reader->isEmptyElement) {
                    $cells[$column] = '';
                    $cell = null;
                }
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $cell !== null && $reader->localName === 'f') {
                $cell['formula'] = true;
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $cell !== null && in_array($reader->localName, ['v', 't'], true)) {
                $cell['value'] .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $cell !== null) {
                $cells[$cell['column']] = match (true) {
                    $cell['formula'] => self::FORMULA,
                    $cell['type'] === 's' => $strings[(int) $cell['value']] ?? '',
                    $cell['type'] === 'b' => $cell['value'] === '1' ? 'TRUE' : 'FALSE',
                    default => $cell['value'],
                };
                $cell = null;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row' && $row !== null) {
                if ($cells !== [] && max(array_keys($cells)) >= self::MAXIMUM_COLUMNS * 2) {
                    throw new SourceRefused(SourceRefused::TOO_MANY_COLUMNS);
                }

                $line = $cells === [] ? [] : array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
                $this->checkWidth($line);
                $grid[] = ['number' => $row, 'cells' => array_values($line)];
                $row = null;
            }
        }

        $reader->close();

        return $grid;
    }

    /** A part streamed with no DTD, entity or network access; a part declaring a DTD is refused unread. */
    private function part(ZipArchive $zip, string $name): ?XMLReader
    {
        $content = $zip->getFromName($name);

        if ($content === false) {
            return null;
        }

        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new SourceRefused(SourceRefused::PACKAGE);
        }

        $reader = XMLReader::XML($content, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT);

        if (! $reader instanceof XMLReader) {
            throw new SourceRefused(SourceRefused::PACKAGE);
        }

        return $reader;
    }

    /** @param  list<string>|array<int, string>  $cells */
    private function checkWidth(array $cells): void
    {
        if (count($cells) > self::MAXIMUM_COLUMNS * 2) {
            throw new SourceRefused(SourceRefused::TOO_MANY_COLUMNS);
        }

        foreach ($cells as $value) {
            if (mb_strlen($value) > self::MAXIMUM_CELL) {
                throw new SourceRefused(SourceRefused::CELL);
            }
        }
    }

    /** A cell reference's zero-based column — "C7" is 2 —, or the next position when the reference is absent. */
    public static function column(string $reference, int $next): int
    {
        if (preg_match('/^([A-Z]{1,3})\d+$/', $reference, $match) !== 1) {
            return $next;
        }

        $index = 0;

        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
