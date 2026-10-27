<?php

namespace App\Modules\PTY\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PTY\Actions\AddClientUnit;
use App\Modules\PTY\Actions\ArchiveClient;
use App\Modules\PTY\Actions\CreateClientOrganization;
use App\Modules\PTY\Actions\UpdateClient;
use App\Modules\PTY\Data\PartyFields;
use App\Modules\PTY\Queries\ClientDirectory;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-054 *Klien* (ROUTE-054, ROUTE-141, ROUTE-142; INT-141, INT-142; NAV-054; SCR-50; J-MD-02): every active account's
 * page of client organizations — search-first, the term `q`, the cursor `setelah` and the organization `klien` as
 * whitelisted address keys (NV-05) — with each part under its own read class (PJ-10), and the `parties.manage`
 * commands that create, edit and archive an organization, a unit with its address, an address or a PIC. Each NX-01
 * command carries its `command_id`, its answer in place as flash data (IP-01) and its field errors at their fields
 * (IP-14). A creation sent `inline` from a form — PG-013's — goes back to that form with the new values chosen (IP-19).
 */
final class ClientsController extends Controller
{
    public const KINDS = ['organization', 'unit', 'address', 'contact'];

    public function __construct(private readonly ClientDirectory $clients) {}

    /** `PTY ShowClients` (ROUTE-054). */
    public function show(Request $request): HttpResponse
    {
        $term = $request->query('q');
        $after = $request->query('setelah');
        $selected = $request->query('klien');
        $term = is_string($term) && trim($term) !== '' ? mb_substr(trim($term), 0, 100) : null;

        return Inertia::render('PTY/Clients', [
            ...$this->clients->screen(PartyForms::actor($request), $term, is_string($after) ? $after : null, is_string($selected) ? $selected : null),
            'term' => $term,
        ])->toResponse($request);
    }

    /** `PTY CreateClientOrganization · AddClientUnit` (ROUTE-141). */
    public function store(Request $request, CreateClientOrganization $create, AddClientUnit $add): RedirectResponse
    {
        $kind = $request->input('kind');
        $parent = $kind === 'organization' ? [] : ['klien' => ['required', 'string']];
        $input = PartyForms::validated($request, ['command_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(self::KINDS)], 'inline' => ['nullable', 'boolean'], ...$parent, ...match ($kind) {
            'organization' => [...self::organizationRules(), 'unit_name' => ['nullable', 'string', 'max:200'], ...self::addressRules(false), ...self::contactRules(false),
                'acknowledged' => ['nullable', 'array', 'max:20'], 'acknowledged.*' => ['uuid'], 'reason' => ['nullable', 'string', 'max:500']],
            'unit' => ['unit_name' => ['required', 'string', 'max:200'], ...self::addressRules(false)],
            'address' => ['unit' => ['required', 'string'], ...self::addressRules(true)],
            default => ['unit' => ['required', 'string'], ...self::contactRules(true)],
        }]);
        $commandId = PartyForms::text($input['command_id']);
        $actor = PartyForms::actor($request);
        $organization = $kind === 'organization' ? null : ($this->clients->find(PartyForms::text($input['klien'])) ?? throw new NotFoundHttpException);
        $unit = $organization !== null && in_array($kind, ['address', 'contact'], true) ? ($this->clients->unitOf($organization, PartyForms::text($input['unit'])) ?? throw new NotFoundHttpException) : null;
        $unitName = PartyFields::of(['name'], ['name' => $input['unit_name'] ?? null])['name'];

        $outcome = match (true) {
            $organization === null => $create->run($commandId, $actor, ['name' => PartyFields::name(PartyForms::text($input['name'])), 'npwp' => PartyForms::optional($input['npwp'] ?? null)],
                $unitName, $unitName === null ? null : self::address($input), $unitName === null ? null : self::contact($input), PartyForms::list($input['acknowledged'] ?? []), PartyForms::optional($input['reason'] ?? null)),
            $kind === 'unit' => $add->unit($commandId, $actor, $organization, (string) $unitName, self::address($input)),
            $kind === 'address' && $unit !== null => $add->address($commandId, $actor, $unit, self::address($input) ?? []),
            $unit !== null => $add->contact($commandId, $actor, $unit, self::contact($input) ?? []),
            default => throw new NotFoundHttpException,
        };
        PartyForms::fieldRefusal($outcome);

        $committed = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null;
        $id = $organization->public_id ?? ($committed ? $this->clients->organizationOf((int) $outcome->resultId)['id'] ?? null : null);
        $inline = $request->boolean('inline');
        $created = $committed && $organization === null && $inline ? ($this->clients->created((int) $outcome->resultId, $commandId) ?? []) : [];
        $matches = $outcome->code === 'PF-21' ? $this->clients->matches(PartyForms::list($outcome->state['matches'] ?? [])) : [];
        $to = $inline || ! $committed ? url()->previous() : '/data-master/klien?'.http_build_query(['klien' => $id]);

