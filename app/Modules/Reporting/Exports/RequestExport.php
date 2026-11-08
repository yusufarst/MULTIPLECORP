<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\IAM\Data\Authority;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\Reports;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * `Reporting RequestExport` (ROUTE-283; INT-283; H6-04; WS-12; PF-28; UXH-11): an export of the page's own report and
 * filters, admitted in one TX-04 step under the requester's row lock (LK-03) — a third request while two are PENDING
 * or RUNNING and not stale refused with MSG-22 — and recorded with its company set, view capabilities and masking
 * context (DP-07); the job dispatched after the commit (JB-03). No `command_id` (RB-03). The row bound is checked here,
 * before any generation, by the report's own bounded counts in one snapshot (PF-28: "refused with a message asking for
 * a narrower period instead of failing midway"): above it MSG-21 in place; the job counts again before it writes, so a
 * request that grew past it meanwhile ends *Gagal* rather than half written. EXPORT_REQUESTED is written outside the
 * transaction (LG-02).
 */
final class RequestExport
{
    public const IN_PROGRESS = 2;

    public const ROW_BOUND = 200_000;

    public const FORMATS = ['xlsx', 'pdf'];

    /** A PDF's bound: one document rendered whole, so it stays far below the workbook's (an executor's choice under PF-28). */
    public const PDF_ROW_BOUND = 5_000;

    /** PF-28's row bound: 200,000 as its starting value for a workbook, P9's disk guard able to lower it (`exports.row_bound`); a PDF's own. */
    public static function bound(string $format = 'xlsx'): int
    {
        return $format === 'pdf' ? config()->integer('exports.pdf_row_bound', self::PDF_ROW_BOUND) : config()->integer('exports.row_bound', self::ROW_BOUND);
    }

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @return array{admitted: bool, id: ?string, message: ?string}
     */
    public function __invoke(Authority $viewer, Report $report, Filters $filters, string $format, ?string $ip, ?string $userAgent): array
    {
        if (Reports::count($report, $viewer, $filters) > self::bound($format)) {
            return ['admitted' => false, 'id' => null, 'message' => 'messages.msg_21'];
        }

        $context = ExportContext::of($report, $viewer, $filters);
        $now = CarbonImmutable::now();
        $id = DB::transaction(function () use ($viewer, $report, $filters, $format, $context, $now): ?string {
            DB::table('users')->where('id', $viewer->userId)->lockForUpdate()->value('id');
            $running = DB::table('export_requests')->where('requested_by', $viewer->userId)
                ->where(fn (Builder $open) => $open->where('state', 'PENDING')->orWhere(fn (Builder $live) => $live->where('state', 'RUNNING')->where('claimed_at', '>', $now->subMinutes(ExportSweep::STALE_MINUTES))))
                ->count();

            if ($running >= self::IN_PROGRESS) {
                return null;
            }

            $id = DB::table('export_requests')->insertGetId([
                'requested_by' => $viewer->userId,
                'report_code' => $report->code(),
                'filters' => json_encode([...$filters->keys(), 'bentuk' => $format, 'hari' => $filters->today], JSON_THROW_ON_ERROR),
                ...$context->columns(),
                'state' => 'PENDING',
                'recorded_at' => $now,
            ]);

            GenerateExport::dispatch((string) $id);

            return (string) $id;
        });

        if ($id === null) {
            return ['admitted' => false, 'id' => null, 'message' => 'messages.msg_22'];
        }

        $this->events->record(SecurityEventKind::ExportRequested, userId: $viewer->userId, routeName: 'reporting.exports.request', targetPublicId: $id, ipAddress: $ip, userAgent: $userAgent,
            details: ['report' => $report->code(), 'format' => $format, 'period' => $filters->period->from.'/'.$filters->period->to, 'companies' => array_values(array_filter(array_map($filters->code(...), $filters->companyIds())))]);

        return ['admitted' => true, 'id' => $id, 'message' => null];
    }
}
