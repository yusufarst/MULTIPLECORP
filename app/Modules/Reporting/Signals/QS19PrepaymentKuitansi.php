<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * QS-19 (WORKFLOWS §10; IA §9.1): pre-payment Kuitansi not yet linked or voided — the company's DOC-05 chains of mode
 * PRE_PAYMENT on IX-14's partial index `documents_ix14_prepayment_kuitansi (company_id)`, whose issued version is
 * neither LINKED to an invoice nor voided — RD-04; oldest first; the Kuitansi on PG-036.
 */
final class QS19PrepaymentKuitansi extends Signal
{
    public function code(): string
    {
        return 'QS-19';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('documents as d')->join('document_versions as v', fn (JoinClause $join) => $join->on('v.document_id', '=', 'd.id')->where('v.state', 'ISSUED'))
            ->where('d.company_id', $company)->where('d.document_type_code', 'DOC-05')->where('d.mode', 'PRE_PAYMENT')
            ->whereNotExists(fn (Builder $linked) => $linked->selectRaw('1')->from('prepayment_kuitansi_links as k')->whereColumn('k.document_id', 'd.id')->where('k.state', 'LINKED'))
            ->orderBy('d.id')
            ->selectRaw("'d' || d.id as key, d.company_id, coalesce(v.formatted_number, '') as subject, null::text as ref, v.kuitansi_amount::text as value, null::text as unit, v.business_date::text as at, d.public_id::text as target");
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
