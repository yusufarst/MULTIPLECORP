<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Services\ClientRows;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;

/**
 * `PTY CreateClientOrganization` (ROUTE-141; INT-141; NX-01; WF-MD-02; J-MD-02; AC-02): a client organization — an
 * ordinary row, UNY and UGM like any other (REF-009) —, with its first unit, the unit's address and a PIC when the
 * form gives them, in one command, so the inline creation from PG-013 answers once with every value the project
 * form chooses (IP-19). Search first: an active organization whose name equals the new one or begins with it
 * refuses the creation with that list (PF-21; IP-05), and only a second submission naming each match with its
 * reason creates the organization.
 */
final class CreateClientOrganization
{
    public const TYPE = 'PTY.CreateClientOrganization';

    public function __construct(
        private readonly PartyCommands $commands,
        private readonly ClientRows $rows,
    ) {}

    /**
     * @param  array{name: string, npwp: ?string}  $organization
     * @param  array<string, string|null>|null  $address
     * @param  array<string, string|null>|null  $contact
     * @param  list<string>  $acknowledged
     */
    public function run(string $commandId, int $actorUserId, array $organization, ?string $unitName, ?array $address, ?array $contact, array $acknowledged, ?string $reason): Outcome
    {
        $payload = [...$organization, 'unit' => $unitName, 'address' => $address, 'contact' => $contact, 'acknowledged' => $acknowledged, 'reason' => $reason];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, $payload, function (CommandContext $context) use ($organization, $unitName, $address, $contact, $acknowledged, $reason): CommandResult {
            $context->lock(new LockSet);
            DuplicateWarning::check('PF-21', $this->rows->sameNames('client_organizations', $organization['name']), $acknowledged, $reason);

            $row = $this->rows->insert($context, new ClientOrganization, $organization);
            $this->commands->record($context, 'PTY.ClientOrganizationCreated', 'client_organization', $row->id, $row->public_id, [], $organization, $reason);

            if ($unitName !== null) {
                $unit = $this->rows->unit($context, $row->id, $unitName, $address);

                if ($contact !== null) {
                    $this->rows->contact($context, $unit->id, $contact);
                }
            }

            return new CommandResult('client_organization', $row->id);
        });
    }
}
