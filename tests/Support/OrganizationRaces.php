<?php

namespace Tests\Support;

use App\Modules\ORG\Actions\DeactivateCompany;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\CompanyGuard;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-025's race steps (BR-ACC-03; C-54; LK-06): autoloaded, so the sessions' own processes reach them. A command
 * starting new business stands in for a project's, an invoice's or a purchase's (TASK-032, TASK-049, TASK-037) — it
 * asks `CompanyGuard` under the company row —; a correction on an existing record stands in for those that continue.
 */
final class OrganizationRaces
{
    public static function newBusiness(int $actorId, int $companyId): string
    {
        return self::word(self::command($actorId, $companyId, 'TEST.NewBusiness', function (CommandContext $context) use ($companyId): CommandResult {
            $context->lock(CompanyGuard::locks($companyId));
            app(CompanyGuard::class)->ensureOpenForNewBusiness($context, $companyId);

            return new CommandResult;
        }));
    }

    /** New business that holds the company row until the deactivation waits on it. */
    public static function holdThenNewBusiness(int $actorId, int $companyId): string
    {
        return self::word(self::command($actorId, $companyId, 'TEST.NewBusiness', function (CommandContext $context) use ($companyId): CommandResult {
            $context->lock(CompanyGuard::locks($companyId));
            Barrier::await('the new business holds the company row');
            Sessions::awaitBlocked();
            app(CompanyGuard::class)->ensureOpenForNewBusiness($context, $companyId);

            return new CommandResult;
        }));
    }

    public static function existingRecordCorrection(int $actorId, int $companyId): string
    {
        return self::word(self::command($actorId, $companyId, 'TEST.Correction', function (CommandContext $context): CommandResult {
            $context->lock(new LockSet);

            return new CommandResult;
        }));
    }

    public static function deactivate(int $actorId, int $companyId): string
    {
        $company = Company::query()->findOrFail($companyId);

        return self::word(app(DeactivateCompany::class)->run((string) Str::uuid(), $actorId, $company, $company->lock_version, 'Uji balapan'));
    }

    /** The deactivation in a transaction that holds the company row first, until the new business waits on it. */
    public static function holdThenDeactivate(int $actorId, int $companyId): string
    {
        return DB::transaction(function () use ($actorId, $companyId): string {
            DB::select('select id from companies where id = ? for no key update', [$companyId]);
            Barrier::await('the deactivation holds the company row');
            Sessions::awaitBlocked();

            return self::deactivate($actorId, $companyId);
        });
    }

    private static function command(int $actorId, int $companyId, string $type, Closure $action): Outcome
    {
        $commandId = (string) Str::uuid();

        return app(CommandEnvelope::class)->run(new CommandRequest($commandId, $type, $actorId, $companyId, 'projects.manage', Fingerprint::of($type, null, [$commandId])), $action);
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code));
    }
}
