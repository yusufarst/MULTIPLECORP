<?php

namespace App\Modules\DOC\Services;

use RuntimeException;

/**
 * An upload the pipeline refuses (SECURITY FL-01–FL-06): nothing is stored, the account sees MSG-31, and the
 * refusal's code — never the file's content — goes into the UPLOAD_REJECTED security event (LG-02, LG-03).
 */
final class UploadRefused extends RuntimeException
{
    public const SIZE = 'size';

    public const TYPE = 'type';

    public const EXTENSION = 'extension';

    public const IMAGE = 'image';

    public const IMAGE_DIMENSIONS = 'image_dimensions';

    public const PDF = 'pdf';

    public const OFFICE = 'office';

    public const OFFICE_ACTIVE = 'office_active';

    public const ARCHIVE_BOMB = 'archive_bomb';

    public const CSV = 'csv';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("The upload is refused: {$reason} (SECURITY FL-01–FL-06).");
    }
}
