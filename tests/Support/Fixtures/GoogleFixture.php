<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Authentication\GoogleSignIn;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The faked Google of the local stack's browser suite (TASK-019; SM-20 — no test calls Google): while a run has armed
 * it, Google sign-in is on with a synthetic client, and the token exchange is answered here with an ID token for the
 * run's subject and the attempt's nonce, which the browser test reads from the authorization address it intercepts.
 * Local only (routes/fixtures.php); no real client, token or account.
 */
final class GoogleFixture
{
    private const KEY = 'fixture-google';

    /** Called as the fixture routes load: arms the fake for the requests of an armed run. */
    public static function apply(): void
    {
        // Routes also load in the console — package discovery among them, where no store may be up yet.
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $armed = Cache::get(self::KEY);
        } catch (\Throwable) {
            return;
        }

        if (! is_array($armed)) {
            return;
        }

        config(['services.google.client_id' => 'klien-fixture', 'services.google.client_secret' => 'rahasia-fixture']);
        app()->bind(GoogleSignIn::HTTP_CLIENT, fn (): Client => new Client(['handler' => HandlerStack::create(new MockHandler([
            function () use ($armed): Response {
                $current = Cache::get(self::KEY);
                $claims = ['iss' => 'https://accounts.google.com', 'aud' => 'klien-fixture', 'sub' => $armed['sub'], 'email' => 'akun@contoh.test',
                    'nonce' => is_array($current) ? ($current['nonce'] ?? '') : '', 'exp' => now()->getTimestamp() + 600];
                $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

                return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'akses-fixture', 'id_token' => $encode(['alg' => 'RS256']).'.'.$encode($claims).'.tanda']));
            },
        ]))]));
    }

    /** Arms the fake for a subject (`sub`), or records the attempt's nonce (`nonce`); `off` disarms it. */
    public static function arm(Request $request): JsonResponse
    {
        if ($request->boolean('off')) {
            Cache::forget(self::KEY);

            return response()->json(['armed' => false]);
        }

        $current = Cache::get(self::KEY);
        $armed = is_array($current) ? $current : [];

        foreach (['sub', 'nonce'] as $key) {
            if (is_string($request->input($key))) {
                $armed[$key] = $request->input($key);
            }
        }

        Cache::put(self::KEY, $armed, 600);

        return response()->json(['armed' => true]);
    }
}
