<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Http\FileResponses;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\Reporting\Exports\GenerateExport;
use App\Modules\Reporting\Exports\OwnExports;
use App\Modules\Reporting\Exports\RequestExport;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Reports;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `Reporting ShowOwnExports` (ROUTE-053; NAV-053; SCR-49), `Reporting RequestExport` (ROUTE-283; INT-283) and
 * `Reporting DownloadExport` (ROUTE-284; INT-284): the requester's own requests only (H6-04); a request of the report
 * and filters of the page it came from, its answer PG-053 with the request (NAV-051) or a limit in place (WS-12); a
 * download an ordinary browser request authorized at request time — READY, unexpired, and the requester's current
 * scope, capabilities and masking context still covering the recorded ones —, FL-07's attachment headers, never a
 * signed link alone (PF-29; RV-08; DP-05). A denied and an unknown request answer alike (AZ-11).
 */
final class ExportsController extends Controller
{
    public function __construct(
        private readonly OwnExports $exports,
        private readonly Reports $reports,
        private readonly EffectiveCapabilities $capabilities,
        private readonly SecurityEventRecorder $events,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->userId($request);
        $page = $this->exports->page($user, self::text($request->query('tab')), self::text($request->query('setelah')));

        return Inertia::render('Reporting/EksporSaya', [...$page, 'kept_hours' => GenerateExport::KEPT_HOURS]);
    }

    public function store(Request $request, RequestExport $admit): RedirectResponse
    {
        $viewer = $this->capabilities->for($request->user() ?? throw new NotFoundHttpException);
        $report = $this->reports->find((string) self::text($request->input('laporan')));

        // ROUTE-283: the report's own view capabilities, the consolidated ones `reports.consolidated` (PERMISSIONS_MATRIX §4).
        if ($report === null || $report->opens($viewer) === [] || (Reports::consolidated($report) && ! $viewer->has('reports.consolidated'))) {
            throw new NotFoundHttpException;
        }

        $format = in_array($request->input('bentuk'), RequestExport::FORMATS, true) ? (string) $request->input('bentuk') : 'xlsx';
        $keys = array_filter(['periode' => self::text($request->input('periode')), 'dari' => self::text($request->input('dari')), 'sampai' => self::text($request->input('sampai')), 'perusahaan' => self::text($request->input('perusahaan'))], is_string(...));
        $answer = $admit($viewer, $report, Filters::of($viewer, $keys, BusinessDate::today()), $format, $request->ip(), $request->userAgent());

        if (! $answer['admitted']) {
            Inertia::flash('answer', ['kind' => 'REJECTED', 'code' => $answer['message'] === 'messages.msg_21' ? 'MSG-21' : 'MSG-22', 'cause' => null, 'text' => __((string) $answer['message'])]);

            return back();
        }

        return redirect('/laporan/ekspor')->with('export', $answer['id']);
    }

    public function download(Request $request, FileStore $store, string $id): StreamedResponse
    {
        $user = $this->userId($request);
        $export = $this->exports->find($user, $id);

        if ($export === null || $this->exports->shown($user, $export) !== 'READY') {
            // RV-08: a READY request the requester's grants no longer cover, escaped its mark, is withheld now.
            if ($export !== null && ($export->state ?? null) === 'READY') {
                DB::table('export_requests')->where('id', Row::text($export, 'id'))->where('state', 'READY')->whereRaw('expires_at > now()')->update(['state' => 'WITHHELD']);
            }

            throw new NotFoundHttpException;
        }

        $file = FileObject::query()->whereKey($export->file_object_id ?? 0)->where('purpose', 'EXPORT')->where('uploaded_by', $user)->first() ?? throw new NotFoundHttpException;
        $filters = json_decode(is_string($export->filters ?? null) ? $export->filters : '{}', true);
        $filters = is_array($filters) ? $filters : [];
        $format = ($filters['bentuk'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
        $name = 'laporan-'.(is_string($export->report_code ?? null) ? $export->report_code : 'ekspor').'-'.(is_string($filters['dari'] ?? null) ? $filters['dari'] : '').'-'.(is_string($filters['sampai'] ?? null) ? $filters['sampai'] : '').'.'.$format;
        $this->events->record(SecurityEventKind::ExportDownloaded, userId: $user, routeName: 'reporting.exports.download', targetPublicId: $id, ipAddress: $request->ip(), userAgent: $request->userAgent(),
            details: ['report' => is_string($export->report_code ?? null) ? $export->report_code : null, 'format' => $format]);

        return FileResponses::attachment($store->stream($file->storage_key, $file->storage_disk), $file->mime_type, $file->size_bytes, $name);
    }

    private function userId(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 60 ? $value : null;
    }
}
