#!/bin/sh
# TL-05 on the deployment definition (TASK-072 criteria 2, 3, 4, 6, 8 and 10; TASK-078's staging findings; SECURITY H9-09,
# WS-06, WS-08, WS-11, H9-14, LG-04; INFRASTRUCTURE §4.4): deploy/coolify/compose.yml started as Coolify v4.7.6 starts it,
# with throwaway values and credential files generated here and never stored, then checked from a container on the
# resource's own network, as Coolify's proxy reaches it. Coolify gives every service every variable of the resource and
# attaches every service to every network of the file and to the resource's own network (docs/09-operations/
# COOLIFY_DEPLOYMENT.md §3); an override file does the same here. TLS and the plain-HTTP redirect are Coolify's proxy's,
# outside this stack. Prints one PASS or FAIL line per check; exits 1 on any FAIL. Leaves the stack running for its logs
# (`sh scripts/ci/check-deployment.sh logs`); `sh scripts/ci/check-deployment.sh down` removes it — its own Compose project,
# network and files only.
set -eu

project=${DEPLOY_CHECK_PROJECT:-mcdeploy}
directory=${RUNNER_TEMP:-${TMPDIR:-/tmp}}
env_file="$directory/$project.env"
coolify_file="$directory/$project.coolify.yml"
credentials="$directory/$project-credentials"
resource_network="$project-resource"
host=app.example.invalid
image=multiplecorp-app:production-deploy-check
failures=0
if [ "$(id -u)" = 0 ]; then as_root=; else as_root='sudo -n'; fi

# The definition as written, and as Coolify deploys it.
definition() {
    docker compose -p "$project" -f deploy/coolify/compose.yml --env-file "$env_file" "$@"
}

compose() {
    docker compose -p "$project" -f deploy/coolify/compose.yml -f "$coolify_file" --env-file "$env_file" "$@"
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

# A request from a container on the resource's network, as Coolify's proxy sends it: its response headers and body.
request() {
    docker run --rm --network "$resource_network" --entrypoint curl "$image" -sS -D - -o - --max-time 20 "$@"
}

status_of() {
    docker run --rm --network "$resource_network" --entrypoint curl "$image" -sS -o /dev/null -w '%{http_code}' --max-time 20 "$@"
}

# A container's bind mounts, their host paths sorted on one line.
binds_of() {
    docker inspect -f '{{range .Mounts}}{{if eq .Type "bind"}}{{println .Source}}{{end}}{{end}}' "$1" | sed '/^$/d' | sort | tr '\n' ' '
}

case "${1:-}" in
    down)
        if [ -f "$env_file" ] && [ -f "$coolify_file" ]; then
            compose down -v --remove-orphans
        fi
        docker network rm "$resource_network" > /dev/null 2>&1 || true
        rm -f "$env_file" "$coolify_file"
        for role in superuser migration admin; do
            if [ -d "$credentials/$role" ]; then
                $as_root rm -f "$credentials/$role/username" "$credentials/$role/password"
                $as_root rmdir "$credentials/$role"
            fi
        done
        if [ -d "$credentials" ]; then $as_root rmdir "$credentials"; fi
        exit 0
        ;;
    logs)
        compose logs --no-color
        exit 0
        ;;
esac

umask 077
superuser_password=$(secret)
migration_password=$(secret)
admin_password=$(secret)
cat > "$env_file" <<EOF
MULTIPLECORP_RELEASE=deploy-check
APP_ENV=production
APP_URL=https://$host
APP_KEY=base64:$(head -c 32 /dev/urandom | base64)
APP_TRUSTED_PROXIES=192.0.2.1
APP_TRUSTED_HOSTS=$host
MULTIPLECORP_HOST=$host
MULTIPLECORP_HSTS=max-age=300
CSP_REPORT_ONLY=true
DB_DATABASE=multiplecorp
DB_USERNAME=check_runtime
DB_PASSWORD=$(secret)
MULTIPLECORP_CREDENTIALS_DIR=$credentials
REDIS_PASSWORD=$(secret)
SECURITY_LOG_HMAC_KEY=$(secret)
EOF

