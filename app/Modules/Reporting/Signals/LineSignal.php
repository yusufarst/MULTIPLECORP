<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;

/**
 * A pooled line signal — QS-01–QS-04 and QS-24 (PERMISSIONS_MATRIX RD-02, `DIR-077` CA-07) —: read with `stock.view`
 * for the companies of the view, its project part — the project's number — only where the viewer also holds
 * `projects.view` in the row's company (RD-03; PJ-13).
 */
abstract class LineSignal extends Signal
{
    public function companies(Context $context): array
    {
        return $context->holding('stock.view');
    }

    protected function ref(Context $context, object $row): ?string
    {
        return ProjectLines::project($context, $row, Row::nullable($row, 'ref'));
    }

    /** The line's project page for a viewer who reads the project, else nothing (INT-019). */
    protected static function project(Context $context, object $row): ?string
    {
        return $context->viewer->allows('projects.view', self::companyOf($row)) ? '/proyek/'.Row::text($row, 'target') : null;
    }
}
