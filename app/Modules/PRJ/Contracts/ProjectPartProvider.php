<?php

namespace App\Modules\PRJ\Contracts;

use App\Modules\IAM\Data\Authority;

/**
 * A part of *Rincian proyek* on PG-011 that a higher tier owns (ARCHITECTURE §3; TASK-037's *Pengadaan*): read through that module's query class, null when the viewer's family does not admit it, so the part is absent, never masked.
 */
interface ProjectPartProvider
{
    public const TAG = 'prj.project-parts';

    public function key(): string;

    /** @return array<string, mixed>|null */
    public function part(Authority $viewer, int $projectId, int $companyId): ?array;
}
