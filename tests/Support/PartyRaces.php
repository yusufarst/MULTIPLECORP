<?php

namespace Tests\Support;

use App\Modules\PTY\Actions\AddClientUnit;
use App\Modules\PTY\Models\ClientOrganization;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-026's race steps (BD-16; LK-08): autoloaded, so the sessions' own processes reach them — two creations of one
 * unit name in one organization.
 */
final class PartyRaces
{
    public static function addUnit(int $actorId, int $organizationId, string $name): string
    {
        return self::word(app(AddClientUnit::class)->unit((string) Str::uuid(), $actorId, ClientOrganization::query()->findOrFail($organizationId), $name, null));
    }

    /** The creation in a transaction that holds the organization row first, until the other creation waits on it. */
    public static function holdThenAddUnit(int $actorId, int $organizationId, string $name): string
    {
        return DB::transaction(function () use ($actorId, $organizationId, $name): string {
            DB::select('select id from client_organizations where id = ? for no key update', [$organizationId]);
            Barrier::await('the first creation holds the organization row');
            Sessions::awaitBlocked();

            return self::addUnit($actorId, $organizationId, $name);
        });
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code));
    }
}
