<?php

namespace App\Modules\DOC\Services;

use RuntimeException;

/**
 * An upload past WS-12's bound — 60 uploads an hour or the daily byte budget of the account —, counted from the
 * durable `file_objects` rows (H6-07): nothing is stored, and the account sees MSG-20 with the minutes left.
 */
final class UploadLimited extends RuntimeException
{
    public function __construct(public readonly int $minutes, public readonly string $bound)
    {
        parent::__construct("The upload is past WS-12's {$bound} bound.");
    }
}
