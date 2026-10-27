<?php

namespace App\Modules\PTY\Queries;

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PTY\Services\PartyCommands;
use App\Modules\PUR\Queries\SupplierPrices;
use App\Support\Search\NameSearch;
use App\Support\Search\Row;
use App\Support\Search\Term;
use InvalidArgumentException;

/**
 * PG-055 *Pemasok* (ROUTE-055; SCR-51; WF-MD-03; PERMISSIONS_MATRIX §7.2 row 21; PJ-09; PJ-10): the suppliers' S0
 * names to every active account — a page in name order after a cursor, or PF-21's list for a term —, chosen directly
 * with no comparison, scoring or ranking step (CAP-02; SH-01); one supplier's tax id, address and contact to
 * `projects.view` or `cost.view` with a company in scope; its purchase-price history from PUR's query class, per
 * company in scope, only with `cost.view`, never pooled (BR-ACC-02).
 */
final class SupplierDirectory
{
    public const PAGE = 25;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(
        private readonly SupplierPrices $prices,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /**
     * The page's props: the list, the chosen supplier's detail, and the hint whether the viewer may manage (AZ-01).
     *
     * @return array{list: array{rows: list<array{id: string, name: string, active: bool}>, more: ?string, cut: bool}, supplier: ?array<string, mixed>, can: array{manage: bool}}
     */
    public function screen(int $viewerId, ?string $term, ?string $after, ?string $selected): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $supplier = $selected === null ? null : $this->find($selected);

        return [
            'list' => $this->page($term, $after),
            'supplier' => $supplier === null ? null : $this->detail($viewer, $supplier),
            'can' => ['manage' => $viewer->allows(PartyCommands::CAPABILITY) && ! $viewer->scope->isEmpty()],
        ];
    }

    /** PJ-10: a supplier's tax id, address and contact. */
    public static function details(): Audience
    {
        return Audience::anyOf(Audience::family('projects.view'), Audience::family('cost.view'));
    }

    /** @return array{rows: list<array{id: string, name: string, active: bool}>, more: ?string, cut: bool} */
    public function page(?string $term, ?string $after): array
    {
        $base = Supplier::query()->select(['suppliers.id as search_key', 'suppliers.public_id', 'suppliers.name', 'suppliers.is_active']);
        $search = self::term($term);

        if ($search !== null) {
            $found = NameSearch::run($base, 'suppliers.name', $search, self::PAGE);

            return ['rows' => array_map(self::listed(...), $found['rows']), 'more' => null, 'cut' => $found['cut']];
        }

        $cursor = $after !== null && preg_match(self::UUID, $after) === 1 ? Supplier::query()->where('public_id', $after)->first(['id', 'name']) : null;

        if ($cursor !== null) {
            $base->whereRaw('(lower(name), id) > (lower(?), ?)', [$cursor->name, $cursor->id]);
        }

        $found = array_values($base->orderByRaw('lower(name)')->orderBy('id')->limit(self::PAGE + 1)->get()->all());
        $rows = array_map(self::listed(...), array_slice($found, 0, self::PAGE));

        return ['rows' => $rows, 'more' => count($found) > self::PAGE ? $rows[self::PAGE - 1]['id'] : null, 'cut' => false];
    }

    public function find(string $publicId): ?Supplier
    {
        return preg_match(self::UUID, $publicId) === 1 ? Supplier::query()->where('public_id', $publicId)->first() : null;
    }

    /**
     * @return array{id: string, name: string, active: bool, deactivated_at: ?string, lock_version: int,
     *     details?: array<string, ?string>, prices?: list<array<string, string>>}
     */
    public function detail(Authority $viewer, Supplier $supplier): array
    {
        $detail = [
            'id' => $supplier->public_id,
            'name' => $supplier->name,
            'active' => $supplier->is_active,
            'deactivated_at' => $supplier->deactivated_at?->toIso8601String(),
            'lock_version' => $supplier->lock_version,
        ];

        if (self::details()->admits($viewer)) {
            $detail['details'] = ['npwp' => $supplier->npwp, 'address_lines' => $supplier->address_lines, 'city' => $supplier->city, 'phone' => $supplier->phone, 'email' => $supplier->email];
        }

        if (Audience::family(SupplierPrices::CAPABILITY)->admits($viewer)) {
            $detail['prices'] = $this->prices->history($viewer, $supplier->id);
        }

        return $detail;
    }

    /**
     * A creation's duplicate list by name — S0 to every active account —, each linked to its record.
     *
     * @param  list<string>  $ids
     * @return list<array{id: string, label: string, href: string}>
     */
    public function matches(array $ids): array
    {
        $rows = $ids === [] ? [] : Supplier::query()->whereIn('public_id', $ids)->orderByRaw('lower(name)')->get(['public_id', 'name'])->all();

        return array_values(array_map(fn (Supplier $row): array => ['id' => $row->public_id, 'label' => $row->name, 'href' => '/data-master/pemasok?pemasok='.$row->public_id], $rows));
    }

    /** @return array{id: string, name: string}|null */
    public function supplierOf(int $id): ?array
    {
        $row = Supplier::query()->find($id, ['public_id', 'name']);

        return $row === null ? null : ['id' => $row->public_id, 'name' => $row->name];
    }

    /** @return array{id: string, name: string, active: bool} */
    private static function listed(object $row): array
    {
        return ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'active' => data_get($row, 'is_active') === true];
    }

    private static function term(?string $term): ?Term
    {
        try {
            return $term === null ? null : new Term($term);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
