#!/bin/sh
# MULTIPLECORP's production entrypoint (TASK-072; SECURITY SX-03; INFRASTRUCTURE §4.2): the framework's configuration, routes,
# events and views cached from this container's own environment — the values Coolify gives it — before its process starts,
# then the process itself through the PHP image's own entrypoint. Each container caches for itself, in its own layer.
set -eu

php artisan optimize --no-interaction > /dev/null

exec docker-php-entrypoint "$@"
