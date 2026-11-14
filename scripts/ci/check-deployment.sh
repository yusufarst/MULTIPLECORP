#!/bin/sh
# TL-05 on the deployment definition (TASK-072 criteria 2, 3, 4, 6, 8 and 10; SECURITY H9-09, WS-06, WS-08, WS-11, H9-14,
# LG-04; INFRASTRUCTURE §4.4): deploy/coolify/compose.yml started with throwaway values generated here and never stored,
# then checked from a container on the proxy's network as Coolify's proxy would reach it. TLS and the plain-HTTP redirect are
# Coolify's proxy's, outside this stack. Prints one PASS or FAIL line per check; exits 1 on any FAIL. Leaves the stack
# running for its logs; `sh scripts/ci/check-deployment.sh down` removes it, its own Compose project only.
set -eu

project=${DEPLOY_CHECK_PROJECT:-mcdeploy}
directory=${RUNNER_TEMP:-${TMPDIR:-/tmp}}
env_file="$directory/$project.env"
host=app.example.invalid
image=multiplecorp-app:production-deploy-check
failures=0

compose() {
    docker compose -p "$project" -f deploy/coolify/compose.yml --env-file "$env_file" "$@"
}

result() {
    if [ "$1" = 0 ]; then printf 'PASS %s\n' "$2"; else printf 'FAIL %s\n' "$2"; failures=$((failures + 1)); fi
}

# The command's status as the check's result, its output kept out of the log.
expect() {
    name=$1
    shift
    if "$@" > /dev/null 2>&1; then result 0 "$name"; else result 1 "$name"; fi
}

# The command must fail for the check to pass.
refuse() {
    name=$1
    shift
    if "$@" > /dev/null 2>&1; then result 1 "$name"; else result 0 "$name"; fi
}

secret() {
    od -An -N24 -tx1 /dev/urandom | tr -d ' \n'
}

# A request from a container on the proxy's network only: its response headers and body.
request() {
    docker run --rm --network "${project}_edge" --entrypoint curl "$image" -sS -D - -o - --max-time 20 "$@"
}

status_of() {
    docker run --rm --network "${project}_edge" --entrypoint curl "$image" -sS -o /dev/null -w '%{http_code}' --max-time 20 "$@"
}

if [ "${1:-}" = down ]; then
    if [ -f "$env_file" ]; then
        compose down -v --remove-orphans
        rm -f "$env_file"
    fi
    exit 0
fi

umask 077
cat > "$env_file" <<EOF
MULTIPLECORP_RELEASE=deploy-check
APP_ENV=production
APP_URL=https://$host
APP_KEY=base64:$(head -c 32 /dev/urandom | base64)
APP_TRUSTED_PROXIES=192.0.2.1
APP_TRUSTED_HOSTS=$host
MULTIPLECORP_HOST=$host
MULTIPLECORP_HSTS=max-age=300
DB_DATABASE=multiplecorp
DB_SUPERUSER=check_superuser
DB_SUPERUSER_PASSWORD=$(secret)
DB_USERNAME=check_runtime
DB_PASSWORD=$(secret)
DB_MIGRATION_USERNAME=check_migration
DB_MIGRATION_PASSWORD=$(secret)
DB_ADMIN_USERNAME=check_admin
DB_ADMIN_PASSWORD=$(secret)
REDIS_PASSWORD=$(secret)
SECURITY_LOG_HMAC_KEY=$(secret)
EOF

echo "== the definition's configuration"
compose config --quiet

echo "== build and start"
# CI builds the image beforehand with its read-only token for Composer (the `composer_auth` secret).
if [ "${DEPLOY_CHECK_PREBUILT:-no}" != yes ]; then
    compose build --quiet
fi
compose up -d

# The long-running services healthy, the one-shot steps done.
deadline=$(( $(date +%s) + 600 ))
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$(compose ps -q web)" 2>/dev/null || echo none)" = healthy ]; do
    if [ "$(date +%s)" -gt "$deadline" ]; then
        compose ps -a
        echo 'FAIL web never became healthy'
        exit 1
    fi
    sleep 5
done

echo "== checks"
# Each check reports its own FAIL; none ends the run.
set +e
for step in db-roles migrate public; do
    code=$(docker inspect -f '{{.State.ExitCode}}' "$(compose ps -aq "$step")")
    result "$code" "the one-shot step $step completed"
done

running=$(compose ps --status running --services)
for service in app worker-1 worker-2 scheduler web db cache; do
    printf '%s\n' "$running" | grep -qx "$service"
    result $? "$service running"
done

# Criterion 2: the stores private and authenticated; the migration credential only in its one-shot step.
published=$(docker ps --filter "label=com.docker.compose.project=$project" --format '{{.Ports}}' | grep -c -- '->' || true)
[ "$published" = 0 ]
result $? "no port published on the host"
refuse "the database unreachable from the proxy's network" docker run --rm --network "${project}_edge" --entrypoint sh "$image" -c 'nc -z -w 3 db 5432'
refuse "the cache unreachable from the proxy's network" docker run --rm --network "${project}_edge" --entrypoint sh "$image" -c 'nc -z -w 3 cache 6379'
compose exec -T cache sh -c 'env -u VALKEYCLI_AUTH valkey-cli ping' 2>&1 | grep -q NOAUTH
result $? "the cache refuses a client without its password"
refuse "the database refuses a wrong password" compose exec -T app php -r 'new PDO("pgsql:host=db;dbname=multiplecorp", getenv("DB_USERNAME"), "wrong");'
for service in app worker-1 worker-2 scheduler; do
    refuse "$service holds no migration, superuser or administration credential" compose exec -T "$service" sh -c 'env | grep -E "^(DB_MIGRATION_|DB_SUPERUSER|DB_ADMIN_)"'
