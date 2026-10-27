<?php

namespace App\Modules\PTY\Services;

use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientUnit;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The rows of the client hierarchy a command adds (WF-MD-02): a unit with its address, an address, a PIC — each
 * inserted with the command's identity (C-48) and audited (LG-01). A unit's name is unique within its organization
 * (DATABASE §4.3): one already there answers CONFLICT, as the lost race on the unique key does (BD-16; SQ-08), so
 * the answer is the same whichever command came first. The names an organization or a supplier would duplicate are
 * the active ones equal to it or beginning with it, PF-21's first part, so the record a search-first creation looks
 * for is never missed; with no unique key on such a name (DATABASE §4.3) the warning is the search's, not a lock's.
 */
final class ClientRows
{
    public function __construct(private readonly PartyCommands $commands) {}

    /** @param  array<string, string|null>  $address */
    public function unit(CommandContext $context, int $organizationId, string $name, ?array $address): ClientUnit
    {
        $this->freeUnitName($organizationId, $name, null);
        $unit = $this->insert($context, new ClientUnit, ['client_organization_id' => $organizationId, 'name' => $name]);
        $this->commands->record($context, 'PTY.ClientUnitAdded', 'client_unit', $unit->id, null, [], ['client_organization_id' => $organizationId, 'name' => $name]);

        if ($address !== null) {
            $this->address($context, $unit->id, $address);
        }

        return $unit;
    }

    /** @param  array<string, string|null>  $fields */
    public function address(CommandContext $context, int $unitId, array $fields): ClientAddress
    {
        $address = $this->insert($context, new ClientAddress, ['client_unit_id' => $unitId, ...$fields]);
        $this->commands->record($context, 'PTY.ClientAddressAdded', 'client_address', $address->id, null, [], ['client_unit_id' => $unitId, ...$fields]);

        return $address;
    }

    /** @param  array<string, string|null>  $fields */
    public function contact(CommandContext $context, int $unitId, array $fields): ClientContact
    {
        $contact = $this->insert($context, new ClientContact, ['client_unit_id' => $unitId, ...$fields]);
        $this->commands->record($context, 'PTY.ClientContactAdded', 'client_contact', $contact->id, null, [], ['client_unit_id' => $unitId, ...$fields]);

        return $contact;
    }

    /** BD-16: no other unit of the organization holds the name, compared lower-cased. */
    public function freeUnitName(int $organizationId, string $name, ?int $exceptId): void
    {
        $taken = DB::table('client_units')->where('client_organization_id', $organizationId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->where('id', '<>', $exceptId ?? 0)
            ->exists();

        if ($taken) {
            throw Refused::conflict('SQ-08', ['field' => 'unit_name']);
        }
    }

    /**
     * The active rows of `client_organizations` or `suppliers` whose name equals the given one or begins with it,
     * by public identifier, at most twenty (PF-21).
     *
     * @param  'client_organizations'|'suppliers'  $table
     * @return list<string>
     */
    public function sameNames(string $table, string $name, ?int $exceptId = null): array
    {
        $key = addcslashes(mb_strtolower($name), '\\%_');
        $found = DB::table($table)->where('is_active', true)
            ->whereRaw("lower(name) like ? escape '\\'", [$key.'%'])
            ->where('id', '<>', $exceptId ?? 0)
            ->orderByRaw('lower(name)')
            ->limit(20)
            ->pluck('public_id')
            ->all();

        return array_values(array_map(fn (mixed $id): string => is_string($id) ? $id : '', $found));
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $row
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function insert(CommandContext $context, Model $row, array $values): Model
    {
        $row->forceFill([...$values, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(),
            'recorded_by' => $context->request->actorUserId])->save();

        return $row;
    }
}
