#!/bin/sh
# TEST_STRATEGY TL-13: the PHP autoloader and the framework's caches in production mode,
# then the production configuration check — debug off, no test route, no development
# dependency. It removes the development packages: run it last, in the app container.
set -eu

composer install --no-dev --no-interaction --prefer-dist --no-progress --classmap-authoritative

# The production environment of the placeholders, APP_DEBUG left to its default.
sed -e 's/^APP_ENV=.*/APP_ENV=production/' -e '/^APP_DEBUG=/d' .env > .env.production
trap 'rm -f .env.production; php artisan config:clear -q; php artisan route:clear -q; php artisan view:clear -q; php artisan event:clear -q' EXIT

APP_ENV=production php artisan optimize
APP_ENV=production php scripts/ci/production-check.php