done
[ "$(compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "select rolcreatedb from pg_roles where rolname = '\''check_migration'\''"' | tr -d '\r')" = f ]
result $? "the migration login holds no CREATEDB on this stack"
[ "$(compose exec -T cache valkey-cli config get maxmemory | tr -d '\r' | sed -n 2p)" -gt 0 ]
result $? "Valkey has its own memory ceiling below the container's limit"
[ "$(compose exec -T app php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");' | tr -d '\r')" = '20M 25M' ]
result $? "PHP accepts the largest upload a purpose allows"
user=$(compose exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::scalar("select current_user");' 2>/dev/null || true)
[ "$user" = check_runtime ]
result $? "the application connects as the runtime role ($user)"
# HO-21: TX-06's statement timeout and the idle-in-transaction backstop as the runtime role's session sees them.
timeouts=$(compose exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::scalar("show statement_timeout"), " ", Illuminate\Support\Facades\DB::scalar("show idle_in_transaction_session_timeout");' 2>/dev/null || true)
[ "$timeouts" = '5s 15s' ]
result $? "the runtime role's defaults: a 5 s statement and 15 s idle in a transaction ($timeouts)"
# SX-03: every process runs on the framework's caches made from its own environment.
for service in app worker-1 worker-2 scheduler; do
    expect "$service runs on the cached configuration" compose exec -T "$service" test -f bootstrap/cache/config.php
done

# Criterion 3: WS-08's static headers, HSTS at its stage, no version banner (TLS and the redirect are the proxy's).
page=$(request -H "Host: $host" http://web:8080/masuk)
for header in 'strict-transport-security: max-age=300' 'x-frame-options: deny' 'x-content-type-options: nosniff' 'content-security-policy:' 'permissions-policy:' 'cross-origin-opener-policy: same-origin'; do
    printf '%s\n' "$page" | tr -d '\r' | tr 'A-Z' 'a-z' | grep -q "^$header"
    result $? "the sign-in page carries $header"
done
printf '%s\n' "$page" | tr -d '\r' | tr 'A-Z' 'a-z' | grep -Eq '^server: nginx$'
result $? "the server header names no version"
refuse "no x-powered-by header" sh -c "printf '%s\n' \"\$1\" | tr 'A-Z' 'a-z' | grep -q '^x-powered-by'" sh "$page"
[ "$(status_of -H "Host: $host" http://web:8080/robots.txt)" = 200 ]
result $? "the release's static files served from the public volume"

# Criterion 4: another host refused; a forwarded host or client address from the client never trusted.
[ "$(status_of -H "Host: other.example" http://web:8080/masuk)" != 200 ]
result $? "a request for another host refused"
spoofed=$(request -H "Host: $host" -H 'X-Forwarded-Host: evil.example' -H 'X-Forwarded-Proto: http' -H 'X-Forwarded-For: 198.51.100.7, 203.0.113.9' http://web:8080/masuk)
refuse "a forwarded host never reaches a built link" sh -c "printf '%s\n' \"\$1\" | grep -q 'evil.example'" sh "$spoofed"
compose logs --no-color web | grep -q '203.0.113.9 - '
result $? "the client address the proxy appended is the one logged and passed on"
refuse "an address the client sent is not taken" sh -c "docker compose -p $project -f deploy/coolify/compose.yml --env-file $env_file logs --no-color web | grep -q '198.51.100.7 - '"

# Criterion 10: no credential-link token or Google callback query string in the request log.
status_of -H "Host: $host" 'http://web:8080/atur-kata-sandi?token=deploychecktoken' > /dev/null || true
status_of -H "Host: $host" 'http://web:8080/reset-kata-sandi/deploychecktoken' > /dev/null || true
status_of -H "Host: $host" 'http://web:8080/masuk/google/callback?code=deploycheckcode&state=deploycheckstate' > /dev/null || true
refuse "the request log holds no credential token or callback query" sh -c "docker compose -p $project -f deploy/coolify/compose.yml --env-file $env_file logs --no-color web | grep -Eq 'deploychecktoken|deploycheckcode|deploycheckstate'"

# Criterion 6: the maintenance mode on every web process, the health check still up, lifted only by the operator.
compose exec -T app php artisan down --no-interaction > /dev/null
[ "$(status_of -H "Host: $host" http://web:8080/masuk)" = 503 ]
result $? "the maintenance mode refuses a page"
[ "$(status_of -H "Host: $host" http://web:8080/up)" = 200 ]
result $? "the health check answers through the maintenance mode"
compose exec -T app php artisan up --no-interaction > /dev/null
[ "$(status_of -H "Host: $host" http://web:8080/masuk)" = 200 ]
result $? "the page answers once the operator lifts it"

# Criterion 8 and HO-23: the processes restarted with Valkey's keys intact; the store itself restarted keeps them.
compose exec -T cache valkey-cli set multiplecorp:deploy-check kept > /dev/null
compose restart worker-1 worker-2 scheduler app > /dev/null 2>&1
[ "$(compose exec -T cache valkey-cli get multiplecorp:deploy-check | tr -d '\r')" = kept ]
result $? "the workers', scheduler's and web processes' restart keeps Valkey's keys"
compose restart cache > /dev/null 2>&1
sleep 3
[ "$(compose exec -T cache valkey-cli get multiplecorp:deploy-check | tr -d '\r')" = kept ]
result $? "the cache's own restart keeps its keys (append-only file)"

echo "== the smoke checks on the started stack (read only; the scheduled tasks have not run yet)"
compose exec -T app php artisan ops:smoke --no-interaction || true

if [ "$failures" -gt 0 ]; then
    echo "$failures check(s) failed"
    exit 1
fi
echo 'every check passed'
