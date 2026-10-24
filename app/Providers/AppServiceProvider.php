<?php

namespace App\Providers;

use App\Modules\IAM\Policies\CapabilityGates;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Database\Eloquent\Model;
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
    }
}
