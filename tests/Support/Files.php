<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Synthetic files for the upload pipeline's tests (SECURITY_TEST_MATRIX SM-09; H8-09): each written to a fresh
 * temporary path, built here from code — no real document, photo or personal data —, the hostile ones carrying
 * exactly the element a check must find and nothing that could act: a field code names no program, a link
 * points at a reserved domain.
 */
final class Files
{
    public static function path(string $bytes): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload-');
        file_put_contents($path, $bytes);

        return $path;
    }

    /** A JPEG with an Exif APP1 segment naming a GPS position, and bytes trailing its end marker. */
    public static function jpegWithMetadata(int $width = 64, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 40, 40));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        $exif = 'Exif'."\0\0".'GPS-LATITUDE-SECRET';
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2).'TRAILING-PAYLOAD';
    }

    public static function png(int $width = 32, int $height = 32): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function webp(int $width = 32, int $height = 32): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagewebp($image);

        return (string) ob_get_clean();
    }

    /** A PNG whose header claims the given size — a decompression bomb is refused before any decode (FL-03). */
    public static function pngHeader(int $width, int $height): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
    }

    public static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\nstartxref\n9\n%%EOF\n";
    }

    public static function svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
    }

    /**
     * An office package: DOCX by default, XLSX with `$workbook`; `$extra` adds or replaces parts.
     *
     * @param  array<string, string>  $extra
     */
    public static function office(bool $workbook = false, array $extra = [], int $padding = 0): string
    {
        $main = $workbook ? 'xl/workbook.xml' : 'word/document.xml';
        $type = $workbook ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/'.$main.'" ContentType="'.$type.'"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$main.'"/></Relationships>',
            $main => $workbook
                ? '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets/></workbook>'
                : self::document('<w:r><w:t>Halo</w:t></w:r>'),
            ...$extra,
        ];
        $path = (string) tempnam(sys_get_temp_dir(), 'office-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($parts as $name => $content) {
            $zip->addFromString($name, $content);
        }

        for ($index = 0; $index < $padding; $index++) {
            $zip->addFromString("word/media/filler-{$index}.bin", str_repeat("\0", 1024 * 1024));
        }

        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /**
     * A DOCX whose field code is a DDE field split across two runs, as a hostile file would write it; its code is
     * assembled here and names no program — the inspection refuses the field kind itself.
     */
    public static function docxWithDdeField(): string
    {
        $kind = implode('', ['D', 'D', 'E']);
        $runs = '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
            .'<w:r><w:instrText xml:space="preserve"> '.$kind.'</w:instrText></w:r>'
            .'<w:r><w:instrText xml:space="preserve">AUTO "Server" "Topic" "Item"</w:instrText></w:r>'
            .'<w:r><w:fldChar w:fldCharType="end"/></w:r>';

        return self::office(extra: ['word/document.xml' => self::document($runs)]);
    }

    /** A DOCX whose DDE field follows an ordinary one — simple fields, or complex fields one after the other. */
    public static function docxWithDdeAfterField(bool $simple): string
    {
        $kind = implode('', ['D', 'D', 'E']).'AUTO "Server" "Topic"';
        $runs = $simple
            ? '<w:fldSimple w:instr="DATE"/><w:fldSimple w:instr="'.htmlspecialchars($kind).'"/>'
            : '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>DATE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
                .'<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>'.htmlspecialchars($kind).'</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>';

        return self::office(extra: ['word/document.xml' => self::document($runs)]);
    }

    /** A document part holding one simple field with the given code. */
    public static function documentWithField(string $code): string
    {
        return self::document('<w:fldSimple w:instr="'.htmlspecialchars($code).'"/>');
    }

    private static function document(string $runs): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>'.$runs.'</w:p></w:body></w:document>';
    }
}
