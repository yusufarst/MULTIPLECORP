<?php

namespace Tests\Support;

use App\Modules\OPS\Actions\CloseCorrectionCase;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Services\CorrectionCases;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/** The correction case's closure and a posting as the integration races run them, each answering its outcome as a word (TASK-031). */
final class CaseRaces
{
    public static function close(int $actorId, int $caseId): string
    {
        $case = CorrectionCase::query()->findOrFail($caseId);

        return self::word(app(CloseCorrectionCase::class)->run((string) Str::uuid(), $actorId, $case, $case->lock_version, 'Kasus selesai ditinjau.'));
    }

    /** A posting service's counters in its own correcting command (as a module's Task will post them). */
    public static function post(int $actorId, int $caseId, array $deltas): string
    {
        $company = (int) DB::table('correction_cases')->where('id', $caseId)->value('company_id');
        $request = new CommandRequest((string) Str::uuid(), 'test.post', $actorId, $company, 'projects.view', Fingerprint::of('test.post', null, ['case' => $caseId, 'deltas' => $deltas]));

        return self::word(app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($caseId, $deltas): CommandResult {
            $context->lock(CorrectionCases::locks($caseId));
            app(CorrectionCases::class)->post($context, $caseId, $deltas, 'Pengembalian dana pemasok tercatat.');

            return new CommandResult('correction_case', $caseId);
        }));
    }

    /** The posting committed only once the other session waits on the project or case it holds. */
    public static function holdThenPost(int $actorId, int $caseId, array $deltas): string
    {
        return DB::transaction(function () use ($actorId, $caseId, $deltas): string {
            $word = self::post($actorId, $caseId, $deltas);
            Barrier::await('the posting holds the case');
            Sessions::awaitBlocked();

            return $word;
        });
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
