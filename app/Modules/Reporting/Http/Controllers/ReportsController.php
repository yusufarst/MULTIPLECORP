<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Queries\AccountAuthority;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\Reports;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `Reporting ShowReports` (ROUTE-051; NAV-051; SCR-47) and `Reporting ShowConsolidatedReport` (ROUTE-052; NAV-052;
 * SCR-48): one report at a time by its address key, among those the viewer's read classes open (RD-02–RD-07), its
 * period and company filter as whitelisted address keys (PF-27; IP-18); the tables a deferred group read in one snapshot,
 * so the first response is the frame and the spinner shows after 300 ms (PT-19). PG-052 is the Owner's alone
 * (`reports.consolidated`; OD-14): an Admin's request answers PG-005. Read-only; no command.
 */
final class ReportsController extends Controller
{
    public function __construct(private readonly Reports $reports, private readonly AccountAuthority $capabilities) {}

    public function show(Request $request): Response
    {
        return $this->page($request, 'Reporting/Laporan', Reports::PAGE);
    }

    public function consolidated(Request $request): Response
    {
        return $this->page($request, 'Reporting/LaporanGabungan', Reports::CONSOLIDATED);
    }

    /**
     * @param  list<class-string<Report>>  $page
     */
    private function page(Request $request, string $component, array $page): Response
    {
        $viewer = $this->capabilities->for($request->user() ?? throw new NotFoundHttpException);
        $visible = $this->reports->visible($viewer, $page);

        if ($visible === []) {
            throw new NotFoundHttpException;
        }

        $asked = $request->query('laporan');
        $report = $visible[0];

        foreach ($visible as $candidate) {
            if ($candidate->code() === $asked) {
                $report = $candidate;
            }
        }

        $filters = Filters::of($viewer, $request->query(), BusinessDate::today());

        return Inertia::render($component, [
            'reports' => array_map(fn (Report $item): string => $item->code(), $visible),
            'report' => $report->code(),
            'filters' => [...$filters->keys(), 'perusahaan' => $filters->company],
            'companies' => $filters->choices,
            'as_of' => now()->toIso8601ZuluString(),
            'tables' => Inertia::defer(fn (): array => Reports::read($report, $viewer, $filters)),
            'can' => self::can($viewer),
        ]);
    }

    /** @return array{export: bool, consolidated: bool} */
    private static function can(Authority $viewer): array
    {
        return ['export' => $viewer->has('reports.export'), 'consolidated' => $viewer->has('reports.consolidated')];
    }
}
