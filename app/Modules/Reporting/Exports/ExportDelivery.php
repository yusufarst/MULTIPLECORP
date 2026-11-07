<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\DOC\Contracts\ExportFiles;
use App\Modules\DOC\Data\Download;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * ROUTE-284's delivery (INT-284; RV-08; DP-05; FL-07; DIR-089 (5)): the requester's own request, READY, unexpired and
 * still covered by the requester's current scope, capabilities and masking context, its file read back through DOC for
 * that requester alone; a READY request no longer covered — one that escaped the access change's mark — is marked
 * WITHHELD now. Anything else answers nothing, a denied and an unknown request alike (AZ-11).
 */
final class ExportDelivery
{
    public function __construct(private readonly OwnExports $exports, private readonly ExportFiles $files) {}

    /** @return ?array{download: Download, report: string, format: string, name: string} */
    public function download(int $userId, string $id): ?array
    {
        $export = $this->exports->find($userId, $id);

        if ($export === null) {
            return null;
        }

        if ($this->exports->shown($userId, $export) !== 'READY') {
            DB::table('export_requests')->where('id', Row::text($export, 'id'))->where('state', 'READY')->whereRaw('expires_at > now()')->update(['state' => 'WITHHELD']);

            return null;
        }

        $download = $this->files->stream((int) Row::text($export, 'file_object_id'), $userId);

        if ($download === null) {
            return null;
        }

        $filters = json_decode(Row::text($export, 'filters'), true);
        $filters = is_array($filters) ? $filters : [];
        $format = ($filters['bentuk'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
        $report = Row::text($export, 'report_code');
        $from = is_string($filters['dari'] ?? null) ? $filters['dari'] : '';
        $to = is_string($filters['sampai'] ?? null) ? $filters['sampai'] : '';

        return ['download' => $download, 'report' => $report, 'format' => $format, 'name' => "laporan-{$report}-{$from}-{$to}.{$format}"];
    }
}
