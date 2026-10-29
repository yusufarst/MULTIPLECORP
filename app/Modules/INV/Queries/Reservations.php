<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-025 *Reservasi* (ROUTE-025; `INV ShowReservations`; WF-INV-02; J-INV-02) and the references its commands name: each
 * company in the viewer's scope its own branch, labelled and never totalled across companies (CS-08; PF-41); the tabs —
 * active reservations, the residual ones of completed or cancelled projects "perlu dilepas" (QS-16), the history —, sort and
 * "Muat Lagi" in the address (IP-18). A product's reservations of every company — the pool its supply serves — show another
 * company's only by quantity and the relation marker (PJ-04). The scope is applied inside every query first (CS-07).
 */
final class Reservations
{
    public const PAGE = 50;

    public const TABS = ['aktif', 'dilepas', 'riwayat'];

    public const SORTS = ['terbaru', 'proyek', 'produk'];

    private const CLOSED = ['COMPLETED_NORMAL', 'COMPLETED_FORCED', 'CANCELLED'];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * @param  array{cabang: ?string, tab: ?string, produk: ?string, urut: ?string, setelah: ?string}  $query
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, array $query): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('stock.reserve', (int) Row::text($company, 'id'))) {
                $branches[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
            }
        }

        $branch = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $query['cabang']) {
                $branch = $candidate;
            }
        }

        $tab = in_array($query['tab'], self::TABS, true) ? $query['tab'] : 'aktif';
        $sort = in_array($query['urut'], self::SORTS, true) ? $query['urut'] : 'terbaru';
        $offset = preg_match('/^\d{1,6}$/', (string) $query['setelah']) === 1 ? (int) $query['setelah'] : 0;
        $product = $this->product($query['produk']);
        $rows = $branch === null ? [] : $this->rows($viewer, $branch['internal'], $tab, $sort, $product, $offset);

        return [
            'branches' => array_map(fn (array $branch): array => array_diff_key($branch, ['internal' => true]), $branches),
            'branch' => $branch === null ? null : $branch['id'],
            'filters' => ['tab' => $tab, 'urut' => $sort, 'produk' => $product === null ? null : $product['public_id']],
            'product' => $product === null ? null : ['id' => $product['public_id'], 'name' => $product['name']],
            'rows' => array_slice($rows, 0, self::PAGE),
            'more' => count($rows) > self::PAGE ? (string) ($offset + self::PAGE) : null,
            'can' => ['reserve' => $branch !== null && $viewer->allows('stock.reserve', $branch['internal'])],
        ];
    }

    /**
     * The line a "Reservasi" names, when the viewer may reserve for its project's company (CS-03): its project, company and line.
     *
     * @return ?array{line: int, company: int, project: string, scale: int}
     */
    public function line(int $viewerId, string $projectPublicId, string $lineHandle): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $projectPublicId) !== 1 || ! RowHandle::valid($lineHandle)) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $project = $viewer->scope->apply(DB::table('projects as p'), 'p.company_id')->where('p.public_id', strtolower($projectPublicId))->first(['p.id', 'p.company_id', 'p.public_id']);

        if (! is_object($project) || ! $viewer->allows('stock.reserve', (int) Row::text($project, 'company_id')) || ! $viewer->allows('projects.view', (int) Row::text($project, 'company_id'))) {
            return null;
        }

        foreach (DB::table('project_lines')->where('project_id', (int) Row::text($project, 'id'))->get(['id', 'quantity_scale']) as $line) {
            if (hash_equals(RowHandle::of('project_lines', (int) Row::text($line, 'id')), $lineHandle)) {
                return ['line' => (int) Row::text($line, 'id'), 'company' => (int) Row::text($project, 'company_id'), 'project' => Row::text($project, 'public_id'), 'scale' => (int) Row::text($line, 'quantity_scale')];
            }
        }

        return null;
    }

    /**
     * The reservation a "Kurangi Reservasi" or "Lepas" names among its project's, when that project's company is in the
     * viewer's scope with `stock.reserve` (CS-03): whatever its state, so a replay answers its recorded outcome (CI-05) and a
     * stale page's command on an ended reservation its refusal in place (C-05; NV-04).
     *
     * @return ?array{reservation: int, company: int, scale: int}
     */
    public function reservation(int $viewerId, string $projectPublicId, string $handle): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $projectPublicId) !== 1 || ! RowHandle::valid($handle)) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $project = $viewer->scope->apply(DB::table('projects as p'), 'p.company_id')->where('p.public_id', strtolower($projectPublicId))->first(['p.id', 'p.company_id']);

        if (! is_object($project) || ! $viewer->allows('stock.reserve', (int) Row::text($project, 'company_id'))) {
            return null;
        }

        foreach (DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->where('l.project_id', (int) Row::text($project, 'id'))
            ->get(['r.id', 'r.company_id', 'r.quantity_scale']) as $row) {
            if (hash_equals(RowHandle::of('reservations', (int) Row::text($row, 'id')), $handle)) {
                return ['reservation' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'scale' => (int) Row::text($row, 'quantity_scale')];
            }
        }

        return null;
    }

    /**
     * @param  ?array{id: int, public_id: string, name: string}  $product
     * @return list<array<string, mixed>>
     */
    private function rows(Authority $viewer, int $company, string $tab, string $sort, ?array $product, int $offset): array
    {
        $query = DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')
            ->join('products as d', 'd.id', '=', 'r.product_id')->leftJoin('units as u', 'u.id', '=', 'd.base_unit_id')
            ->leftJoin('companies as c', 'c.id', '=', 'r.company_id');
        // A product's reservations reach every company of the pool, each other company's masked (PJ-04); otherwise the branch's.
        $product === null
            ? $viewer->scope->apply($query, 'r.company_id')->where('r.company_id', $company)
            : $query->where('r.product_id', $product['id'])->where(fn (Builder $mine) => $mine->where('r.company_id', $company)->orWhere('r.state', 'ACTIVE'));

        // The residual tab and the project sort are the branch's own: another company's reservation of the pool is listed
        // as ACTIVE and ordered after them by itself, never by its project's state or number (PJ-04).
        match ($tab) {
            'dilepas' => $query->where('r.company_id', $company)->where('r.state', 'ACTIVE')->whereIn('p.state', self::CLOSED),
            'riwayat' => $query->whereIn('r.state', ['RELEASED', 'CONSUMED']),
            default => $query->where('r.state', 'ACTIVE')->where(fn (Builder $open) => $open->where('r.company_id', '<>', $company)->orWhereNotIn('p.state', self::CLOSED)),
        };
        match ($sort) {
            'proyek' => $query->orderByRaw('case when r.company_id = ? then p.project_number end nulls last', [$company])
                ->orderByRaw('case when r.company_id = ? then l.line_no end nulls last', [$company])->orderByDesc('r.id'),
            'produk' => $query->orderBy('d.name')->orderByDesc('r.id'),
            default => $query->orderByDesc('r.id'),
        };

        $rows = [];

        foreach ($query->offset($offset)->limit(self::PAGE + 1)->get(['r.id', 'r.state', 'r.remaining_qty_base', 'r.company_id', 'r.product_id', 'l.line_no', 'l.id as line_id',
            'p.public_id as project', 'p.project_number', 'p.title', 'p.state as project_state', 'd.public_id as product_public_id', 'd.name as product', 'u.code as unit', 'c.code as company']) as $row) {
            $owner = (int) Row::text($row, 'company_id');
            $seen = $owner === $company || ($viewer->scope->includes($owner) && $viewer->allows('projects.view', $owner));
            $active = Row::text($row, 'state') === 'ACTIVE';
            $reservable = $active && $seen && $viewer->allows('stock.reserve', $owner);
            // The row's key in the list only — a handle no command resolves.
            $key = RowHandle::of('reservation_rows', (int) Row::text($row, 'id'));
            $rows[] = $seen ? [
                'key' => $key,
                'handle' => RowHandle::of('reservations', (int) Row::text($row, 'id')),
                'state' => Row::text($row, 'state'),
                'quantity' => Row::text($row, 'remaining_qty_base'),
                'unit' => Row::nullable($row, 'unit') ?? '',
                'product' => Row::text($row, 'product'),
                // The product's pool, every company's reservations of it (PJ-04's masking applied above).
                'pool' => Row::text($row, 'product_public_id'),
                'company' => Row::nullable($row, 'company') ?? '',
                'project' => ['id' => Row::text($row, 'project'), 'number' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'href' => '/proyek/'.Row::text($row, 'project')],
                'line' => ['no' => (int) Row::text($row, 'line_no'), 'handle' => RowHandle::of('project_lines', (int) Row::text($row, 'line_id'))],
                'residual' => $active && in_array(Row::text($row, 'project_state'), self::CLOSED, true),
                'may' => ['reserve' => $reservable && Row::text($row, 'project_state') === 'ACTIVE', 'reduce' => $reservable, 'release' => $reservable],
            ] : [
                // Another company's reservation of the pooled product: its quantity and the relation marker, nothing more (PJ-04).
                'key' => $key,
                'handle' => null,
                'state' => Row::text($row, 'state'),
                'quantity' => Row::text($row, 'remaining_qty_base'),
                'unit' => Row::nullable($row, 'unit') ?? '',
                'product' => Row::text($row, 'product'),
                // The product's pool, every company's reservations of it (PJ-04's masking applied above).
                'pool' => Row::text($row, 'product_public_id'),
                'company' => null,
                'project' => null,
                'line' => null,
                'residual' => false,
                'may' => ['reserve' => false, 'reduce' => false, 'release' => false],
            ];
        }

        return $rows;
    }

    /**
     * The pool's product by its public identifier, as the catalogue names it (PG-056).
     *
     * @return ?array{id: int, public_id: string, name: string}
     */
    private function product(?string $publicId): ?array
    {
        if ($publicId === null || preg_match('/^[0-9a-f-]{36}$/', $publicId) !== 1) {
            return null;
        }

        $row = DB::table('products')->where('public_id', strtolower($publicId))->first(['id', 'public_id', 'name']);

        return is_object($row) ? ['id' => (int) Row::text($row, 'id'), 'public_id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name')] : null;
    }
}
