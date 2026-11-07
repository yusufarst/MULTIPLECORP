<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;

/**
 * PG-052's inter-company allocation report (RD-07; J-INV-07; PJ-05; PJ-06): the Owner's view of every source draw and
 * every consuming project's allocated cost of the period, both sides in full, one section. An Admin's sides are the
 * purchase report's allocation table on PG-051, masked as PJ-05 and PJ-06 state. One statement.
 */
final class AllocationReport implements Report
{
    public function code(): string
    {
        return 'alokasi';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::Consolidated->admits($viewer) ? ['reports.consolidated'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return $viewer->has('stock.view') ? ['stock.view'] : [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        return [AllocationTable::of($viewer, $filters, pooled: true)];
    }
}