# The operator's credentials directory, owned as COOLIFY_DEPLOYMENT §5 has it on the VPS: the superuser's and the
# administration login's files the superuser's alone, the migration login's the application image's user's (82), each
# file 0400. One line each, ended by a newline as an editor leaves it.
mkdir -p "$credentials/superuser" "$credentials/migration" "$credentials/admin"
printf '%s\n' check_superuser > "$credentials/superuser/username"
printf '%s\n' "$superuser_password" > "$credentials/superuser/password"
printf '%s\n' check_migration > "$credentials/migration/username"
printf '%s\n' "$migration_password" > "$credentials/migration/password"
printf '%s\n' check_admin > "$credentials/admin/username"
printf '%s\n' "$admin_password" > "$credentials/admin/password"
$as_root chown -R 0:0 "$credentials/superuser" "$credentials/admin"
$as_root chown -R 82:82 "$credentials/migration"
$as_root chmod 0400 "$credentials/superuser/username" "$credentials/superuser/password" "$credentials/migration/username" \
    "$credentials/migration/password" "$credentials/admin/username" "$credentials/admin/password"
$as_root chmod 0700 "$credentials/superuser" "$credentials/admin"
$as_root chmod 0500 "$credentials/migration"

echo "== the definition's configuration"
definition config --quiet

# Coolify v4.7.6's own changes to the file (COOLIFY_DEPLOYMENT §3), as an override: every service given the resource's
# variables, as Coolify's `env_file: .env` gives them, and attached to every network of the file and to the resource's
# own network, which Coolify's proxy joins — here the network the checking container joins.
networks=$(definition config --networks)
{
    echo 'services:'
    for service in $(definition config --services); do
        printf '  %s:\n    env_file: ["%s"]\n    networks:\n' "$service" "$env_file"
        for network in $networks coolify-resource; do
            printf '      %s: {}\n' "$network"
        done
    done
    printf 'networks:\n  coolify-resource:\n    name: %s\n    external: true\n' "$resource_network"
} > "$coolify_file"
docker network inspect "$resource_network" > /dev/null 2>&1 || docker network create --attachable "$resource_network" > /dev/null
compose config --quiet

echo "== build and start"
# CI builds the application's image beforehand with its read-only token for Composer (the `composer_auth` secret); the
# site's and the roles' images are built here.
if [ "${DEPLOY_CHECK_PREBUILT:-no}" != yes ]; then
    definition build --quiet
else
    definition build --quiet web db-roles
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

# Coolify's attachment, which the reachability checks below rely on: the stores and php-fpm on every network too.
for service in db cache app; do
    attached=$(docker inspect -f '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$(compose ps -q "$service")")
    for network in "$resource_network" "${project}_edge" "${project}_egress"; do
        printf '%s\n' "$attached" | grep -qx -- "$network"
        result $? "$service attached to $network, as Coolify attaches every service"
    done
done

# Criterion 2 and H9-09: the stores and php-fpm listen on `internal` alone, private and authenticated; no port published.
published=$(docker ps --filter "label=com.docker.compose.project=$project" --format '{{.Ports}}' | grep -c -- '->' || true)
[ "$published" = 0 ]
result $? "no port published on the host"
for network in "$resource_network" "${project}_edge" "${project}_egress"; do
    refuse "the database refuses a connection from $network" docker run --rm --network "$network" --entrypoint sh "$image" -c 'nc -z -w 3 db 5432'
    refuse "the cache refuses a connection from $network" docker run --rm --network "$network" --entrypoint sh "$image" -c 'nc -z -w 3 cache 6379'
    refuse "php-fpm refuses a connection from $network" docker run --rm --network "$network" --entrypoint sh "$image" -c 'nc -z -w 3 app 9000'
done
expect "the database, the cache and php-fpm answer on internal" docker run --rm --network "${project}_internal" --entrypoint sh "$image" -c 'nc -z -w 3 db.internal 5432 && nc -z -w 3 cache.internal 6379 && nc -z -w 3 app.internal 9000'
compose exec -T cache sh -c 'env -u VALKEYCLI_AUTH valkey-cli ping' 2>&1 | grep -q NOAUTH
result $? "the cache refuses a client without its password"
compose exec -T app php -r 'try { new PDO("pgsql:host=db.internal;dbname=multiplecorp", getenv("DB_USERNAME"), "wrong"); } catch (PDOException $e) { echo $e->getMessage(); }' 2>&1 | grep -q 'password authentication failed'
result $? "the database refuses a wrong password"

# INFRASTRUCTURE §4.4 under Coolify's variables: the superuser, migration and administration logins in no container's
# configuration — no password in any `docker inspect` —, in no long-running process's environment, and each credential
# directory mounted only where it is used; nothing mounted from the repository.
# (`owner`, since the helpers above keep each check's name in `name`.)
for container in $(compose ps -aq); do
    owner=$(docker inspect -f '{{index .Config.Labels "com.docker.compose.service"}}' "$container")
    refuse "$owner's configuration holds no superuser, migration or administration password" sh -c 'docker inspect "$1" | grep -qF -e "$2" -e "$3" -e "$4"' sh "$container" "$superuser_password" "$migration_password" "$admin_password"
    case $owner in
        db) wanted="$credentials/superuser" ;;
        db-roles) wanted="$credentials/admin $credentials/migration $credentials/superuser" ;;
        migrate) wanted="$credentials/migration" ;;
        *) wanted= ;;
    esac
    [ "$(binds_of "$container")" = "$(printf '%s\n' $wanted | sed '/^$/d' | sort | tr '\n' ' ')" ]
    result $? "$owner's host mounts: ${wanted:-none}"
