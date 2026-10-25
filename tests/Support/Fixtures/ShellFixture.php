<?php

namespace Tests\Support\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shell fixture's stub of the server (TASK-016): personas by their capability hints (INFORMATION_ARCHITECTURE
 * §5.1), and a command recorded once per `command_id` — a second attempt with the same identifier answers the
 * first one's outcome, as the command log does (CI-01–CI-09).
 */
final class ShellFixture
{
    /** The stub sign-in's test password — a fixture value, no account's. */
    public const PASSWORD = 'sandi-contoh-fixture';

    private const PERSONAS = [
        'owner' => ['name' => 'Laras', 'capabilities' => ['review.view', 'scope.all_companies', 'projects.view', 'projects.manage', 'purchase.manage', 'stock.view', 'stock.receive', 'finance.view', 'payment.record', 'cost.view', 'evidence.upload', 'audit.view', 'users.manage', 'reports.consolidated']],
        'admin' => ['name' => 'Dimas', 'capabilities' => ['projects.view', 'projects.manage', 'purchase.manage', 'finance.view', 'payment.record', 'cost.view', 'evidence.upload']],
        'gudang' => ['name' => 'Bayu', 'capabilities' => ['stock.view', 'stock.receive', 'stock.dispatch', 'evidence.upload']],
    ];

    private const COMPANIES = [
        ['id' => '01a10000-0000-7000-8000-000000000001', 'code' => 'ARJ', 'name' => 'PT Arjuna Sarana'],
        ['id' => '01a10000-0000-7000-8000-000000000002', 'code' => 'BTN', 'name' => 'PT Bentara Niaga'],
        ['id' => '01a10000-0000-7000-8000-000000000003', 'code' => 'CKP', 'name' => 'CV Cakrawala Pratama'],
    ];

    /** @return array<string, mixed> */
    public static function props(Request $request): array
    {
        $run = self::run($request);
        // The session's account: the address's persona, or whoever signed in through the dialog since (UXH-02).
        $key = Cache::get("shell-fixture:{$run}:persona", $request->query('persona'));
        $persona = self::PERSONAS[$key] ?? self::PERSONAS['owner'];

        return [
            'auth' => [...$persona, 'companies' => $key === 'gudang' ? [self::COMPANIES[0]] : self::COMPANIES],
            'run' => $run,
            'outcome' => Cache::get("shell-fixture:{$run}:outcome"),
        ];
    }

    public static function expire(Request $request): Response
    {
        Cache::put('shell-fixture:'.self::run($request).':expired', true, 600);

        return response()->noContent();
    }

    public static function signIn(Request $request): Response
    {
        if ($request->input('password') !== self::PASSWORD) {
            return response()->json(['message' => __('messages.msg_19')], 422);
        }

        Cache::forget('shell-fixture:'.self::run($request).':expired');

        foreach (self::PERSONAS as $key => $persona) {
            if ($request->input('email') === mb_strtolower($persona['name']).'@contoh.test') {
                Cache::put('shell-fixture:'.self::run($request).':persona', $key, 600);
            }
        }

        return response()->noContent();
    }

    public static function confirm(Request $request): Response
    {
        if ($request->input('password') !== self::PASSWORD) {
            return response()->json(['message' => __('messages.msg_19')], 422);
        }

        Cache::put('shell-fixture:'.self::run($request).':confirmed', true, 600);

        return response()->noContent();
    }

    public static function command(Request $request, bool $stepUp): Response
    {
        $run = self::run($request);

        if (Cache::get("shell-fixture:{$run}:expired") === true) {
            return response()->json(['message' => __('messages.msg_11')], 401);
        }

        if ($stepUp && Cache::get("shell-fixture:{$run}:confirmed") !== true) {
            return response()->json(['message' => __('messages.msg_27')], 423);
        }

        $commandId = (string) $request->input('command_id');
        $seen = (array) Cache::get("shell-fixture:{$run}:commands", []);
        $replayed = in_array($commandId, $seen, true);

        if (! $replayed) {
            $seen[] = $commandId;
            Cache::put("shell-fixture:{$run}:commands", $seen, 600);
        }

        Cache::put("shell-fixture:{$run}:outcome", ['command_id' => $commandId, 'replayed' => $replayed, 'executed' => count($seen)], 600);

        return redirect('/__fixtures/shell?'.http_build_query(['persona' => $request->query('persona', 'owner'), 'run' => $run]), 303);
    }

    private static function run(Request $request): string
    {
        $run = (string) $request->query('run', 'default');

        return preg_match('/^[a-z0-9-]{1,40}$/', $run) === 1 ? $run : 'default';
    }
}
