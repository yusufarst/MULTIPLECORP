<?php

namespace App\Modules\IAM\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CompanyScope;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function Illuminate\Support\defer;

/**
 * The resolution of a public identifier through the company-scope resolver (PERMISSIONS_MATRIX CS-04,
 * AZ-11; DP-01, DP-03): a record outside the actor's scope fails exactly as a missing one — the same
 * exception, after the same two queries —, never by a global existence rule. An out-of-scope record of
 * a real public identifier is an ACCESS_DENIED security event, written after the response so the
 * answer's timing is unchanged (LG-02, LG-07's IDOR signal).
 */
final class ScopedRecords
{
    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  the company-owned records, the model's own query
     * @return TModel
     */
    public function find(Builder $query, string $publicId, Authority $authority, string $companyColumn = 'company_id'): Model
    {
        $table = $query->getModel()->getTable();
        $valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1;
        $publicId = strtolower($publicId);

        // The caller's conditions grouped before the identifier, so an `orWhere` among them can never return a
        // record other than the one the identifier names.
        $record = $valid
            ? $authority->scope->apply(CompanyScope::grouped(clone $query)->where("{$table}.public_id", $publicId), "{$table}.{$companyColumn}")->first()
            : null;

        if ($record !== null) {
            return $record;
        }

        // Both failures run this query — the caller's own conditions without the scope —, so a probe cannot
        // tell them apart by time, and a record the caller's conditions exclude is not taken for a probe.
        $exists = $valid && CompanyScope::grouped(clone $query)->where("{$table}.public_id", $publicId)->exists();

        if ($exists) {
            defer(fn () => $this->events->record(SecurityEventKind::AccessDenied, userId: $authority->userId, reasonCode: 'OUT_OF_SCOPE', targetPublicId: $publicId))->always();
        }

        throw (new ModelNotFoundException)->setModel($query->getModel()::class, [$publicId]);
    }
}
