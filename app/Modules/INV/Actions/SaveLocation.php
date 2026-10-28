<?php

namespace App\Modules\INV\Actions;

use App\Modules\CAT\Services\ProductMinimum;
use App\Modules\INV\Models\Location;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * `INV SaveLocation · SetMinimumStock` (ROUTE-148; INT-148; NX-01; WF-MD-05; J-MD-05): a rack of the shared warehouse —
 * a pool record of no company (CS-05) — created with its code, unique and fixed once made, or its name and active state
 * edited at the `lock_version` the page read (ST-01); and a product's minimum stock set through CAT's write service
 * (ARCHITECTURE §3), advice only (BR-INV-10). `warehouse.manage`, whose holder has a company in scope (CS-05); the rows at
 * LK-08 — catalogue, then locations —; a code another rack holds answers CONFLICT at its field, as the lost race on its
 * unique key does (BD-16; SQ-08). Each change a business audit event with no company (LG-01; PJ-11).
 */
final class SaveLocation
{
    public const CREATE = 'INV.CreateLocation';

    public const EDIT = 'INV.EditLocation';

    public const MINIMUM = 'INV.SetMinimumStock';

    public const CAPABILITY = 'warehouse.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
        private readonly ProductMinimum $minimum,
    ) {}

    public function create(string $commandId, int $actorUserId, string $code, string $name): Outcome
    {
        return $this->run($commandId, self::CREATE, $actorUserId, ['code' => $code, 'name' => $name], function (CommandContext $context) use ($code, $name): CommandResult {
            $context->lock(new LockSet);

            if (Location::query()->where('code', $code)->exists()) {
                throw Refused::conflict('SQ-08', ['field' => 'code']);
            }

            $location = new Location;
            $location->forceFill(['code' => $code, 'name' => $name, 'command_id' => $context->request->commandId, 'command_line_no' => 1,
                'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId])->save();
            $this->record($context, 'INV.LocationCreated', $location->id, [], ['code' => $code, 'name' => $name]);

            return new CommandResult('location', $location->id);
        });
    }

    public function edit(string $commandId, int $actorUserId, Location $location, int $lockVersion, string $name, bool $active): Outcome
    {
        return $this->run($commandId, self::EDIT, $actorUserId, ['code' => $location->code, 'lock_version' => $lockVersion, 'name' => $name, 'active' => $active],
            function (CommandContext $context) use ($location, $lockVersion, $name, $active): CommandResult {
                $context->lock((new LockSet)->add('locations', ['id' => $location->id]));
                $row = Location::query()->findOrFail($location->id);

                if ($row->lock_version !== $lockVersion) {
                    throw Refused::conflict('ST-01');
                }

                $values = ['name' => $name, ...($active === $row->is_active ? [] : ($active
                    ? ['is_active' => true, 'deactivated_at' => null, 'deactivated_by' => null, 'deactivated_reason' => null]
                    : ['is_active' => false, 'deactivated_at' => $context->now(), 'deactivated_by' => $context->request->actorUserId]))];
                $before = ['name' => $row->name, 'is_active' => $row->is_active];
                $after = ['name' => $name, 'is_active' => $active];

                if ($before !== $after) {
                    $changed = DB::table('locations')->where('id', $row->id)->where('lock_version', $lockVersion)
                        ->update([...$values, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

                    if ($changed !== 1) {
                        throw Refused::conflict('ST-01');
                    }

                    $this->record($context, 'INV.LocationUpdated', $row->id, $before, $after);
                }

                return new CommandResult('location', $row->id);
            });
    }

    /** @param  ?int  $lockVersion  the version the page read, or null for a product chosen without its minimum shown */
    public function setMinimum(string $commandId, int $actorUserId, int $productId, string $productPublicId, ?int $lockVersion, ?string $minimum): Outcome
    {
        return $this->run($commandId, self::MINIMUM, $actorUserId, ['product' => $productPublicId, 'lock_version' => $lockVersion, 'minimum' => $minimum],
            function (CommandContext $context) use ($productId, $lockVersion, $minimum): CommandResult {
                $context->lock(ProductMinimum::locks($productId));
                $this->minimum->set($context, $productId, $lockVersion, $minimum);

                return new CommandResult('product', $productId);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(CommandContext): CommandResult  $action
     */
    private function run(string $commandId, string $type, int $actorUserId, array $payload, Closure $action): Outcome
    {
        return $this->envelope->run(new CommandRequest($commandId, $type, $actorUserId, null, self::CAPABILITY, Fingerprint::of($type, null, $payload)), $action);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function record(CommandContext $context, string $action, int $id, array $before, array $after): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'location',
            entityId: $id,
            commandId: $context->request->commandId,
            change: $before === [] ? AuditChange::created($after) : AuditChange::updated($before, $after),
        );
    }
}
