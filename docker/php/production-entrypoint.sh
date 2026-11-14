#!/bin/sh
# MULTIPLECORP's production entrypoint (TASK-072; SECURITY SX-03; INFRASTRUCTURE §4.2): the framework's configuration, routes
# and events cached from this container's own environment — the values Coolify gives it — before its process starts, then
# the process itself through the PHP image's own entrypoint. Each container caches for itself, in its own layer. The views
# are left to compile on first use: their cache is the shared `storage` volume, which clearing at every container start —
# each worker restarts hourly — would empty under the web processes still serving from it.
set -eu

php artisan optimize --except=views --no-interaction > /dev/null

exec docker-php-entrypoint "$@"
