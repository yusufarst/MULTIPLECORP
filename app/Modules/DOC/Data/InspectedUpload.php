<?php

namespace App\Modules\DOC\Data;

use App\Modules\DOC\Enums\DetectedType;

/** An upload that passed FL-01's checks: its detected type and, for a re-encoded image, the bytes to store. */
final readonly class InspectedUpload
{
    public function __construct(public DetectedType $type, public ?string $reencoded) {}
}
