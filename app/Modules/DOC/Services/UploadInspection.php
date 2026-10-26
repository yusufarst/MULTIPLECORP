<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\InspectedUpload;
use App\Modules\DOC\Enums\DetectedType;
use App\Modules\DOC\Enums\FilePurpose;
use finfo;

/**
 * SECURITY FL-01's checks in their order, on a file already streamed to temporary storage outside the web
 * root: the size; the type detected from the content — never the client's claim or the broad image rule —;
 * the purpose's allowlist (FL-02), the extension agreeing with the detected type; then the type's own checks —
 * images (FL-03), PDFs (FL-04), office packages (FL-02, FL-06) and CSV (FL-06). Displayed images are
 * re-encoded here, so what is stored for them carries no metadata or trailing payload.
 */
final class UploadInspection
{
    public function __construct(private readonly ImageCodec $images, private readonly OfficePackageInspection $packages) {}

    public function inspect(string $path, string $clientName, FilePurpose $purpose): InspectedUpload
    {
        $size = @filesize($path);

        if ($size === false || $size < 1 || $size > $purpose->maximumBytes()) {
            throw new UploadRefused(UploadRefused::SIZE);
        }

        $type = $this->detect($path);
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if ($type->isOfficePackage()) {
            // The package's main part decides what it is; an extension naming the other kind disagrees.
            $type = $this->packages->inspect($path);
        }

        if (! in_array($type, $purpose->allowed(), true)) {
            throw new UploadRefused(UploadRefused::TYPE);
        }

        if (! in_array($extension, $type->extensions(), true)) {
            throw new UploadRefused(UploadRefused::EXTENSION);
        }

        $bytes = null;

        if ($type->isImage()) {
            $bytes = $purpose->reencodesImages() ? $this->images->reencode($path, $type) : null;

            if ($bytes === null) {
                // An evidence photo keeps its bytes, but is decoded once to prove it is the image it claims.
                $this->images->decode($path, $type);
            }
        } elseif ($type === DetectedType::Pdf) {
            $this->checkPdf($path, $size);
        } elseif ($type === DetectedType::Csv) {
            $this->checkCsv($path);
        }

        return new InspectedUpload($type, $bytes);
    }

    /**
     * The server-detected type: the content's signature read by the system's file-type database — the same
     * detection the framework's `mimetypes` rule makes —, mapped onto FL-02's types. SVG, HTML, scripts and every
     * other type map onto nothing and are refused.
     */
    private function detect(string $path): DetectedType
    {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return match ($detected) {
            'application/pdf' => DetectedType::Pdf,
            'image/jpeg' => DetectedType::Jpeg,
            'image/png' => DetectedType::Png,
            'image/webp' => DetectedType::Webp,
            DetectedType::Docx->value => DetectedType::Docx,
            DetectedType::Xlsx->value => DetectedType::Xlsx,
            // A package whose first entry the database cannot place is read structurally to tell what it is.
            'application/zip', 'application/octet-stream' => $this->isZip($path) ? DetectedType::Docx : throw new UploadRefused(UploadRefused::TYPE),
            'text/csv', 'text/plain', 'application/csv' => DetectedType::Csv,
            default => throw new UploadRefused(UploadRefused::TYPE),
        };
    }

    private function isZip(string $path): bool
    {
        $handle = fopen($path, 'rb');
        $head = $handle === false ? '' : (string) fread($handle, 4);

        if ($handle !== false) {
            fclose($handle);
        }

        return $head === "PK\x03\x04";
    }

    /** FL-04: the header and the structure's trailer; the file is never rendered from the application origin. */
    private function checkPdf(string $path, int $size): void
    {
        $handle = fopen($path, 'rb') ?: throw new UploadRefused(UploadRefused::PDF);
        $head = (string) fread($handle, 1024);
        fseek($handle, max(0, $size - 2048));
        $tail = (string) fread($handle, 2048);
        fclose($handle);

        if (preg_match('~^%PDF-[12]\.\d~', $head) !== 1 || ! str_contains($tail, 'startxref') || ! str_contains($tail, '%%EOF')) {
            throw new UploadRefused(UploadRefused::PDF);
        }
    }

    /** FL-06: UTF-8 text, read as data only — its rows and columns are the import's parser's (TASK-057). */
    private function checkCsv(string $path): void
    {
        $content = (string) file_get_contents($path);

        if (str_starts_with($content, "\u{FEFF}")) {
            $content = substr($content, 3);
        }

        if (str_contains($content, "\0") || ! mb_check_encoding($content, 'UTF-8')) {
            throw new UploadRefused(UploadRefused::CSV);
        }
    }
}
