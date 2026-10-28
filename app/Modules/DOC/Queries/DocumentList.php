<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Search\Row;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-035 *Dokumen*'s reads (ROUTE-035; SCR-34; PJ-15; CS-07; PF-13; PF-19; PF-22; QB-12): the generated documents of
 * one company at a time — a branch of the viewer's scope with `projects.view` —, the scope inside the statement before
 * the exact number lookup, the order and the cursor, so a number of another company's document is never shown (PJ-23).
 * Each row is metadata only — type, number, state, date —, its current version the issued one, else the latest;
 * the pre-payment Kuitansi with its own state *Menunggu Pembayaran* until it is linked or voided (UXS-38).
 */
final class DocumentList
{
    public const PAGE = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities, private readonly DocumentSubjects $subjects) {}

    /**
     * The subject a "Terbitkan …" control names by its records' public ids, as its issuing module resolves it for the
     * viewer, or unknown when no module supplies the type or the viewer may not see the record (AZ-11).
     *
     * @param  array<string, string>  $records
     */
    public function subjectOf(int $viewerId, string $typeCode, string $mode, array $records): DocumentSubject
    {
        $subject = $this->subjects->provider($typeCode, $mode)?->subject($this->capabilities->for($viewerId), $typeCode, $mode, $records);

        if ($subject === null || $subject->typeCode !== $typeCode || $subject->mode !== $mode) {
            throw (new ModelNotFoundException)->setModel(Document::class, array_values($records));
        }

        return $subject;
    }

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $number, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if (DocumentAccess::mayViewMetadata($viewer, self::int($company->id ?? null))) {
                $branches[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => self::int($company->id ?? null)];
            }
        }

        $chosen = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $candidate;
            }
        }

        $lookup = $number === null ? null : mb_strtoupper(trim($number));
        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen['internal'], $lookup === '' ? null : $lookup, $after);

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'number' => $lookup === '' ? null : $lookup,
            'rows' => $page['rows'],
            'more' => $page['more'],
        ];
    }

    /**
     * One page of a company's documents after the cursor, newest first, one more read to know whether more follow.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(Authority $viewer, int $companyId, ?string $number, ?string $after): array
    {
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1
            ? DB::table('documents')->where('public_id', strtolower($after))->where('company_id', $companyId)->value('id') : null;
        $query = $viewer->scope->apply(DB::table('documents as d'), 'd.company_id')
            ->where('d.company_id', $companyId)
            ->join('document_types as t', 't.code', '=', 'd.document_type_code')
            ->joinLateral(DB::table('document_versions as v')->whereColumn('v.document_id', 'd.id')
                ->orderByRaw("(v.state = 'ISSUED') desc")->orderByDesc('v.version_no')->limit(1)
                ->select(['v.id', 'v.state', 'v.formatted_number', 'v.business_date', 'v.version_no']), 'c')
            ->when($number !== null, fn (Builder $where) => $where->whereExists(fn (Builder $versions) => $versions->from('document_versions as n')
                ->whereColumn('n.document_id', 'd.id')->where('n.company_id', $companyId)->where('n.formatted_number', $number)))
            ->when(is_numeric($cursor), fn (Builder $where) => $where->where('d.id', '<', self::int($cursor)))
            ->orderByDesc('d.id')
            ->limit(self::PAGE + 1)
            ->select(['d.id', 'd.public_id', 'd.document_type_code', 'd.mode', 't.name as type_name', 'c.id as version_id', 'c.state', 'c.formatted_number', 'c.business_date', 'c.version_no']);
        $found = $query->get()->all();
        $more = count($found) > self::PAGE;
        $found = array_slice($found, 0, self::PAGE);
        $linked = self::linked(array_map(fn (object $row): int => self::int($row->id ?? null), $found));
        $rows = [];

        foreach ($found as $row) {
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'type' => Row::text($row, 'document_type_code'),
                'type_name' => Row::text($row, 'type_name'),
                'state' => Row::text($row, 'state'),
                'prepayment' => self::prepaymentState($row, $linked),
                'number' => is_string($row->formatted_number ?? null) ? $row->formatted_number : null,
                'business_date' => is_string($row->business_date ?? null) ? $row->business_date : null,
                'version_no' => self::int($row->version_no ?? null),
            ];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }

    /** A document a command or the detail page names, by public identifier, or unknown to a viewer without its metadata (AZ-11). */
    public function find(int $viewerId, string $publicId): Document
    {
        $viewer = $this->capabilities->for($viewerId);
        $document = preg_match(self::UUID, $publicId) === 1 ? Document::query()->where('public_id', strtolower($publicId))->first() : null;

        if ($document === null || ! $viewer->scope->includes($document->company_id) || ! DocumentAccess::mayViewMetadata($viewer, $document->company_id)) {
            throw (new ModelNotFoundException)->setModel(Document::class, [$publicId]);
        }

        return $document;
    }

    public function publicIdOf(int $documentId): string
    {
        $id = DB::table('documents')->where('id', $documentId)->value('public_id');

        return is_string($id) ? $id : throw (new ModelNotFoundException)->setModel(Document::class, [$documentId]);
    }

    /**
     * What ADMIN_FLOW §12.3's refusal of an issue in a period whose numbering has not started names: the company, and
     * for the Owner — who alone starts numbering — the address of PG-061 (MSG-17).
     *
     * @return array{company: string, numbering: ?string}
     */
    public function numberingOf(int $viewerId, Document $document): array
    {
        $company = DB::table('companies')->where('id', $document->company_id)->first(['public_id', 'legal_name']);
        $owner = $this->capabilities->for($viewerId)->ownerRole;

        return [
            'company' => is_object($company) ? Row::text($company, 'legal_name') : '',
            'numbering' => $owner && is_object($company) ? '/pengaturan/penomoran?'.http_build_query(['perusahaan' => $company->public_id, 'jenis' => strtolower($document->document_type_code)]) : null,
        ];
    }

    /**
     * The pre-payment Kuitansi chains among the documents with a LINKED link (AX-31).
     *
     * @param  list<int>  $documents
     * @return list<int>
     */
    private static function linked(array $documents): array
    {
        $linked = [];

        foreach ($documents === [] ? [] : DB::table('prepayment_kuitansi_links')->whereIn('document_id', $documents)->where('state', 'LINKED')->distinct()->pluck('document_id') as $id) {
            $linked[] = self::int($id);
        }

        return $linked;
    }

    /** @param  list<int>  $linked */
    private static function prepaymentState(object $row, array $linked): ?string
    {
        if (Row::text($row, 'document_type_code') !== 'DOC-05' || Row::text($row, 'mode') !== 'PRE_PAYMENT' || Row::text($row, 'state') !== 'ISSUED') {
            return null;
        }

        return in_array(self::int($row->id ?? null), $linked, true) ? 'linked' : 'open_not_linked';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
