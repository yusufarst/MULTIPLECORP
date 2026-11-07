<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Reports;
use App\Modules\Reporting\Reports\Snapshot;
use App\Support\BusinessDate;
use App\Support\Jobs\PlatformJob;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * JB-03 `GenerateExport` (CONCURRENCY_IDEMPOTENCY §15; ARCHITECTURE §11; RV-07; PF-28; FL-09): one export at a time
 * across accounts under one shared overlap lock — a job that finds it taken released back with a delay, the lock
 * expiring after 12 minutes, above the 10-minute timeout and below JB-09's 15 —; under it the request claimed by a
 * conditional update to RUNNING, from PENDING or from a dead earlier run's RUNNING, the claims and not the job's attempts
 * limiting the tries — a request claimed three times is FAILED —; the requester re-authorized before the snapshot opens
 * (RV-07), a request no longer covered WITHHELD; the report read under the recorded context alone in one TX-05 snapshot,
 * counted again against the row bound, and written by the streaming writer; then the private EXPORT file registered and
 * the request made READY by one conditional update that a WITHHELD or FAILED mark defeats, the bytes removed then.
 */
final class GenerateExport extends PlatformJob
{
    public const LOCK = 'exports';

    public const LOCK_SECONDS = 720;

    public const MAXIMUM_CLAIMS = 3;

    /** Hours a READY export stays downloadable; P9's cleanup removes it after (FL-09; HO-29). */
    public const KEPT_HOURS = 24;

    /** The claims, not the attempts, bound the tries (JB-03): a release while the lock is taken is no failure. */
    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $requestId)
    {
        parent::__construct();
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [...parent::middleware(), (new WithoutOverlapping(self::LOCK))->shared()->releaseAfter(30)->expireAfter(self::LOCK_SECONDS)];
    }

    public function handle(EffectiveCapabilities $capabilities, Reports $reports, ExportFile $files, FileStore $store): void
    {
        $request = $this->claim();

        if ($request === null) {
            return;
        }

        $requester = (int) Row::text($request, 'requested_by');
        $context = ExportContext::stored($request->company_ids ?? null, $request->capability_codes ?? null, $request->masking_context ?? null);
        $current = $capabilities->fresh($requester);

        if (! $context->coveredBy($current)) {
            self::mark($this->requestId, 'WITHHELD');

            return;
        }

        $report = $reports->find(Row::text($request, 'report_code')) ?? throw new LogicException('An export names a report that does not exist.');
        $keys = json_decode(Row::text($request, 'filters'), true);
        $keys = is_array($keys) ? $keys : [];
        // The period as the request resolved it: a preset is read again as its own dates, never as the job's day's month.
        $keys = isset($keys['dari'], $keys['sampai']) ? [...$keys, 'periode' => 'rentang'] : $keys;
        $format = in_array($keys['bentuk'] ?? null, RequestExport::FORMATS, true) ? (string) $keys['bentuk'] : 'xlsx';
        $viewer = $context->narrow($current);
        $at = CarbonImmutable::now();
        $directory = sys_get_temp_dir().'/export-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($directory, 0700);

        try {
            $path = Snapshot::read(function () use ($report, $viewer, $keys, $format, $directory, $at, $files): ?string {
                $filters = Filters::of($viewer, $keys, BusinessDate::today());

                return Reports::count($report, $viewer, $filters) > RequestExport::bound() ? null : $files->write($report, $viewer, $filters, $format, $directory, $at);
            });

            if ($path === null) {
                self::mark($this->requestId, 'FAILED');

                return;
            }

            $this->finish($store, $path, $format, $requester, $at);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /** The claim (JB-03): a conditional update, from PENDING or a dead run's RUNNING; a request claimed three times FAILED. */
    private function claim(): ?object
    {
        $now = CarbonImmutable::now();
        DB::table('export_requests')->where('id', $this->requestId)->whereIn('state', ['PENDING', 'RUNNING'])->where('claim_count', '>=', self::MAXIMUM_CLAIMS)->update(['state' => 'FAILED']);
        $claimed = DB::table('export_requests')->where('id', $this->requestId)->whereIn('state', ['PENDING', 'RUNNING'])->where('claim_count', '<', self::MAXIMUM_CLAIMS)
            ->update(['state' => 'RUNNING', 'claimed_at' => $now, 'claim_count' => DB::raw('claim_count + 1')]);

        return $claimed === 1 ? DB::table('export_requests')->where('id', $this->requestId)->first() : null;
    }

    private function finish(FileStore $store, string $path, string $format, int $requester, CarbonImmutable $at): void
    {
        $key = $store->newKey();
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new LogicException('An export file could not be read back.');
        }

        try {
            $store->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            $ready = DB::transaction(function () use ($store, $key, $path, $format, $requester, $at): bool {
                $file = $store->register($key, [
                    'purpose' => FilePurpose::Export->value,
                    'mime_type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'sha256' => (string) hash_file('sha256', $path),
                    'size_bytes' => (int) filesize($path),
                    'company_id' => null,
                    'uploaded_by' => $requester,
                    'original_filename' => null,
                    'sensitivity' => FilePurpose::Export->sensitivity(),
                ]);

                $ready = DB::table('export_requests')->where('id', $this->requestId)->where('state', 'RUNNING')
                    ->update(['state' => 'READY', 'file_object_id' => $file->id, 'expires_at' => $at->addHours(self::KEPT_HOURS)]);

                if ($ready !== 1) {
                    // Withheld or failed meanwhile: the file's row rolls back, and its bytes are removed below.
                    throw new ExportDefeated;
                }

                return true;
            });
        } catch (ExportDefeated) {
            $ready = false;
        } catch (Throwable $failure) {
            $store->disk()->delete($key);

            throw $failure;
        }

        if (! $ready) {
            $store->disk()->delete($key);
            Log::info('An export finished after it was withheld or failed; its file was removed (JB-03).', ['export' => $this->requestId]);
        }
    }

    /** A request's end other than READY, by a conditional update from what it can still be. */
    public static function mark(string $id, string $state): void
    {
        DB::table('export_requests')->where('id', $id)->whereIn('state', ['PENDING', 'RUNNING'])->update(['state' => $state]);
    }

    public function failed(?Throwable $exception): void
    {
        self::mark($this->requestId, 'FAILED');
    }
}
