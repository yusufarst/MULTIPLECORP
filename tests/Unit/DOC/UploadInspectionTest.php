<?php

use App\Modules\DOC\Enums\DetectedType;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Services\UploadInspection;
use App\Modules\DOC\Services\UploadRefused;
use Tests\Support\Files;

// TL-03 (TASK-022; SECURITY FL-01–FL-06; SM-09's elements of H8-09; criterion 2): the upload checks in FL-01's
// order — size, the type detected from the content, the purpose's allowlist with the extension agreeing — and each
// type's own: images decoded only by their own decoder after a header check that stops a pixel bomb, re-encoded for
// a displayed purpose so no EXIF/GPS or trailing payload is kept while evidence keeps its bytes; PDFs by header and
// trailer; office packages structurally, refused for macros, ActiveX, embedded objects, external links, data
// connections, DDE fields, a DTD or a bomb; CSV as UTF-8 data for an import source only.

function inspect(string $bytes, string $name, FilePurpose $purpose): DetectedType
{
    return app(UploadInspection::class)->inspect(Files::path($bytes), $name, $purpose)->type;
}

function refusal(string $bytes, string $name, FilePurpose $purpose): ?string
{
    try {
        inspect($bytes, $name, $purpose);
    } catch (UploadRefused $refused) {
        return $refused->reason;
    }

    return null;
}

it('admits each purpose its allowlisted types, named by their content (FL-02)', function () {
    expect(inspect(Files::pdf(), 'nota.pdf', FilePurpose::Upload))->toBe(DetectedType::Pdf)
        ->and(inspect(Files::jpegWithMetadata(), 'foto.jpeg', FilePurpose::Upload))->toBe(DetectedType::Jpeg)
        ->and(inspect(Files::webp(), 'produk.webp', FilePurpose::ProductImage))->toBe(DetectedType::Webp)
        ->and(inspect(Files::png(), 'logo.png', FilePurpose::IdentityAsset))->toBe(DetectedType::Png)
        ->and(inspect(Files::office(), 'surat.docx', FilePurpose::Upload))->toBe(DetectedType::Docx)
        ->and(inspect(Files::office(workbook: true), 'rekap.xlsx', FilePurpose::Archive))->toBe(DetectedType::Xlsx)
        ->and(inspect("kode,nama\nP-01,Pensil\n", 'impor.csv', FilePurpose::ImportSource))->toBe(DetectedType::Csv);
});

it('refuses a file above its purpose\'s limit, an empty one, and every type outside the allowlist (FL-02)', function () {
    expect(refusal(Files::png().str_repeat("\0", 2 * 1024 * 1024), 'logo.png', FilePurpose::IdentityAsset))->toBe(UploadRefused::SIZE)
        ->and(refusal('', 'kosong.pdf', FilePurpose::Upload))->toBe(UploadRefused::SIZE)
        ->and(refusal(Files::svg(), 'gambar.svg', FilePurpose::ProductImage))->toBe(UploadRefused::TYPE)
        ->and(refusal('<html><body>halaman</body></html>', 'foto.png', FilePurpose::ProductImage))->toBe(UploadRefused::TYPE)
        ->and(refusal("push graphic-context\nviewbox 0 0 640 480\nimage over 0,0 0,0 'https://example.invalid/x.jpg'\n", 'foto.png', FilePurpose::ProductImage))->toBe(UploadRefused::TYPE)
        ->and(refusal("GIF89a\x01\x00\x01\x00\x00\x00\x00;", 'foto.png', FilePurpose::ProductImage))->toBe(UploadRefused::TYPE)
        ->and(refusal(Files::pdf(), 'produk.pdf', FilePurpose::ProductImage))->toBe(UploadRefused::TYPE)
        ->and(refusal("a,b\n1,2\n", 'bukti.csv', FilePurpose::Upload))->toBe(UploadRefused::TYPE)
        ->and(refusal("MZ\x90\x00".str_repeat("\0", 60), 'alat.pdf', FilePurpose::Upload))->toBe(UploadRefused::TYPE);
});

it('refuses an extension that disagrees with the detected type (FL-01)', function () {
    expect(refusal(Files::png(), 'foto.jpg', FilePurpose::Upload))->toBe(UploadRefused::EXTENSION)
        ->and(refusal(Files::pdf(), 'nota.png', FilePurpose::Upload))->toBe(UploadRefused::EXTENSION)
        ->and(refusal(Files::office(workbook: true), 'surat.docx', FilePurpose::Upload))->toBe(UploadRefused::EXTENSION);
});

