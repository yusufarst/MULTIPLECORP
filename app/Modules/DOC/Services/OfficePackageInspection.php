<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Enums\DetectedType;
use DOMDocument;
use DOMElement;
use ZipArchive;

/**
 * The structural inspection of a DOCX or XLSX package (SECURITY FL-02, FL-06): the entry count and the
 * uncompressed size are checked before any part is read; then the package's content types, its relationships
 * and its field codes are read with a hardened XML parser — no DTD, external entity or network access — and the
 * package is refused when it holds macros, ActiveX, OLE or embedded objects, external workbook links,
 * external-target relationships other than hyperlinks, data connections or DDE fields. Nothing is previewed,
 * rendered or evaluated.
 */
final class OfficePackageInspection
{
    public const MAXIMUM_ENTRIES = 1000;

    public const MAXIMUM_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    /** Parts whose mere presence is active or embedded content, an external link or a data connection. */
    private const FORBIDDEN_PARTS = '~(^|/)(vbaProject\.bin|vbaData\.xml|activeX/|embeddings/|oleObject|externalLinks/|connections\.xml|customUI/)~i';

    /** Content types of the same kinds, and the macro-enabled packages. */
    private const FORBIDDEN_TYPES = '~(macroEnabled|vbaProject|vbaData|activeX|oleObject|\.package|externalLink|connections)~i';

    private const HYPERLINK = '~/hyperlink$~';

    /** The detected type the package really is — by its main part, whatever its extension claimed. */
    public function inspect(string $path): DetectedType
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UploadRefused(UploadRefused::OFFICE);
        }

        try {
            $names = $this->entries($zip);
            $types = $this->xml($zip, '[Content_Types].xml') ?? throw new UploadRefused(UploadRefused::OFFICE);

            foreach ($types->getElementsByTagName('*') as $element) {
                if (preg_match(self::FORBIDDEN_TYPES, $element->getAttribute('ContentType')) === 1) {
                    throw new UploadRefused(UploadRefused::OFFICE_ACTIVE);
                }
            }

            $kind = match (true) {
                in_array('word/document.xml', $names, true) => DetectedType::Docx,
                in_array('xl/workbook.xml', $names, true) => DetectedType::Xlsx,
                default => throw new UploadRefused(UploadRefused::OFFICE),
            };

            foreach ($names as $name) {
                if (str_ends_with($name, '.rels')) {
                    $this->checkRelationships($zip, $name);
                } elseif (str_ends_with($name, '.xml') && (str_starts_with($name, 'word/') || str_starts_with($name, 'xl/'))) {
                    $this->checkFields($zip, $name);
                }
            }

            return $kind;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    private function entries(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAXIMUM_ENTRIES) {
            throw new UploadRefused(UploadRefused::ARCHIVE_BOMB);
        }

        $names = [];
        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                throw new UploadRefused(UploadRefused::OFFICE);
            }

            $name = $stat['name'];
            $total += $stat['size'];

            if ($total > self::MAXIMUM_UNCOMPRESSED_BYTES) {
                throw new UploadRefused(UploadRefused::ARCHIVE_BOMB);
            }

            if (str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
                throw new UploadRefused(UploadRefused::OFFICE);
            }

            if (preg_match(self::FORBIDDEN_PARTS, $name) === 1) {
                throw new UploadRefused(UploadRefused::OFFICE_ACTIVE);
            }

            $names[] = $name;
        }

        return $names;
    }

    private function checkRelationships(ZipArchive $zip, string $name): void
    {
        $document = $this->xml($zip, $name) ?? throw new UploadRefused(UploadRefused::OFFICE);

        foreach ($document->getElementsByTagName('Relationship') as $relationship) {
            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0
                && preg_match(self::HYPERLINK, $relationship->getAttribute('Type')) !== 1) {
                throw new UploadRefused(UploadRefused::OFFICE_ACTIVE);
            }
        }
    }

    /** DDE fields: a field code — simple or complex — that starts DDE or DDEAUTO, and a workbook's DDE link. */
    private function checkFields(ZipArchive $zip, string $name): void
    {
        $document = $this->xml($zip, $name) ?? throw new UploadRefused(UploadRefused::OFFICE);
        $codes = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            /** @var DOMElement $element */
            match ($element->localName) {
                'instrText' => $codes[] = $element->textContent,
                'fldSimple' => $codes[] = $element->getAttributeNS($element->namespaceURI ?? '', 'instr') ?: $element->getAttribute('w:instr'),
                'ddeLink', 'oleLink' => throw new UploadRefused(UploadRefused::OFFICE_ACTIVE),
                default => null,
            };
        }

        // A complex field's code may be split across runs, so the codes are read together.
        if (preg_match('~(^|\s)DDE(AUTO)?(\s|$)~i', implode('', $codes)) === 1) {
            throw new UploadRefused(UploadRefused::OFFICE_ACTIVE);
        }
    }

    /** A part parsed with no DTD, entity or network access; a part declaring a DTD is refused unread. */
    private function xml(ZipArchive $zip, string $name): ?DOMDocument
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        $content = $zip->getFromName($name);

        if (! is_string($content) || strlen($content) !== $stat['size']) {
            throw new UploadRefused(UploadRefused::OFFICE);
        }

        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new UploadRefused(UploadRefused::OFFICE_ACTIVE);
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new UploadRefused(UploadRefused::OFFICE);
        }

        return $document;
    }
}
