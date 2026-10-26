<?php

namespace App\Modules\DOC\Enums;

/**
 * A file's purpose, mirroring `file_objects`' CHECK (DATABASE §4.10, §22), with the allowlist, the size limit
 * and the sensitivity SECURITY FL-02 and DOMAIN_MODEL give each purpose a person uploads. A rendition and an
 * export are written by the system, never uploaded.
 */
enum FilePurpose: string
{
    case Upload = 'UPLOAD';
    case Rendition = 'RENDITION';
    case IdentityAsset = 'IDENTITY_ASSET';
    case ProductImage = 'PRODUCT_IMAGE';
    case ImportSource = 'IMPORT_SOURCE';
    case Archive = 'ARCHIVE';
    case Export = 'EXPORT';

    private const MEGABYTE = 1024 * 1024;

    /** @return list<DetectedType> FL-02's allowlist of the purpose; none for the system's own files */
    public function allowed(): array
    {
        return match ($this) {
            // Evidence and proofs, and archive items, with the attachment-only office packages.
            self::Upload, self::Archive => [DetectedType::Pdf, DetectedType::Jpeg, DetectedType::Png, DetectedType::Webp, DetectedType::Docx, DetectedType::Xlsx],
            self::ProductImage => [DetectedType::Jpeg, DetectedType::Png, DetectedType::Webp],
            self::IdentityAsset => [DetectedType::Png, DetectedType::Jpeg],
            // A CSV only as an import source (FL-06).
            self::ImportSource => [DetectedType::Csv, DetectedType::Xlsx],
            self::Rendition, self::Export => [],
        };
    }

    public function maximumBytes(): int
    {
        return match ($this) {
            self::ProductImage => 5 * self::MEGABYTE,
            self::IdentityAsset => 2 * self::MEGABYTE,
            default => 20 * self::MEGABYTE,
        };
    }

    /**
     * Displayed images are re-encoded during validation, so the stored object carries no EXIF/GPS or trailing
     * payload; an evidence photo keeps its original bytes, whose metadata can be evidential (FL-03).
     */
    public function reencodesImages(): bool
    {
        return $this === self::ProductImage || $this === self::IdentityAsset;
    }

    /** DATABASE §4.10's sensitivity class of the purpose's files (S0–S4; DOMAIN_MODEL; PJ-20). */
    public function sensitivity(): string
    {
        return match ($this) {
            self::ProductImage => 'S0',
            self::ImportSource => 'S4',
            default => 'S3',
        };
    }
}
