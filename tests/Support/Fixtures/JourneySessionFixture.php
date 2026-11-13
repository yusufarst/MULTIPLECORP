<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\Authorization;
use Throwable;

/**
 * TL-09 (TASK-064; CJ-10, CJ-18, CJ-21): the session and command-identity preconditions and readings the module fixtures do not hold,
 * for synthetic accounts and companies of one run — never a step a journey walks, never an answer the application gives in its place.
 *
 * - `grant`: a run's fresh account given one company and a fixed set of capabilities (SECURITY_TEST_MATRIX §2), at most one active
 *   grant of each, optionally a display name of its own.
 * - `session`: the account's session rows read, aged — the idle limit passed while a page stayed open (AU-07; UXS-03, UXS-04) —
 *   or their last activity reported, so that a journey proves which requests extend the session (RB-05; RV-06).
 * - `commands`: the `command_log` rows of the identifiers a page sent — the recorded outcome each attempt is asserted by (CI-05; M-10).
 * - `hold` and `holding`: a project row held FOR UPDATE past the interactive lock timeout, so a command meets SQ-14's lock-wait
 *   FAILED (UXS-07), and whether that hold is in place.
 * - `google`: what an unknown Google identity left behind — nothing (AU-22).
 * - `day`: the server's business day and the instant 30 seconds before the WIB midnight that began it (FX-08).
 * - `apply`: FX-08's controlled clock for one request — the instant a request names in `X-Fixture-Clock`, at most three days back, never
 *   ahead —, called as the fixture routes load, so only the request that asks for it is moved. Local only (routes/fixtures.php).
 */
final class JourneySessionFixture
{
    public const CLOCK = 'X-Fixture-Clock';

    /** The capabilities a journey's Admin holds, by profile: viewing projects, or the quotation's cycle with its money. */
    private const PROFILES = [
        'view' => ['projects.view'],
        'quotation' => ['projects.view', 'quotation.manage', 'finance.view', 'projects.confirm'],
    ];

    /** Called as the fixture routes load: the request's own instant, when it names one. */
    public static function apply(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $named = request()->header(self::CLOCK);

        if (! is_string($named) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $named) !== 1) {
            return;
        }

        try {
            $instant = CarbonImmutable::parse($named);
        } catch (Throwable) {
            return;
        }

        $now = CarbonImmutable::now();

        if ($instant->greaterThan($now) || $instant->lessThan($now->subDays(3))) {
            return;
        }

        Carbon::setTestNow($instant);
        CarbonImmutable::setTestNow($instant);
    }

    public static function grant(Request $request): JsonResponse
    {
        $user = User::query()->where('public_id', (string) $request->input('account'))->first();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->first();
        $profile = self::PROFILES[(string) $request->input('profile')] ?? null;

        if (! $user instanceof User || ! $company instanceof Company || $profile === null) {
            return response()->json(['error' => 'an account, a company and a profile (view, quotation) are required'], 422);
        }

        if (! DB::table('user_company_grants')->where('user_id', $user->id)->where('company_id', $company->id)->whereNull('revoked_at')->exists()) {
            Authorization::grantCompany($user, $company->id);
        }

        foreach ($profile as $capability) {
            $held = DB::table('user_capability_grants')
                ->where('user_id', $user->id)
                ->where('capability_id', DB::table('capabilities')->where('code', $capability)->value('id'))
                ->whereNull('revoked_at')
                ->exists();

            if (! $held) {
                Authorization::grant($user, $capability);
            }
        }

        $name = $request->input('name');

        if (is_string($name) && preg_match('/^[A-Za-z0-9 -]{3,60}$/', $name) === 1) {
            $user->forceFill(['name' => $name])->save();
        }

        return response()->json(['ok' => true]);
    }

    public static function session(Request $request): JsonResponse
    {
        $user = User::query()->where('public_id', (string) $request->input('account'))->first();

        if (! $user instanceof User) {
            return response()->json(['error' => 'unknown account'], 422);
        }

        $rows = DB::table('sessions')->where('user_id', $user->id);

        if ($request->input('action') === 'age') {
            $seconds = max(60, min(7200, $request->integer('seconds', 3700)));
            (clone $rows)->update(['last_activity' => DB::raw('last_activity - '.$seconds)]);
        }

        $activity = (clone $rows)->max('last_activity');

        return response()->json(['sessions' => (clone $rows)->count(), 'last_activity' => is_numeric($activity) ? (int) $activity : null]);
    }

    public static function commands(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(explode(',', (string) $request->query('ids')), fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1));
        $rows = $ids === [] ? [] : DB::table('command_log')
            ->leftJoin('users', 'users.id', '=', 'command_log.actor_user_id')
            ->whereIn('command_log.command_id', $ids)
            ->get(['command_log.command_id', 'command_log.command_type', 'command_log.state', 'command_log.outcome_code', 'users.public_id as actor'])
            ->map(fn (stdClass $row): array => (array) $row)
            ->all();

        return response()->json(['commands' => $rows]);
    }

    public static function hold(Request $request): JsonResponse
    {
        $project = Project::query()->where('public_id', (string) $request->input('project'))->first();

        if (! $project instanceof Project) {
            return response()->json(['error' => 'unknown project'], 422);
        }

        $seconds = max(4, min(20, $request->integer('seconds', 12)));
        DB::transaction(function () use ($project, $seconds): void {
            DB::select('select set_config(\'application_name\', ?, true)', [self::holder($project->public_id)]);
            DB::select('select id from projects where id = ? for update', [$project->id]);
            DB::select('select pg_sleep(?)', [$seconds]);
        });

        return response()->json(['held' => $seconds]);
    }

    public static function holding(Request $request): JsonResponse
    {
        $project = (string) $request->query('project');

        if (preg_match('/^[0-9a-f-]{36}$/', $project) !== 1) {
            return response()->json(['error' => 'a project identifier is required'], 422);
        }

        $held = DB::selectOne('select exists(select 1 from pg_stat_activity where application_name = ? and state = \'active\' and query ilike \'%pg_sleep%\') as held', [self::holder($project)]);

        return response()->json(['held' => $held instanceof stdClass && ($held->held ?? false) === true]);
    }

    public static function google(Request $request): JsonResponse
    {
        $subject = (string) $request->query('sub');

        return response()->json([
            'identities' => DB::table('user_external_identities')->where('provider', 'google')->where('provider_subject', $subject)->count(),
            // The faked token's e-mail claim (GoogleFixture): an account created from it would carry it.
            'accounts' => DB::table('users')->where('email', 'akun@contoh.test')->count(),
        ]);
    }

    public static function day(): JsonResponse
    {
        $today = BusinessDate::today();
        $midnight = CarbonImmutable::parse("{$today} 00:00:00", BusinessDate::TIMEZONE);

        return response()->json([
            'today' => $today,
            'yesterday' => $midnight->subDay()->toDateString(),
            'before_midnight' => $midnight->subSeconds(30)->utc()->toIso8601ZuluString(),
        ]);
    }

    private static function holder(string $project): string
    {
        return 'k2-hold-'.$project;
    }
}
