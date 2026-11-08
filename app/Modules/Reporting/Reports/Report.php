<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;

/**
 * One report of PG-051 or PG-052 (ARCHITECTURE §14; RD-02–RD-07): a read-only query class implementing its CALC-
 * definitions, the scope applied inside each statement before aggregation (CS-07). What opens it is the viewer's read
 * classes; what it shows beyond that — a project's number, a value, a lot's code — its masking capabilities (PJ-22),
 * which an export records with its company set (DP-07). The page, its print view and its export read the same tables.
 */
interface Report
{
    /** Its address key, `laporan`. */
    public function code(): string;

    /**
     * The view capabilities the viewer holds that open it; none and the report is absent.
     *
     * @return list<string>
     */
    public function opens(Authority $viewer): array;

    /**
     * The capabilities the viewer holds that widen what it shows (DP-07's masking context).
     *
     * @return list<string>
     */
    public function masking(Authority $viewer): array;

    /**
     * Its tables for the viewer and filters, each read inside the caller's snapshot (TX-10; TX-05).
     *
     * @return list<Table>
     */
    public function tables(Authority $viewer, Filters $filters): array;
}