        return PartyForms::answer($outcome, 'parties.answer.client_'.PartyForms::text($kind), $to, $matches, $created);
    }

    /** `PTY UpdateClient · ArchiveClient` (ROUTE-142). */
    public function update(Request $request, string $id, UpdateClient $change, ArchiveClient $archive): RedirectResponse
    {
        $organization = $this->clients->find($id) ?? throw new NotFoundHttpException;
        $kind = $request->input('kind');
        $action = $request->input('action');
        $fields = $action === 'archive' ? ['reason' => ['nullable', 'string', 'max:500']] : match ($kind) {
            'organization' => self::organizationRules(),
            'unit' => ['unit_name' => ['required', 'string', 'max:200']],
            'address' => self::addressRules(true),
            default => self::contactRules(true),
        };
        $input = PartyForms::validated($request, ['command_id' => ['required', 'uuid'], 'action' => ['required', Rule::in(['change', 'archive'])], 'kind' => ['required', Rule::in(self::KINDS)],
            'row' => [$kind === 'organization' ? 'nullable' : 'required', 'string'], 'lock_version' => ['required', 'integer', 'min:0'], ...$fields]);
        /** @var 'organization'|'unit'|'address'|'contact' $kind */
        $kind = PartyForms::text($input['kind']);
        $row = $this->clients->rowOf($organization, $kind, PartyForms::optional($input['row'] ?? null)) ?? throw new NotFoundHttpException;
        $commandId = PartyForms::text($input['command_id']);
        $lockVersion = (int) PartyForms::text($input['lock_version']);

        $outcome = $input['action'] === 'archive'
            ? $archive->run($commandId, PartyForms::actor($request), $kind, $organization->id, $row, $lockVersion, PartyForms::optional($input['reason'] ?? null))
            : $change->run($commandId, PartyForms::actor($request), $kind, $organization->id, $row, $lockVersion, match ($kind) {
                'organization' => ['name' => PartyFields::name(PartyForms::text($input['name'])), 'npwp' => PartyForms::optional($input['npwp'] ?? null)],
                'unit' => ['name' => PartyFields::name(PartyForms::text($input['unit_name']))],
                'address' => self::address($input) ?? [],
                'contact' => self::contact($input) ?? [],
            });
        PartyForms::fieldRefusal($outcome);

        return PartyForms::answer($outcome, 'parties.answer.client_'.PartyForms::text($input['action']), '/data-master/klien?'.http_build_query(['klien' => $organization->public_id]));
    }

    /** @return array<string, list<mixed>> */
    private static function organizationRules(): array
    {
        return ['name' => ['required', 'string', 'max:200'], 'npwp' => ['nullable', 'string', 'regex:/^[0-9 .\-]{15,25}$/']];
    }

    /** @return array<string, list<mixed>> */
    private static function addressRules(bool $required): array
    {
        return ['address_label' => ['nullable', 'string', 'max:120'], 'address_lines' => [$required ? 'required' : 'nullable', 'string', 'max:500'], 'city' => ['nullable', 'string', 'max:120']];
    }

    /** @return array<string, list<mixed>> */
    private static function contactRules(bool $required): array
    {
        return ['contact_name' => [$required ? 'required' : 'nullable', 'string', 'max:200'], 'contact_position' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{6,30}$/'], 'contact_email' => ['nullable', 'string', 'email:rfc', 'max:254']];
    }

    /**
     * The address the form sent, or null when it sent none.
     *
     * @param  array<string, mixed>  $input
     * @return array{label: ?string, address_lines: string, city: ?string}|null
     */
    private static function address(array $input): ?array
    {
        $lines = PartyForms::optional($input['address_lines'] ?? null);

        return $lines === null ? null : ['label' => PartyForms::optional($input['address_label'] ?? null), 'address_lines' => $lines, 'city' => PartyForms::optional($input['city'] ?? null)];
    }

    /**
     * The PIC the form sent, or null when it sent none.
     *
     * @param  array<string, mixed>  $input
     * @return array{name: string, position: ?string, phone: ?string, email: ?string}|null
     */
    private static function contact(array $input): ?array
    {
        $name = PartyForms::optional($input['contact_name'] ?? null);

        return $name === null ? null : ['name' => PartyFields::name($name), 'position' => PartyForms::optional($input['contact_position'] ?? null),
            'phone' => PartyForms::optional($input['contact_phone'] ?? null), 'email' => PartyForms::optional($input['contact_email'] ?? null)];
    }
}
