<?php

namespace App\Modules\FIN\Queries;

use App\Modules\FIN\Models\Invoice;
use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use App\Support\Search\Term;
use Illuminate\Database\Query\JoinClause;

/**
 * The invoices' part of search and lookup (PERFORMANCE PF-19, PF-22; IP-17): an invoice is found by the formatted
 * number of one of its documents — *Nota*, *Invoice* or *Surat Permintaan Pembayaran* (DOC-04, DOC-06, DOC-13),
 * the layouts and request of one record (L-26) —, exactly, unique only within its company and type, so several
 * rows may share it. To a holder of `finance.view` only (RD-04), the company scope inside the same statement
 * (CS-07; DP-06); each row names its company by code (PT-04).
 */
final class InvoiceLookup
{
    public const CAPABILITY = 'finance.view';

    public const DOCUMENT_TYPES = ['DOC-04', 'DOC-06', 'DOC-13'];

    /** @return list<array{type: string, id: string, code: string, label: string, company: string}> */
    public function exact(Authority $viewer, Term $term, int $rows): array
    {
        if (! $viewer->allows(self::CAPABILITY)) {
            return [];
        }

        $query = Invoice::query()
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->join('documents', fn (JoinClause $join) => $join->on('documents.invoice_id', '=', 'invoices.id')->on('documents.company_id', '=', 'invoices.company_id'))
            ->join('document_versions', fn (JoinClause $join) => $join->on('document_versions.document_id', '=', 'documents.id')->on('document_versions.company_id', '=', 'documents.company_id'))
            ->whereIn('document_versions.document_type_code', self::DOCUMENT_TYPES)
            ->where('document_versions.formatted_number', mb_strtoupper($term->compact()))
            ->select(['invoices.public_id', 'document_versions.formatted_number', 'companies.code as company_code'])
            ->distinct()
            ->orderBy('companies.code')
            ->limit($rows + 1);

        $rows = $viewer->scope->apply($query, 'invoices.company_id')->get()->all();

        return array_map(fn (object $invoice): array => [
            'type' => 'invoice',
            'id' => Row::text($invoice, 'public_id'),
            'code' => Row::text($invoice, 'formatted_number'),
            'label' => Row::text($invoice, 'formatted_number'),
            'company' => Row::text($invoice, 'company_code'),
        ], array_values($rows));
    }
}