it('reads an image\'s dimensions from its header before any decode, refusing a pixel bomb (FL-03; H9-12)', function () {
    expect(refusal(Files::pngHeader(10_001, 10), 'bom.png', FilePurpose::Upload))->toBe(UploadRefused::IMAGE_DIMENSIONS)
        ->and(refusal(Files::pngHeader(7000, 7000), 'bom.png', FilePurpose::Upload))->toBe(UploadRefused::IMAGE_DIMENSIONS)
        // A header that passes, then a body that is not the image: the decoder of its own type refuses it.
        ->and(refusal(Files::pngHeader(100, 100), 'rusak.png', FilePurpose::Upload))->toBe(UploadRefused::IMAGE);
});

it('re-encodes a displayed image without its metadata or trailing payload, and keeps an evidence photo\'s bytes (FL-03; FL-05)', function () {
    $photo = Files::jpegWithMetadata();
    $product = app(UploadInspection::class)->inspect(Files::path($photo), 'produk.jpg', FilePurpose::ProductImage);
    $evidence = app(UploadInspection::class)->inspect(Files::path($photo), 'bukti.jpg', FilePurpose::Upload);

    expect($product->reencoded)->toBeString()
        ->and($product->reencoded)->not->toContain('GPS-LATITUDE-SECRET')
        ->and($product->reencoded)->not->toContain('TRAILING-PAYLOAD')
        ->and(getimagesizefromstring((string) $product->reencoded)[2] ?? null)->toBe(IMAGETYPE_JPEG)
        ->and($evidence->reencoded)->toBeNull();
});

it('checks a PDF\'s header and trailer (FL-04)', function () {
    expect(refusal("%PDF-1.4\n1 0 obj<<>>endobj\n", 'potong.pdf', FilePurpose::Upload))->toBe(UploadRefused::PDF)
        ->and(refusal("%PDF-9.9\nstartxref\n0\n%%EOF", 'aneh.pdf', FilePurpose::Upload))->toBe(UploadRefused::PDF);
});

it('refuses an office package holding active, embedded or external content (FL-02; H8-09)', function (array $extra) {
    expect(refusal(Files::office(extra: $extra), 'surat.docx', FilePurpose::Upload))->toBe(UploadRefused::OFFICE_ACTIVE);
})->with([
    'macros' => [['word/vbaProject.bin' => 'macro']],
    'ActiveX' => [['word/activeX/activeX1.xml' => '<x/>']],
    'an embedded object' => [['word/embeddings/oleObject1.bin' => 'ole']],
    'an external workbook link' => [['xl/externalLinks/externalLink1.xml' => '<externalLink/>']],
    'a data connection' => [['xl/connections.xml' => '<connections/>']],
    'a macro-enabled content type' => [['[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>']],
    'an external-target relationship' => [['word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://example.invalid/t.dotm" TargetMode="External"/></Relationships>']],
    'a DTD with an entity (XXE)' => [['word/document.xml' => '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">&x;</w:document>']],
]);

it('keeps an external hyperlink and refuses a DDE field split across runs (FL-02)', function () {
    $hyperlink = Files::office(extra: ['word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.invalid/" TargetMode="External"/></Relationships>']);

    expect(inspect($hyperlink, 'surat.docx', FilePurpose::Upload))->toBe(DetectedType::Docx)
        ->and(refusal(Files::docxWithDdeField(), 'surat.docx', FilePurpose::Upload))->toBe(UploadRefused::OFFICE_ACTIVE);
});

it('refuses a package by its entry count or its uncompressed size before reading a part (FL-02; FL-06)', function () {
    $entries = [];

    for ($index = 0; $index <= 1000; $index++) {
        $entries["word/media/e{$index}.txt"] = 'x';
    }

    expect(refusal(Files::office(extra: $entries), 'surat.docx', FilePurpose::Upload))->toBe(UploadRefused::ARCHIVE_BOMB)
        ->and(refusal(Files::office(workbook: true, padding: 101), 'impor.xlsx', FilePurpose::ImportSource))->toBe(UploadRefused::ARCHIVE_BOMB);
});

it('admits a CSV only as UTF-8 data (FL-06)', function () {
    expect(inspect("\u{FEFF}kode;nama\nP-01;Pensil biru\n", 'impor.csv', FilePurpose::ImportSource))->toBe(DetectedType::Csv)
        ->and(refusal("kode,nama\nP-01,Pensil \xE9\xE8\n", 'impor.csv', FilePurpose::ImportSource))->toBe(UploadRefused::CSV);
});
