#!/bin/sh
# The one verification command (TEST_STRATEGY §17, TS-10; TASK-003): the deterministic checks of each
# layer against the Docker Compose stack of DEVELOPMENT.md, which it starts, each reported in TEST_STRATEGY
# §16's form; CI runs it again on a fresh runner.
#
#   sh scripts/verify.sh               the repository gates, TL-01–TL-08, TL-13 and TL-14
#   sh scripts/verify.sh browser       TL-09–TL-12 on Chromium and WebKit
#   sh scripts/verify.sh performance   TL-15, with the load tool's smoke run
#   sh scripts/verify.sh migration     TL-16, on a fresh database only
#
# A layer whose first test is not written yet reports EMPTY with the Task that writes it, and runs once
# it exists; any FAIL or NOT RUN fails the command. VERIFY_PHP, VERIFY_NODE, VERIFY_E2E and VERIFY_K6 replace
# the commands that run PHP, Node.js, Playwright and k6 on a machine that cannot build the application image.
set -u

php_run=${VERIFY_PHP-docker compose exec -T app}
node_run=${VERIFY_NODE-docker compose run --rm node}
e2e_run=${VERIFY_E2E-docker compose run --rm e2e}
k6_run=${VERIFY_K6-docker compose run --rm k6}
environment=${VERIFY_ENVIRONMENT:-local}
commit=$(git rev-parse --short HEAD 2>/dev/null || echo unknown)
logs=storage/logs/verify
summary=''
failed=0

mkdir -p "$logs"

report() {
    summary="$summary$1: $2 — Evidence: $commit, $environment, $3, $4
"
    case $2 in
        PASS | EMPTY*) ;;
        *) failed=1 ;;
    esac
}

check() {
    name=$1
    shift
    log="$logs/$(printf '%s' "$name" | tr -cs 'A-Za-z0-9' '-').log"
    printf '== %s\n' "$name"
    if "$@" > "$log" 2>&1; then
        report "$name" PASS "$*" "$log"
    else
        tail -n 60 "$log"
        report "$name" FAIL "$*" "$log"
    fi
}

# A layer's slot: its tests run once the first exists.
slot() {
    name=$1 directory=$2 pattern=$3 writer=$4
    shift 4
    if [ -n "$(find "$directory" -type f -name "$pattern" 2>/dev/null | head -n 1)" ]; then
        check "$name" "$@"
    else
        report "$name" "EMPTY — no test until $writer" "find $directory -name '$pattern'" 'none'
    fi
}

# The command of a PHP test suite and of a browser layer, split into words by their callers.
suite() {
    echo "$php_run php artisan test --parallel --testsuite=$1 --log-junit $logs/junit-$1.xml"
}

browser() {
    echo "$e2e_run npx --no-install playwright test -c tests/Browser/playwright.config.ts tests/Browser/$1"
}

