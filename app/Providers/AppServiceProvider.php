<?php

namespace App\Providers;

use App\Modules\IAM\Policies\CapabilityGates;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Localization\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
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
