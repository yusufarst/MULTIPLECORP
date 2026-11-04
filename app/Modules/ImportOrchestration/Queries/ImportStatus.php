<?php

namespace App\Modules\ImportOrchestration\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Jobs\CommitImportRows;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\Status\StatusRead;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * The batch state PG-058 polls through ROUTE-285 while a job runs (IP-12; UXI-32): *Uji Coba Berjalan*, *Sedang
 * Diimpor* with the server's count of committed rows, or the state reached; for the Owner and the preparers with every
 * company of the batch in scope (PJ-20), an unknown and a denied batch answering alike.
 */
final class ImportStatus implements StatusRead
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    public function kind(): string
    {
        return 'impor';
    }

    public function read(Authenticatable $viewer, string $id): array
    {
        $batch = preg_match('/^[0-9a-f-]{36}$/i', $id) === 1 ? ImportBatch::query()->where('public_id', strtolower($id))->first() : null;

        if ($batch === null || ! Batches::reaches($this->capabilities->for($viewer), $batch->company_scope)) {
            throw (new ModelNotFoundException)->setModel(ImportBatch::class, [$id]);
        }

        $counts = DB::table('import_rows')->where('import_batch_id', $batch->id)->groupBy('validation_state')->selectRaw('validation_state, count(*) as total')->pluck('total', 'validation_state')->all();

        return [
            'state' => self::display($batch),
            'committed' => is_numeric($counts['COMMITTED'] ?? null) ? (int) $counts['COMMITTED'] : 0,
            'remaining' => is_numeric($counts['VALID'] ?? null) ? (int) $counts['VALID'] : 0,
        ];
    }

    /**
     * The batch's state as PG-058 shows it (ADMIN_FLOW §13): the stored state, a dry run running or failed, a commit
     * running or stopped by a refused row.
     */
    public static function display(ImportBatch $batch): string
    {
        $report = is_array($batch->dry_run_report) ? $batch->dry_run_report : [];

        return match (true) {
            $batch->state === 'PREPARED' && ($report['state'] ?? null) === DryRunImport::PENDING => 'DRY_RUN_RUNNING',
            $batch->state === 'PREPARED' && ($report['state'] ?? null) === 'FAILED' => 'DRY_RUN_FAILED',
            $batch->state === 'SIGNED_OFF' && $batch->committed_at !== null => CommitImportRows::stopped($batch->id) ? 'STOPPED' : 'COMMITTING',
            default => $batch->state,
        };
    }
}
