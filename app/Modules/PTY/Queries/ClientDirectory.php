<?php

namespace App\Modules\PTY\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Queries\ClientProjects;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-054 *Klien* (ROUTE-054; SCR-50; WF-MD-02; PERMISSIONS_MATRIX §7.2 rows 17–20; PJ-10): the organizations' S0
 * names to every active account — a page in name order after a cursor; the page's search-first lookup is ROUTE-281's
 * PF-21 list, the term in a request body, never the address (H7-10) —,
 * and one organization's detail: its NPWP, units, unit addresses and PICs to `projects.view` with a company in
 * scope, and its related projects only through PRJ's scope-filtered list, without a count (UXN-23; D-IA-14). A unit,
 * address or PIC has no public identifier: the page names it by a row handle (RowHandle), resolved here among the
 * organization's own rows. A detail reads a fixed number of statements whatever its rows (PFM-02).
 */
final class ClientDirectory
{
    public const PAGE = 25;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(
        private readonly ClientProjects $projects,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /**
     * The page's props: the list, the chosen organization's detail, and the hints of what the viewer may do — the
     * server decides every request (AZ-01).
     *
     * @return array{list: array{rows: list<array{id: string, name: string, active: bool}>, more: ?string}, client: ?array<string, mixed>, can: array{manage: bool, relations: bool}}
     */
    public function screen(int $viewerId, ?string $after, ?string $selected): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $organization = $selected === null ? null : $this->find($selected);

        return [
            'list' => $this->page($after),
            'client' => $organization === null ? null : $this->detail($viewer, $organization),
            'can' => ['manage' => $viewer->allows(PartyCommands::CAPABILITY) && ! $viewer->scope->isEmpty(), 'relations' => PartyLookup::relations()->admits($viewer)],
        ];
    }

    /** The account's authority now, for the lookups a route answers. */
    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /** @return array{rows: list<array{id: string, name: string, active: bool}>, more: ?string} */
    public function page(?string $after): array
    {
        $base = ClientOrganization::query()->select(['client_organizations.id as search_key', 'client_organizations.public_id', 'client_organizations.name', 'client_organizations.is_active']);

        $cursor = $after !== null && preg_match(self::UUID, $after) === 1 ? ClientOrganization::query()->where('public_id', $after)->first(['id', 'name']) : null;

        if ($cursor !== null) {
            $base->whereRaw('(lower(name), id) > (lower(?), ?)', [$cursor->name, $cursor->id]);
        }

        $found = array_values($base->orderByRaw('lower(name)')->orderBy('id')->limit(self::PAGE + 1)->get()->all());
        $rows = array_map(self::listed(...), array_slice($found, 0, self::PAGE));

        return ['rows' => $rows, 'more' => count($found) > self::PAGE ? $rows[self::PAGE - 1]['id'] : null];
    }

    public function find(string $publicId): ?ClientOrganization
    {
        return preg_match(self::UUID, $publicId) === 1 ? ClientOrganization::query()->where('public_id', $publicId)->first() : null;
    }

    /**
     * @return array{id: string, name: string, active: bool, deactivated_at: ?string, lock_version: int, npwp?: ?string,
     *     units?: list<array<string, mixed>>, projects?: array{rows: list<array<string, string>>, more: bool}}
     */
    public function detail(Authority $viewer, ClientOrganization $organization): array
    {
        $detail = [
            'id' => $organization->public_id,
            'name' => $organization->name,
            'active' => $organization->is_active,
            'deactivated_at' => $organization->deactivated_at?->toIso8601String(),
            'lock_version' => $organization->lock_version,
        ];

        if (! PartyLookup::relations()->admits($viewer)) {
            return $detail;
        }

        $units = ClientUnit::query()->where('client_organization_id', $organization->id)->orderByDesc('is_active')->orderByRaw('lower(name)')->orderBy('id')
            ->get(['id', 'name', 'is_active', 'lock_version'])->all();
        $ids = array_values(array_map(fn (ClientUnit $unit): int => $unit->id, $units));
        $addresses = self::children('client_addresses', $ids, ['label', 'address_lines', 'city']);
        $contacts = self::children('client_contacts', $ids, ['name', 'position', 'phone', 'email']);

        return [...$detail,
            'npwp' => $organization->npwp,
            'units' => array_values(array_map(fn (ClientUnit $unit): array => [
                'handle' => RowHandle::of('client_units', $unit->id),
                'name' => $unit->name,
                'active' => $unit->is_active,
                'lock_version' => $unit->lock_version,
                'addresses' => $addresses[$unit->id] ?? [],
                'contacts' => $contacts[$unit->id] ?? [],
            ], $units)),
            'projects' => $this->projects->of($viewer, $organization->id),
        ];
    }

    /**
     * The organization's own row of the kind the handle names — the organization itself for `organization` —, its
     * identifier, or null when the handle names none of its rows.
     *
     * @param  'organization'|'unit'|'address'|'contact'  $kind
     */
    public function rowOf(ClientOrganization $organization, string $kind, ?string $handle): ?int
    {
        return $this->rowIn($organization->id, $kind, $handle);
    }

