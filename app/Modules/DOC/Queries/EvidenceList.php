<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Data\FileFamilies;
use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\DOC\Services\EvidenceUse;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-038 *Bukti*'s reads (ROUTE-038; SCR-36; PJ-08; PF-13; CS-07; QB-12): one branch at a time — a company in the
 * viewer's scope, or the shared warehouse's pool evidence —, the scope and the visible families inside the query, a
 * page of 25 by keyset in a number of statements that never depends on its rows; one evidence's versions and links.
 * The reproduction flag of FL-05 is shown only to a viewer who may see the matched rendition (IP-16 step 5; GAP-034).
 */
final class EvidenceList
{
    public const PAGE = 25;

    /** The address key's value of the shared warehouse's branch (NV-05). */
    public const POOL = 'bersama';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * The page's props: the branches the viewer may read — each company in scope with a family it sees, then the pool
     * —, the chosen one, its page after the cursor, the selected evidence's sheet and the upload's choices.
     *
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, ?string $branch, ?string $after, ?string $selected): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            $id = self::int($company->id);

            if (array_filter(EvidenceTypes::TYPES, fn (string $type): bool => EvidenceTypes::mayView($viewer, $type, $id)) !== []) {
                $branches[] = ['id' => self::text($company->public_id), 'code' => self::text($company->code), 'name' => self::text($company->legal_name), 'internal' => $id, 'types' => EvidenceTypes::choosable($viewer, $id)];
            }
        }

        if (EvidenceTypes::mayView($viewer, EvidenceTypes::POOL_TYPES[0], null)) {
            $branches[] = ['id' => self::POOL, 'code' => null, 'name' => null, 'internal' => null, 'types' => EvidenceTypes::choosable($viewer, null)];
        }

        $chosen = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $candidate;
            }
        }

        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($after))->value('id') : null;
        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen['internal'], is_numeric($cursor) ? (int) $cursor : null);
        $evidence = is_string($selected) ? $this->find($viewer, $selected) : null;

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'rows' => $page['rows'],
            'more' => $page['more'],
            'evidence' => $evidence === null ? null : $this->detail($viewer, $evidence),
            'override' => $viewer->has('warnings.override'),
            'amountTypes' => EvidenceTypes::AMOUNT_BEARING,
            'poolTypes' => EvidenceTypes::POOL_TYPES,
        ];
    }

    /** The evidence for a command, by public identifier, or unknown to a viewer who may not see it (AZ-11). */
    public function forCommand(int $viewerId, string $publicId): EvidenceDocument
    {
        return $this->find($this->capabilities->for($viewerId), $publicId) ?? throw new ModelNotFoundException;
    }

    /** A company in the actor's scope by public identifier, or unknown (CS-03; AZ-11). */
    public function companyOf(int $viewerId, string $publicId): int
    {
        $id = preg_match(self::UUID, $publicId) === 1
            ? $this->capabilities->for($viewerId)->scope->apply(DB::table('companies'), 'id')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : throw new ModelNotFoundException;
    }

    /** A committed registration's public identifier, for the page it opens (NAV-038). */
    public function publicIdOf(int $evidenceId): ?string
    {
        $id = DB::table('evidence_documents')->where('id', $evidenceId)->value('public_id');

        return is_string($id) ? $id : null;
    }

    /** A company's public identifier, for the address of its branch. */
    public function companyPublicIdOf(?int $companyId): ?string
    {
        $id = $companyId === null ? null : DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : null;
    }

    /** A project the actor may see in the evidence's company, by public identifier, for a link (C-32; PJ-08 row 95). */
    public function projectOf(int $viewerId, string $publicId, ?int $companyId): int
    {
        $project = preg_match(self::UUID, $publicId) === 1 && $companyId !== null && $this->capabilities->for($viewerId)->allows('projects.view', $companyId)
            ? DB::table('projects')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->value('id') : null;

        return is_numeric($project) ? (int) $project : throw new ModelNotFoundException;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, more: string|null}
     */
    public function page(Authority $viewer, ?int $companyId, ?int $after): array
    {
        $types = array_values(array_filter(EvidenceTypes::TYPES, fn (string $type): bool => EvidenceTypes::mayView($viewer, $type, $companyId)));

        if ($types === []) {
            return ['rows' => [], 'more' => null];
        }

        $query = DB::table('evidence_documents as e')
            ->leftJoin('evidence_document_versions as cv', 'cv.id', '=', 'e.current_version_id')
            ->leftJoin('file_objects as f', 'f.id', '=', 'cv.file_object_id')
            ->whereIn('e.evidence_type', $types);
        ($companyId === null ? $query->whereNull('e.company_id') : $query->where('e.company_id', $companyId));

        if ($after !== null) {
            $query->where('e.id', '<', $after);
        }

        // PJ-08 row 95: only the links whose record the viewer may see are counted.
        $targets = EvidenceUse::visibleTargets($viewer, $companyId);
        $links = DB::table('evidence_links as l')->selectRaw('count(*)')->whereColumn('l.evidence_document_id', 'e.id')->whereNull('l.unlinked_at');

        if ($targets === []) {
            $links->whereRaw('false');
        } else {
            $links->where(function (Builder $visible) use ($targets): void {
                foreach ($targets as $column) {
                    $visible->orWhereNotNull("l.{$column}");
                }
            });
        }

        $found = $query->select([
            'e.id', 'e.public_id', 'e.evidence_type', 'e.declared_issuer', 'e.issuer_reference', 'e.document_date', 'e.proven_amount', 'e.recorded_at',
            'cv.version_no', 'f.sha256', 'f.mime_type',
            DB::raw('(select count(*) from evidence_document_versions v where v.evidence_document_id = e.id) as versions'),
        ])->selectSub($links, 'links')->orderByDesc('e.id')->limit(self::PAGE + 1)->get()->all();
        $more = count($found) > self::PAGE ? self::text($found[self::PAGE - 1]->public_id) : null;
        $found = array_slice($found, 0, self::PAGE);
        $reproductions = $this->reproductions($viewer, array_map(fn (object $row): string => self::text($row->sha256), $found));
        $rows = [];

        foreach ($found as $row) {
            $type = self::text($row->evidence_type);
            $rows[] = [
                'id' => self::text($row->public_id),
                'type' => $type,
                'issuer' => self::text($row->declared_issuer),
                'reference' => self::nullable($row->issuer_reference),
                'date' => substr(self::text($row->document_date), 0, 10),
                'amount' => EvidenceTypes::isAmountBearing($type) ? self::nullable($row->proven_amount) : null,
                'version' => is_numeric($row->version_no) ? (int) $row->version_no : null,
                'versions' => self::int($row->versions),
                'links' => self::int($row->links),
                'image' => str_starts_with(self::text($row->mime_type), 'image/'),
                'reproduction' => isset($reproductions[self::text($row->sha256)]),
                'recorded_at' => self::text($row->recorded_at),
            ];
        }

        return ['rows' => $rows, 'more' => $more];
    }

    /**
     * A company evidence of one of the types a command takes, by public identifier, for a viewer who may see it — the
     * client's order evidence a no-quotation confirmation links (ROUTE-158; PJ-08) —; null when unknown, of another
     * company or type, or hidden from the viewer alike (AZ-11).
     *
     * @param  list<string>  $types
     */
    public function idOf(int $viewerId, string $publicId, int $companyId, array $types): ?int
    {
        $evidence = $this->find($this->capabilities->for($viewerId), $publicId);

        return $evidence !== null && $evidence->company_id === $companyId && in_array($evidence->evidence_type, $types, true) ? $evidence->id : null;
    }

    /**
     * The payment a proof may be linked to, of the evidence's company, the viewer holding `finance.view` of it: never a type that proves no receipt, nor a document the company issued (L-29); else unknown (AZ-11).
     */
    public function paymentOf(int $viewerId, string $publicId, EvidenceDocument $evidence): int
    {
        $viewer = $this->capabilities->for($viewerId);
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('payments')->where('public_id', strtolower($publicId))->where('company_id', $evidence->company_id)->value('id') : null;

        if (! is_numeric($id) || $evidence->company_id === null || ! $viewer->allows('finance.view', $evidence->company_id) || ! in_array($evidence->evidence_type, ['PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'], true) || EvidenceGuard::companyIssued([$evidence->id])) {
            throw new ModelNotFoundException;
        }

        return (int) $id;
    }

    /** The evidence by public identifier, when the viewer may see it (PJ-08; AZ-11). */
    public function find(Authority $viewer, string $publicId): ?EvidenceDocument
    {
        if (preg_match(self::UUID, $publicId) !== 1) {
            return null;
        }

        $evidence = EvidenceDocument::query()->where('public_id', strtolower($publicId))->first();

        return $evidence !== null && EvidenceTypes::mayView($viewer, $evidence->evidence_type, $evidence->company_id) ? $evidence : null;
    }

    /**
     * One evidence's facts, versions and links, for its sheet.
     *
     * @return array<string, mixed>
     */
    public function detail(Authority $viewer, EvidenceDocument $evidence): array
    {
        $versions = DB::table('evidence_document_versions as v')
            ->join('file_objects as f', 'f.id', '=', 'v.file_object_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.uploaded_by')
            ->where('v.evidence_document_id', $evidence->id)
            ->orderByDesc('v.version_no')
            ->get(['v.id', 'v.version_no', 'v.reason', 'v.recorded_at', 'f.public_id as file', 'f.original_filename', 'f.mime_type', 'f.sha256', 'u.name as uploader'])
            ->all();
        $reproductions = $this->reproductions($viewer, array_map(fn (object $row): string => self::text($row->sha256), $versions));
        $links = DB::table('evidence_links as l')
            ->leftJoin('projects as p', 'p.id', '=', 'l.project_id')
            ->leftJoin('purchases as pu', 'pu.id', '=', 'l.purchase_id')
            ->where('l.evidence_document_id', $evidence->id)
            ->whereNull('l.unlinked_at')
            ->orderBy('l.id')
            ->get(['l.*', 'p.public_id as project_public_id', 'p.project_number', 'p.title as project_title', 'pu.public_id as purchase_public_id', 'pu.purchase_number'])
            ->all();

        return [
            'id' => $evidence->public_id,
            'type' => $evidence->evidence_type,
            'pool' => $evidence->company_id === null,
            'issuer' => $evidence->declared_issuer,
            'reference' => $evidence->issuer_reference,
            'date' => $evidence->document_date->format('Y-m-d'),
            'amount' => EvidenceTypes::isAmountBearing($evidence->evidence_type) ? $evidence->proven_amount : null,
            'deliverer' => $evidence->deliverer,
            'received' => $evidence->received_date?->format('Y-m-d'),
            'versions' => array_map(fn (object $version): array => [
                'number' => self::int($version->version_no),
                'current' => self::int($version->id) === $evidence->current_version_id,
                'file' => self::text($version->file),
                'name' => self::nullable($version->original_filename),
                'image' => str_starts_with(self::text($version->mime_type), 'image/'),
                'reason' => self::nullable($version->reason),
                'uploader' => self::nullable($version->uploader),
                'recorded_at' => self::text($version->recorded_at),
                'reproduction' => isset($reproductions[self::text($version->sha256)]),
            ], $versions),
            'links' => array_values(array_filter(array_map(fn (object $link): ?array => self::link(get_object_vars($link)), $links),
                fn (?array $link): bool => $link !== null && EvidenceUse::targetVisible($viewer, $link['target'], $evidence->company_id))),
        ];
    }

    /**
     * The hashes among these that equal a rendition the viewer may see (PJ-15) — one statement for any number.
     *
     * @param  array<int, string>  $hashes
     * @return array<string, true>
     */
    private function reproductions(Authority $viewer, array $hashes): array
    {
        $hashes = array_values(array_unique(array_filter($hashes, fn (string $hash): bool => $hash !== '')));

        if ($hashes === []) {
            return [];
        }

        $seen = [];

        foreach (DB::table('file_objects as f')->join('document_renditions as r', 'r.file_object_id', '=', 'f.id')
            ->join('document_versions as v', 'v.id', '=', 'r.document_version_id')
            ->where('f.purpose', 'RENDITION')->whereIn('f.sha256', $hashes)
            ->get(['f.sha256', 'v.company_id', 'v.document_type_code']) as $row) {
            $capability = FileFamilies::document(self::text($row->document_type_code));

            if ($capability !== null && is_numeric($row->company_id) && $viewer->allows($capability, (int) $row->company_id)) {
                $seen[self::text($row->sha256)] = true;
            }
        }

        return $seen;
    }

    /**
     * A link as the sheet shows it: its handle, its record's kind and, for a project or a purchase, its number.
     *
     * @param  array<mixed>  $link
     * @return array{handle: string, target: string, record: string|null, label: string|null, role: string, linked_at: string}|null
     */
    private static function link(array $link): ?array
    {
        foreach (array_keys(EvidenceUse::TARGETS) as $column) {
            if (! is_numeric($link[$column] ?? null)) {
                continue;
            }

            [$record, $label] = match ($column) {
                'project_id' => [self::nullable($link['project_public_id'] ?? null), trim(self::text($link['project_number'] ?? null).' · '.self::text($link['project_title'] ?? null), ' ·')],
                'purchase_id' => [self::nullable($link['purchase_public_id'] ?? null), self::nullable($link['purchase_number'] ?? null)],
                default => [null, null],
            };

            return [
                'handle' => LinkHandle::of(self::int($link['id'] ?? null)),
                'target' => $column,
                'record' => $record,
                'label' => $label === '' ? null : $label,
                'role' => self::text($link['role'] ?? null),
                'linked_at' => self::text($link['recorded_at'] ?? null),
            ];
        }

        return null;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
