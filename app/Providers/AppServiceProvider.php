<?php

namespace App\Providers;

use App\Modules\IAM\Policies\CapabilityGates;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Services\NumberingGoLive;
use App\Modules\ORG\Services\UnrecordedGoLive;
use App\Support\Localization\Locales;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Memoized for one request or job only, never across them (PERMISSIONS_MATRIX AZ-03; SECURITY EN-02).
        $this->app->scoped(EffectiveCapabilities::class);

        // Go-live is not recorded until TASK-077 binds the store P11Q-04 defines (NM-03; DIR-085 OF-04; TASK-021).
        $this->app->bindIf(NumberingGoLive::class, UnrecordedGoLive::class);
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The framework's strict-model checks outside production (SECURITY WS-01; PERFORMANCE PF-02):
        // a lazy-loaded relation or a silently discarded attribute fails, so a missed eager load or
        // an unlisted field is caught by the tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        CapabilityGates::register();

        // WS-12's search limiter: 60 searches a minute per account, separate from the scanner's burst-sized one
        // (TM-43); its answer is MSG-20 with the minutes left, read by the field that asked (ROUTE-281).
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('search:'.self::scalar($request->user()?->getAuthIdentifier()))
            ->response(function (Request $request, array $headers): JsonResponse {
                $retry = $headers['Retry-After'] ?? 60;
                $minutes = max(1, (int) ceil((is_numeric($retry) ? (int) $retry : 60) / 60));

                return response()->json(['message' => trans_choice('messages.msg_20', $minutes, ['count' => $minutes])], 429, $headers);
            }));

        // LOCALIZATION §3.6: a catalogue key missing in a language is shown in its canonical Indonesian text,
        // never as itself, and logged; TL-10 keeps the catalogue complete (TASK-014).
        Lang::handleMissingKeysUsing(function (string $key, array $replace, ?string $locale): string {
            if ($locale === Locales::DEFAULT || ! Lang::hasForLocale($key, Locales::DEFAULT)) {
                return $key;
            }

            Log::warning('A string is missing in a language; its Indonesian text is shown (LOCALIZATION §3.6).', ['key' => $key, 'locale' => $locale]);

            return (string) Lang::get($key, [], Locales::DEFAULT);
        });
    }
}
