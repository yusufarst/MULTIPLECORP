<?php

namespace App\Providers;

use App\Modules\DOC\Contracts\DocumentRenderer;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Queries\RenditionStatus;
use App\Modules\DOC\Services\UnwrittenTemplates;
use App\Modules\DOC\Services\WeasyPrintRenderer;
use App\Modules\IAM\Policies\CapabilityGates;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Services\NumberingGoLive;
use App\Modules\ORG\Services\UnrecordedGoLive;
use App\Support\Localization\Locales;
use App\Support\Status\StatusRead;
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

        // FL-08's renderer (DIR-099 C1) and the layouts' templates, which TASK-029 binds (TASK-022).
        $this->app->bindIf(DocumentRenderer::class, WeasyPrintRenderer::class);
        $this->app->bindIf(RenditionTemplate::class, UnwrittenTemplates::class);

        // ROUTE-285's kinds; the export and import kinds join with TASK-060 and TASK-057 (TASK-022).
        $this->app->tag([RenditionStatus::class], StatusRead::TAG);
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

        // WS-12's password-change limiter: 5 per 15 minutes per account (ROUTE-111; AU-13), its answer MSG-20 at the field.
        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinutes(15, 5)
            ->by('password-change:'.self::scalar($request->user()?->getAuthIdentifier()))
            ->response(function (Request $request, array $headers) {
                $retry = $headers['Retry-After'] ?? 900;
                $minutes = max(1, (int) ceil((is_numeric($retry) ? (int) $retry : 900) / 60));

                return back(303)->withErrors(['current_password' => trans_choice('messages.msg_20', $minutes, ['count' => $minutes])]);
            }));

        // WS-12's search limiter: 60 searches a minute per account, separate from the scanner's burst-sized one
        // (TM-43); its answer is MSG-20 with the minutes left, read by the field that asked (ROUTE-281).
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('search:'.self::scalar($request->user()?->getAuthIdentifier()))
            ->response(function (Request $request, array $headers): JsonResponse {
                $retry = $headers['Retry-After'] ?? 60;
                $minutes = max(1, (int) ceil((is_numeric($retry) ? (int) $retry : 60) / 60));

                return response()->json(['message' => trans_choice('messages.msg_20', $minutes, ['count' => $minutes])], 429, $headers);
            }));

        // WS-12's upload limiter: 60 uploads an hour per account in front of the durable count and byte budget of H6-07
        // (ROUTE-228); its answer is MSG-20 with the minutes left, read by the upload field.
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perHour(60)
            ->by('uploads:'.self::scalar($request->user()?->getAuthIdentifier()))
            ->response(function (Request $request, array $headers): JsonResponse {
                $retry = $headers['Retry-After'] ?? 3600;
                $minutes = max(1, (int) ceil((is_numeric($retry) ? (int) $retry : 3600) / 60));

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
