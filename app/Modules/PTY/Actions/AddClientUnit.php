<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Services\ClientRows;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `PTY AddClientUnit` (ROUTE-141; INT-141; NX-01; WF-MD-02; J-MD-02): a unit with its address under an organization,
 * and an address or a PIC under a unit. The organization — and the unit — are locked at LK-08 before anything is
 * read, so an archive and an addition never pass each other, and a unit's name already in its organization answers
 * CONFLICT whichever of two concurrent creations commits first (BD-16; SQ-08). An archived organization or unit takes
 * nothing new (WF-MD-02).
 */
final class AddClientUnit
{
    public const UNIT = 'PTY.AddClientUnit';

    public const ADDRESS = 'PTY.AddClientAddress';

    public const CONTACT = 'PTY.AddClientContact';

    public function __construct(
        private readonly PartyCommands $commands,
        private readonly ClientRows $rows,
    ) {}

    /** @param  array<string, string|null>|null  $address */
    public function unit(string $commandId, int $actorUserId, ClientOrganization $organization, string $name, ?array $address): Outcome
    {
        return $this->commands->run($commandId, self::UNIT, $actorUserId, ['organization' => $organization->public_id, 'name' => $name, 'address' => $address],
            function (CommandContext $context) use ($organization, $name, $address): CommandResult {
                $context->lock((new LockSet)->add('client_organizations', ['id' => $organization->id]));
                self::active(ClientOrganization::query()->findOrFail($organization->id)->is_active);

                return new CommandResult('client_unit', $this->rows->unit($context, $organization->id, $name, $address)->id);
            });
    }

    /** @param  array<string, string|null>  $fields */
    public function address(string $commandId, int $actorUserId, ClientUnit $unit, array $fields): Outcome
    {
        return $this->underUnit($commandId, self::ADDRESS, $actorUserId, $unit, $fields, fn (CommandContext $context): CommandResult => new CommandResult('client_address', $this->rows->address($context, $unit->id, $fields)->id));
    }

    /** @param  array<string, string|null>  $fields */
    public function contact(string $commandId, int $actorUserId, ClientUnit $unit, array $fields): Outcome
    {
        return $this->underUnit($commandId, self::CONTACT, $actorUserId, $unit, $fields, fn (CommandContext $context): CommandResult => new CommandResult('client_contact', $this->rows->contact($context, $unit->id, $fields)->id));
    }

    /**
     * @param  array<string, string|null>  $fields
     * @param  \Closure(CommandContext): CommandResult  $add
     */
    private function underUnit(string $commandId, string $type, int $actorUserId, ClientUnit $unit, array $fields, \Closure $add): Outcome
    {
        return $this->commands->run($commandId, $type, $actorUserId, ['unit' => $unit->id, ...$fields], function (CommandContext $context) use ($unit, $add): CommandResult {
            $context->lock((new LockSet)->add('client_organizations', ['id' => $unit->client_organization_id])->add('client_units', ['id' => $unit->id]));
            self::active(ClientOrganization::query()->findOrFail($unit->client_organization_id)->is_active && ClientUnit::query()->findOrFail($unit->id)->is_active);

            return $add($context);
        });
    }

    private static function active(bool $active): void
    {
        if (! $active) {
            throw Refused::rejected('WF-MD-02', ['reason' => 'archived']);
        }
    }
}
