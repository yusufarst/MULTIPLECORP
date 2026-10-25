<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Models\Document;
use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use App\Support\Search\Term;
use Illuminate\Database\Query\JoinClause;

/**
 * The documents' part of search and lookup (PERFORMANCE PF-19, PF-22; IP-17; INFORMATION_ARCHITECTURE §10;
 * PERMISSIONS_MATRIX DP-06): a document of any type is found by the formatted number of one of its versions,
 * exactly, unique only within its company and type, so several rows may share it. A document's metadata — its
 * type and number — is `projects.view`'s (PJ-15), always within the document's company: the scope inside the same
 * statement (CS-07). Each row names its type and its company by code (PT-04) and opens the document (PG-036).
 */
final class DocumentLookup
{
    public const CAPABILITY = 'projects.view';

    /**
     * @param  int  $rows  the rows a type shows; one more is read, so the caller can say the list is cut (MSG-23)
     * @param  list<string>  $except  the types another result type already shows, e.g. an invoice's documents
     * @return list<array{type: string, id: string, code: string, label: string, company: string}>
     */
    public function exact(Authority $viewer, Term $term, int $rows, array $except = []): array
    {
        if (! $viewer->allows(self::CAPABILITY)) {
            return [];
        }

        $query = Document::query()
            ->join('companies', 'companies.id', '=', 'documents.company_id')
            ->join('document_versions', fn (JoinClause $join) => $join->on('document_versions.document_id', '=', 'documents.id')->on('document_versions.company_id', '=', 'documents.company_id'))
            ->join('document_types', 'document_types.code', '=', 'document_versions.document_type_code')
            ->where('document_versions.formatted_number', mb_strtoupper($term->compact()))
            ->when($except !== [], fn ($where) => $where->whereNotIn('document_versions.document_type_code', $except))
            ->select(['documents.public_id', 'document_versions.formatted_number', 'document_types.name as type_name', 'document_types.code as type_code', 'companies.code as company_code'])
            ->distinct()
            ->orderBy('companies.code')
            ->orderBy('document_types.code')
            ->limit($rows + 1);

        $found = $viewer->scope->apply($query, 'documents.company_id')->get()->all();

        return array_map(fn (object $document): array => [
            'type' => 'document',
            'id' => Row::text($document, 'public_id'),
            'code' => Row::text($document, 'formatted_number'),
            'label' => Row::text($document, 'type_name'),
            'company' => Row::text($document, 'company_code'),
        ], array_values($found));
    }
}
