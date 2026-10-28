<?php

namespace Tests\Support;

use App\Modules\INV\Actions\SaveLocation;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-028's race steps (BD-16; LK-08): autoloaded, so the sessions' own processes reach them — two creations of one
 * rack code.
 */
final class LocationRaces
{
    public static function create(int $actorId, string $code): string
    {
        return self::word(app(SaveLocation::class)->create((string) Str::uuid(), $actorId, $code, "Rak {$code}"));
    }

    /** The creation committed only once the other session waits on the code it wrote. */
    public static function holdThenCreate(int $actorId, string $code): string
    {
        return DB::transaction(function () use ($actorId, $code): string {
            $word = self::create($actorId, $code);
            Barrier::await('the first rack is written');
            Sessions::awaitBlocked();

            return $word;
        });
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code));
    }
}
