<?php

namespace App\Modules\ImportOrchestration\Services;

use App\Modules\IAM\Data\Authority;
use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What every batch command shares (NX-13; PJ-20): its lock set — the batch's companies (LK-06) and the batch (LK-09)
 * —, the batch re-read under them, and its scope rule — the Owner, or `import.prepare` in every company of the batch.
 */
final class Batches
{
    public static function locks(ImportBatch $batch): LockSet
    {
        $set = new LockSet;

        foreach ($batch->company_scope as $company) {
            $set->add('companies', ['id' => $company]);
        }

        return $set->add('import_batches', ['id' => $batch->id]);
    }

    /** The batch as its locks hold it. */
    public static function fresh(ImportBatch $batch): ImportBatch
    {
        return ImportBatch::query()->whereKey($batch->id)->firstOrFail();
    }

    /** PJ-20: the Owner, or `import.prepare` with every company of the batch in scope; otherwise denied. */
    public static function authorize(CommandContext $context, ImportBatch $batch): Authority
    {
        $authority = $context->authority() ?? throw new AuthorizationException;

        if (! self::reaches($authority, $batch->company_scope)) {
            throw new AuthorizationException;
        }

        return $authority;
    }

    /** @param  list<int>  $scope */
    public static function reaches(Authority $authority, array $scope): bool
    {
        if ($authority->allows('import.commit')) {
            return true;
        }

        foreach ($scope as $company) {
            if (! $authority->allows('import.prepare', $company)) {
                return false;
            }
        }

        return $scope !== [];
    }

    /** Whether any row of the batch has committed or begun committing — the batch may then never be aborted (IMP-06). */
    public static function started(int $batchId): bool
    {
        return DB::table('import_rows')->where('import_batch_id', $batchId)
            ->where(fn (Builder $rows) => $rows->where('validation_state', 'COMMITTED')->orWhereRaw('errors @> ?::jsonb', [json_encode([['reason' => RowRunner::IN_PROGRESS]])]))
            ->exists();
    }

    /** An active Owner, whose authority the dry run's actions run under on the rolled-back copy (JB-05). */
    public static function owner(): ?int
    {
        $id = DB::table('user_roles as ur')->join('role_capabilities as rc', 'rc.role_id', '=', 'ur.role_id')->join('capabilities as c', 'c.id', '=', 'rc.capability_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')->where('c.code', 'import.commit')->where('u.is_active', true)->orderBy('u.id')->value('u.id');

        return is_numeric($id) ? (int) $id : null;
    }
}