done
for service in app worker-1 worker-2 scheduler web cache; do
    refuse "$service holds no migration, superuser or administration credential" compose exec -T "$service" sh -c 'env | grep -E "^(DB_MIGRATION_|DB_SUPERUSER|DB_ADMIN_)"'
done
[ "$(compose exec -T db sh -c 'psql -U "$(cat /run/multiplecorp/superuser/username)" -d "$POSTGRES_DB" -tAc "select rolcreatedb from pg_roles where rolname = '\''check_migration'\''"' | tr -d '\r')" = f ]
result $? "the migration login holds no CREATEDB on this stack"
[ "$(docker run --rm --network "${project}_internal" -e PGPASSWORD="$admin_password" --entrypoint psql "multiplecorp-db-roles:production-deploy-check" -h db.internal -U check_admin -d multiplecorp -tAc 'select current_user' 2>/dev/null | tr -d '\r')" = check_admin ]
result $? "the administration login signs in with its file's password"
[ "$(compose exec -T cache valkey-cli config get maxmemory | tr -d '\r' | sed -n 2p)" -gt 0 ]
result $? "Valkey has its own memory ceiling below the container's limit"
[ "$(compose exec -T app php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");' | tr -d '\r')" = '20M 25M' ]
result $? "PHP accepts the largest upload a purpose allows"
user=$(compose exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::scalar("select current_user");' 2>/dev/null || true)
[ "$user" = check_runtime ]
result $? "the application connects as the runtime role ($user)"
# WS-08's staging stage: CSP_REPORT_ONLY reaches every process's cached configuration (on production it changes no header).
for service in app worker-1 worker-2 scheduler; do
    [ "$(compose exec -T "$service" php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo var_export(config("app.csp_report_only"), true);' 2>/dev/null | tr -d '\r')" = true ]
    result $? "$service reads CSP_REPORT_ONLY from its environment"
done
# HO-21: the runtime's login defaults to TX-06's 5 s statement timeout and the 15 s idle-in-transaction backstop.
timeouts=$(compose exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::scalar("show statement_timeout"), " ", Illuminate\Support\Facades\DB::scalar("show idle_in_transaction_session_timeout");' 2>/dev/null || true)
[ "$timeouts" = '5s 15s' ]
result $? "the runtime's sessions default to HO-21's 5 s statement and 15 s idle timeouts ($timeouts)"
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
refuse "an address the client sent is not taken" sh -c "docker compose -p $project -f deploy/coolify/compose.yml -f $coolify_file --env-file $env_file logs --no-color web | grep -q '198.51.100.7 - '"

# Criterion 10: no credential-link token or Google callback query string in the request log.
status_of -H "Host: $host" 'http://web:8080/atur-kata-sandi?token=deploychecktoken' > /dev/null || true
status_of -H "Host: $host" 'http://web:8080/reset-kata-sandi/deploychecktoken' > /dev/null || true
status_of -H "Host: $host" 'http://web:8080/masuk/google/callback?code=deploycheckcode&state=deploycheckstate' > /dev/null || true
refuse "the request log holds no credential token or callback query" sh -c "docker compose -p $project -f deploy/coolify/compose.yml -f $coolify_file --env-file $env_file logs --no-color web | grep -Eq 'deploychecktoken|deploycheckcode|deploycheckstate'"

# Criterion 6: the maintenance mode on every web process, the health check still up, lifted only by the operator.
compose exec -T app php artisan down --no-interaction > /dev/null
[ "$(status_of -H "Host: $host" http://web:8080/masuk)" = 503 ]
result $? "the maintenance mode refuses a page"
[ "$(status_of -H "Host: $host" http://web:8080/kesehatan)" = 200 ]
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
