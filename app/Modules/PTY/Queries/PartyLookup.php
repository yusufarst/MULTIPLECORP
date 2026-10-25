<?php

namespace App\Modules\PTY\Queries;

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use App\Support\Search\NameSearch;
use App\Support\Search\Row;
use App\Support\Search\Term;

/**
 * The partners' part of search and lookup (PERFORMANCE PF-20–PF-22; IP-17): client organizations and suppliers
 * are S0 names open to every active account; a client unit and a PIC are master S2 relations, searched only by
 * a holder of `projects.view` with a company in scope (CS-06; PJ-10). A unit or PIC has no address of its own —
 * it is reached through its organization (INFORMATION_ARCHITECTURE §11) —, so its result carries the
 * organization's public identifier and its own name. Only active rows are offered.
 */
final class PartyLookup
{
    /** @return array{rows: list<array{type: string, id: string, code: null, label: string, detail?: string}>, cut: bool} */
    public function clients(Term $term, int $rows): array
    {
        $base = ClientOrganization::query()
            ->select(['client_organizations.id as search_key', 'client_organizations.public_id', 'client_organizations.name'])
            ->where('client_organizations.is_active', true);

        return $this->found(NameSearch::run($base, 'client_organizations.name', $term, $rows), 'client');
    }

    /** @return array{rows: list<array{type: string, id: string, code: null, label: string, detail?: string}>, cut: bool} */
    public function suppliers(Term $term, int $rows): array
    {
        $base = Supplier::query()
            ->select(['suppliers.id as search_key', 'suppliers.public_id', 'suppliers.name'])
            ->where('suppliers.is_active', true);

        return $this->found(NameSearch::run($base, 'suppliers.name', $term, $rows), 'supplier');
    }

    /** @return array{rows: list<array{type: string, id: string, code: null, label: string, detail?: string}>, cut: bool} */
    public function units(Authority $viewer, Term $term, int $rows): array
    {
        if (! self::relations()->admits($viewer)) {
            return ['rows' => [], 'cut' => false];
        }

        $base = ClientUnit::query()
            ->join('client_organizations', 'client_organizations.id', '=', 'client_units.client_organization_id')
            ->select(['client_units.id as search_key', 'client_organizations.public_id', 'client_units.name', 'client_organizations.name as detail'])
            ->where('client_units.is_active', true)
            ->where('client_organizations.is_active', true);

        return $this->found(NameSearch::run($base, 'client_units.name', $term, $rows), 'unit');
    }

    /**
     * A PIC's name has no trigram index (DATABASE §21): it is found by its name's prefix within the bound.
     *
     * @return array{rows: list<array{type: string, id: string, code: null, label: string, detail?: string}>, cut: bool}
     */
    public function contacts(Authority $viewer, Term $term, int $rows): array
    {
        if (! self::relations()->admits($viewer)) {
            return ['rows' => [], 'cut' => false];
        }

        $found = ClientContact::query()
            ->join('client_units', 'client_units.id', '=', 'client_contacts.client_unit_id')
            ->join('client_organizations', 'client_organizations.id', '=', 'client_units.client_organization_id')
            ->select(['client_contacts.id as search_key', 'client_organizations.public_id', 'client_contacts.name', 'client_units.name as detail'])
            ->where('client_contacts.is_active', true)
            ->where('client_units.is_active', true)
            ->where('client_organizations.is_active', true)
            ->whereRaw("lower(client_contacts.name) like ? escape '\\'", [$term->likeLiteral().'%'])
            ->orderByRaw('lower(client_contacts.name)')
            ->limit($rows)
            ->get()
            ->all();

        return $this->found(['rows' => array_values($found), 'cut' => false], 'pic');
    }

    /** PJ-10: the S2 relations of a client to `projects.view` with a company in scope. */
    public static function relations(): Audience
    {
        return Audience::family('projects.view');
    }

    /**
     * @param  array{rows: list<object>, cut: bool}  $found
     * @return array{rows: list<array{type: string, id: string, code: null, label: string, detail?: string}>, cut: bool}
     */
    private function found(array $found, string $type): array
    {
        $rows = [];

        foreach ($found['rows'] as $row) {
            $detail = Row::nullable($row, 'detail');
            $rows[] = ['type' => $type, 'id' => Row::text($row, 'public_id'), 'code' => null, 'label' => Row::text($row, 'name'), ...($detail === null ? [] : ['detail' => $detail])];
        }

        return ['rows' => $rows, 'cut' => $found['cut']];
    }
}
