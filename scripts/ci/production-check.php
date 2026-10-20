<?php

// The production configuration check of TEST_STRATEGY TL-13 and SECURITY_TEST_MATRIX SM-08,
// run by production-check.sh after the production caches are built.

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];

if (! $app->environment('production')) {
    $failures[] = 'APP_ENV is not production.';
}

if (config('app.debug') !== false) {
    $failures[] = 'APP_DEBUG is not false.';
}

if (! $app->configurationIsCached()) {
    $failures[] = 'The configuration is not cached.';
}

foreach ($app['router']->getRoutes() as $route) {
    if (str_starts_with($route->uri(), '__test')) {
        $failures[] = "A test route is registered: {$route->uri()}.";
    }
}

$installed = require __DIR__.'/../../vendor/composer/installed.php';

if ($installed['root']['dev'] !== false) {
    $failures[] = 'The development dependencies are installed.';
}

foreach ($installed['versions'] as $name => $package) {
    if (($package['dev_requirement'] ?? false) && isset($package['install_path']) && is_dir($package['install_path'])) {
        $failures[] = "A development dependency is installed: {$name}.";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Production configuration check passed: debug off, configuration cached, no test route, no development dependency.'.PHP_EOL);
