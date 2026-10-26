<?php

namespace App\Modules\ORG\Data;

/** A number drawn at an issue (SF-ISSUE; NM-02, NM-07): stored with the record and never recomputed. */
final class DrawnNumber
{
    public function __construct(
        public readonly int $schemeId,
        public readonly string $periodKey,
        public readonly int $sequenceNo,
        public readonly string $formatted,
    ) {}
}