local_layers() {
    range=${VERIFY_COMMIT_RANGE:-}
    if [ -z "$range" ]; then
        if git rev-parse -q --verify origin/main > /dev/null; then range=origin/main..HEAD; else range=HEAD~1..HEAD; fi
    fi

    check 'Stack' docker compose up -d --wait
    check 'Repository gates (SM-15; LF)' sh -c 'sh scripts/ci/check-repository.sh --self-test && sh scripts/ci/check-repository.sh'
    check 'Commit messages (AGENTS.md)' sh -c "sh scripts/ci/check-commit-messages.sh --self-test && sh scripts/ci/check-commit-messages.sh $range"
    check 'Task plan integrity (PMC-15)' $node_run sh -c 'node scripts/ci/check-plan.mjs --self-test && node scripts/ci/check-plan.mjs'
    check 'TL-01 Pint' $php_run vendor/bin/pint --test
    check 'TL-01 ESLint' $node_run npm run lint
    check 'TL-02 Larastan/PHPStan' $php_run vendor/bin/phpstan analyse --no-progress --memory-limit=1G
    check 'TL-02 TypeScript' $node_run npm run typecheck
    check 'TL-03 Unit' $(suite Unit)
    check 'TL-03 Architecture (SM-03)' $(suite Architecture)
    check 'TL-03 ESLint rules' $node_run npm run test:lint-rules
    check 'TL-04 Feature' $(suite Feature)
    check 'TL-05 Integration' $(suite Integration)
    slot 'TL-06 Authorization' tests/Authorization '*Test.php' TASK-011 $(suite Authorization)
    slot 'TL-07 Route integrity' tests/Routes '*Test.php' TASK-011 $(suite Routes)
    slot 'TL-08 Interaction' tests/Interaction '*Test.php' 'the first Task with an INT- contract' $(suite Interaction)
    check 'TL-13 Frontend build' $node_run npm run build
    check 'TL-14 Secret-like scan (SX-06)' $node_run sh -c 'sh scripts/ci/scan-secrets.sh --self-test && sh scripts/ci/scan-secrets.sh'
    check 'TL-14 PHP dependency audit' $php_run sh -c 'sh scripts/ci/audit-dependencies.sh php --self-test && sh scripts/ci/audit-dependencies.sh php'
    check 'TL-14 JavaScript dependency audit' $node_run sh -c 'sh scripts/ci/audit-dependencies.sh js --self-test && sh scripts/ci/audit-dependencies.sh js'
    # Last: the production check removes the development packages, which a local run reinstalls.
    check 'TL-13 Production caches and configuration' $php_run sh scripts/ci/production-check.sh
    case $environment in
        CI*) ;;
        *) check 'Development packages reinstalled' $php_run composer install --no-interaction --prefer-dist --no-progress ;;
    esac
}

browser_layers() {
    check 'Stack' docker compose up -d --wait
    slot 'TL-09 E2E/browser' tests/Browser/E2E '*.spec.ts' TASK-062 $(browser E2E)
    slot 'TL-10 Localization' tests/Browser/Localization '*.spec.ts' 'the first page with copy' $(browser Localization)
    slot 'TL-11 Responsive' tests/Browser/Responsive '*.spec.ts' 'the first page' $(browser Responsive)
    slot 'TL-12 Accessibility' tests/Browser/Accessibility '*.spec.ts' 'the first page' $(browser Accessibility)
}

performance_layers() {
    check 'Stack' docker compose up -d --wait
    slot 'TL-15 Performance' tests/Performance '*Test.php' 'the first Task with a budget' $(suite Performance)
    check 'TL-15 Load tool smoke run (k6)' $k6_run run --quiet tests/Performance/load/smoke.js
}

migration_layers() {
    if [ -z "$(find database/migrations -type f -name '*.php' 2>/dev/null | head -n 1)" ]; then
        report 'TL-16 Migration' 'EMPTY — no migration until TASK-005' "find database/migrations -name '*.php'" 'none'
        return
    fi
    case $environment in
        CI*)
            check 'Stack' docker compose up -d --wait
            # As the migration/owner role, the only one that runs DDL (DATABASE §28; TASK-005).
            check 'TL-16 Migration up, down and up from empty' $php_run sh -c 'php artisan migrate --database=migration --force && php artisan migrate:rollback --database=migration --force && php artisan migrate --database=migration --force'
            ;;
        *) report 'TL-16 Migration' 'NOT RUN — it needs the fresh database of a CI runner' 'none' 'none' ;;
    esac
}

[ $# -gt 0 ] || set -- local
for section in "$@"; do
    case $section in
        local) local_layers ;;
        browser) browser_layers ;;
        performance) performance_layers ;;
        migration) migration_layers ;;
        *) echo "usage: sh scripts/verify.sh [local] [browser] [performance] [migration]" >&2; exit 2 ;;
    esac
done

printf '\n%s' "$summary"
if [ "$failed" -ne 0 ]; then
    echo 'verify: FAIL'
    exit 1
fi
echo 'verify: PASS'
