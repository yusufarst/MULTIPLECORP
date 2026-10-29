<?php

namespace App\Modules\PRJ\Data;

/**
 * A project as a higher tier names it (ARCHITECTURE §3): its identifier, company and public identifier, read through
 * PRJ's query classes, so no other module uses PRJ's model.
 */
final class ProjectRef
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $publicId,
    ) {}
}