    /**
     * {@see self::rowOf()} for the organization named by its identifier — the form of a higher tier, which reads PTY
     * through this query class and never its models (ARCHITECTURE §3).
     *
     * @param  'organization'|'unit'|'address'|'contact'  $kind
     */
    public function rowIn(int $organizationId, string $kind, ?string $handle): ?int
    {
        if ($kind === 'organization') {
            return $organizationId;
        }

        if ($handle === null || ! RowHandle::valid($handle)) {
            return null;
        }

        $units = DB::table('client_units')->where('client_organization_id', $organizationId)->select('id');
        $ids = match ($kind) {
            'unit' => $units->pluck('id')->all(),
            'address' => DB::table('client_addresses')->whereIn('client_unit_id', $units)->pluck('id')->all(),
            'contact' => DB::table('client_contacts')->whereIn('client_unit_id', $units)->pluck('id')->all(),
        };
        $table = ['unit' => 'client_units', 'address' => 'client_addresses', 'contact' => 'client_contacts'][$kind];

        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * An organization by public identifier — its identifier, name and whether it is active —, or null; and its detail
     * as {@see self::detail()} gives it, for a higher tier's form (ARCHITECTURE §3).
     *
     * @return array{id: int, name: string, active: bool}|null
     */
    public function organization(string $publicId): ?array
    {
        $row = $this->find($publicId);

        return $row === null ? null : ['id' => $row->id, 'name' => $row->name, 'active' => $row->is_active];
    }

    /** @return array<string, mixed>|null */
    public function detailOf(Authority $viewer, int $organizationId): ?array
    {
        $row = ClientOrganization::query()->find($organizationId);

        return $row === null ? null : $this->detail($viewer, $row);
    }

    public function unitOf(ClientOrganization $organization, ?string $handle): ?ClientUnit
    {
        $id = $this->rowOf($organization, 'unit', $handle);

        return $id === null ? null : ClientUnit::query()->find($id);
    }

    /**
     * A search-first creation's duplicate list by name — S0 to every active account —, each linked to its record.
     *
     * @param  list<string>  $ids
     * @return list<array{id: string, label: string, href: string}>
     */
    public function matches(array $ids): array
    {
        $rows = $ids === [] ? [] : ClientOrganization::query()->whereIn('public_id', $ids)->orderByRaw('lower(name)')->get(['public_id', 'name'])->all();

        return array_values(array_map(fn (ClientOrganization $row): array => ['id' => $row->public_id, 'label' => $row->name, 'href' => '/data-master/klien?klien='.$row->public_id], $rows));
    }

    /** @return array{id: string, name: string}|null */
    public function organizationOf(int $id): ?array
    {
        $row = ClientOrganization::query()->find($id, ['public_id', 'name']);

        return $row === null ? null : ['id' => $row->public_id, 'name' => $row->name];
    }

    /**
     * What an inline creation chose (IP-19): the organization and the unit, address and PIC its command added, each
     * by the handle the form sends back.
     *
     * @return array{organization: array{id: string, name: string}, unit: ?array{handle: string, label: string}, address: ?array{handle: string, label: string}, contact: ?array{handle: string, label: string}}|null
     */
    public function created(int $organizationId, string $commandId): ?array
    {
        $organization = $this->organizationOf($organizationId);

        if ($organization === null) {
            return null;
        }

        $unit = DB::table('client_units')->where('client_organization_id', $organizationId)->where('command_id', $commandId)->first(['id', 'name']);
        $unitId = $unit === null ? null : (int) Row::text($unit, 'id');
        $address = $unitId === null ? null : DB::table('client_addresses')->where('client_unit_id', $unitId)->where('command_id', $commandId)->first(['id', 'label', 'address_lines']);
        $contact = $unitId === null ? null : DB::table('client_contacts')->where('client_unit_id', $unitId)->where('command_id', $commandId)->first(['id', 'name']);

        return [
            'organization' => $organization,
            'unit' => $unitId === null ? null : ['handle' => RowHandle::of('client_units', $unitId), 'label' => Row::text($unit, 'name')],
            'address' => $address === null ? null : ['handle' => RowHandle::of('client_addresses', (int) Row::text($address, 'id')), 'label' => Row::nullable($address, 'label') ?? Row::text($address, 'address_lines')],
            'contact' => $contact === null ? null : ['handle' => RowHandle::of('client_contacts', (int) Row::text($contact, 'id')), 'label' => Row::text($contact, 'name')],
        ];
    }

    /**
     * The addresses or PICs of the units, by unit, active first — one statement for all of them.
     *
     * @param  list<int>  $unitIds
     * @param  list<string>  $fields
     * @return array<int, list<array<string, mixed>>>
     */
    private static function children(string $table, array $unitIds, array $fields): array
    {
        if ($unitIds === []) {
            return [];
        }

        $rows = DB::table($table)->whereIn('client_unit_id', $unitIds)->orderByDesc('is_active')->orderBy('id')
            ->get(['id', 'client_unit_id', 'is_active', 'lock_version', ...$fields])->all();
        $grouped = [];

        foreach ($rows as $row) {
            $item = ['handle' => RowHandle::of($table, (int) Row::text($row, 'id')), 'active' => data_get($row, 'is_active') === true, 'lock_version' => (int) Row::text($row, 'lock_version')];

            foreach ($fields as $field) {
                $item[$field] = Row::nullable($row, $field);
            }

            $grouped[(int) Row::text($row, 'client_unit_id')][] = $item;
        }

        return $grouped;
    }

    /** @return array{id: string, name: string, active: bool} */
    private static function listed(object $row): array
    {
        return ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'active' => data_get($row, 'is_active') === true];
    }
}
