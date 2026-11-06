<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-08 (WORKFLOWS §10; IA §9.1): rendition pending or failed — the document versions whose latest attempt is PENDING
 * or FAILED, read from the workspace's rows of IX-07's partial index before the company filter of the same statement,
 * so it returns nothing from another company (PF-24; CS-07) — RD-03; newest first; the document on PG-036.
 */
final class QS08RenditionsOwed extends Signal
{
    public function code(): string
    {
        return 'QS-08';
    }

    public function companies(Context $context): array
    {
        return $context->holding('projects.view');
    }

    protected function newestFirst(): bool
    {
        return true;
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('document_renditions as r')->join('document_versions as v', 'v.id', '=', 'r.document_version_id')->join('documents as d', 'd.id', '=', 'v.document_id')
            ->whereIn('r.state', ['PENDING', 'FAILED'])->where('v.company_id', $company)
            ->whereNotExists(fn (Builder $later) => $later->selectRaw('1')->from('document_renditions as n')->whereColumn('n.document_version_id', 'r.document_version_id')->whereColumn('n.attempt_no', '>', 'r.attempt_no'))
            ->orderByDesc('r.recorded_at')
            ->selectRaw("'r' || r.id as key, v.company_id, coalesce(v.formatted_number, v.document_type_code) as subject, v.document_type_code as ref, r.state as value, null::text as unit, r.recorded_at::text as at, d.public_id::text as target");
    }

    protected function href(Context $context, object $row): string
    {
        return '/dokumen/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/dokumen';
    }
}
