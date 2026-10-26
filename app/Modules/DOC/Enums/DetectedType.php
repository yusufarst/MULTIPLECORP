<?php

namespace App\Modules\DOC\Enums;

/**
 * The types SECURITY FL-02 admits, each named by the content the pipeline detects — never by the client's
 * claim —, with the media type a download states (FL-07) and the extensions that may name it (FL-01).
 */
enum DetectedType: string
{
    case Pdf = 'application/pdf';
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Webp = 'image/webp';
    case Docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case Xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case Csv = 'text/csv';

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Jpeg => ['jpg', 'jpeg'],
            self::Png => ['png'],
            self::Webp => ['webp'],
            self::Docx => ['docx'],
            self::Xlsx => ['xlsx'],
            self::Csv => ['csv'],
        };
    }

    public function isImage(): bool
    {
        return in_array($this, [self::Jpeg, self::Png, self::Webp], true);
    }

    /** An office package, inspected structurally only and served only as an attachment (FL-02). */
    public function isOfficePackage(): bool
    {
        return $this === self::Docx || $this === self::Xlsx;
    }
}
