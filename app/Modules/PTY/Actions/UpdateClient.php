<?php

namespace App\Modules\PTY\Actions;

use App\Modules\PTY\Data\PartyFields;
use App\Modules\PTY\Services\ClientRows;
use App\Modules\PTY\Services\PartyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `PTY UpdateClient` (ROUTE-142; INT-142; NX-01; BR-SN-01; C-11): an organization's, a unit's, an address's or a PIC's
 * own fields, the row — under its organization — at LK-08 and changed at the `lock_version` the form read (ST-01).
 * A master answers "true now": an issued quotation, document or committed record keeps the values it was issued
 * with, its own snapshot (DATABASE §14), so a change here reaches later drafts only. A unit renamed to a name its
 * organization already holds answers CONFLICT (BD-16). An archived row is not edited.
 */
final class UpdateClient
{
    public const TYPE = 'PTY.UpdateClient';

    public function __construct(
        private readonly PartyCommands $commands,
        private readonly ClientRows $rows,
    ) {}

    /**
     * @param  'organization'|'unit'|'address'|'contact'  $kind
     * @param  array<string, string|null>  $fields
     */
    public function run(string $commandId, int $actorUserId, string $kind, int $organizationId, int $rowId, int $lockVersion, array $fields): Outcome
    {
        $table = PartyFields::TABLES[$kind];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['kind' => $kind, 'row' => $rowId, 'lock_version' => $lockVersion, ...$fields],
            function (CommandContext $context) use ($kind, $table, $organizationId, $rowId, $lockVersion, $fields): CommandResult {
                $context->lock((new LockSet)->add('client_organizations', ['id' => $organizationId])->add($table, ['id' => $rowId]));
                /** @var array<string, mixed> $current */
                $current = (array) (DB::table($table)->where('id', $rowId)->first() ?? throw Refused::rejected('WF-MD-02', ['reason' => 'missing']));

                if ($current['is_active'] !== true) {
                    throw Refused::rejected('WF-MD-02', ['reason' => 'archived']);
                }

                if ($kind === 'unit' && is_string($fields['name'] ?? null)) {
                    $this->rows->freeUnitName($organizationId, $fields['name'], $rowId);
                }

                [$before, $after] = $this->commands->changes($current, $fields);

                if ($current['lock_version'] !== $lockVersion) {
                    throw Refused::conflict('ST-01');
                }

                if ($after !== []) {
                    $this->commands->update($context, $table, $rowId, $lockVersion, $after);
                    $publicId = $current['public_id'] ?? null;
                    $this->commands->record($context, 'PTY.ClientUpdated', 'client_'.$kind, $rowId, is_string($publicId) ? $publicId : null, $before, $after);
                }

                return new CommandResult('client_'.$kind, $rowId);
            });
    }
}
