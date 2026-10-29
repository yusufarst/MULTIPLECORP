<?php

namespace App\Modules\PRJ\Contracts;

use App\Modules\PRJ\Data\ProjectRef;

/**
 * PRJ's inversion contract for the administrative checklist PG-011's "Kesiapan dokumen" carries (ARCHITECTURE §3;
 * D-IA-10; J-ADM-01): ADM implements it (TASK-035) and registers it under {@see self::TAG}, so PRJ's page reads the
 * checklist without calling a higher tier. Read inside the documents group's request, in a bounded number of
 * statements (QB-02).
 */
interface ReadinessProvider
{
    public const TAG = 'prj.readiness-providers';

    /**
     * The project's checklist as the viewer may see it, or null when the viewer may see none.
     *
     * @return ?array<string, mixed>
     */
    public function checklist(int $viewerId, ProjectRef $project): ?array;
}
