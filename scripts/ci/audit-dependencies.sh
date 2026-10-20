#!/bin/sh
# The dependency audits of SECURITY_TEST_MATRIX SM-15 (TL-14; TASK-003): composer audit and npm audit
# over the committed lockfiles, failing on any advisory. `--self-test` proves that each audit refuses a
# lockfile pinning a release with a published advisory — generated in a temporary directory, never
# committed — and names that advisory, so that an audit that cannot run never passes for a clean one.
# Usage: audit-dependencies.sh php|js [--self-test] — php in the app container, js in the node container.
set -eu

tool=${1:?usage: audit-dependencies.sh php|js [--self-test]}

audit() {
    case $tool in
        php) composer audit --locked --no-interaction --working-dir="$1" ${2:+"$2"} ;;
        js) (cd "$1" && npm audit --audit-level=low ${2:+"$2"}) ;;
        *) echo "unknown tool: $tool" >&2; return 2 ;;
    esac
}

if [ "${2:-}" = "--self-test" ]; then
    fixture=$(mktemp -d)
    trap 'rm -rf "$fixture"' EXIT
    case $tool in
        php)
            # guzzlehttp/guzzle 7.4.0 carries CVE-2022-29248, fixed in 7.4.3.
            advisory=CVE-2022-29248
            printf '%s\n' '{"name": "multiplecorp/audit-fixture", "version": "1.0.0", "require": {"guzzlehttp/guzzle": "7.4.0"}}' > "$fixture/composer.json"
            printf '%s\n' '{"packages": [{"name": "guzzlehttp/guzzle", "version": "7.4.0", "type": "library"}], "packages-dev": []}' > "$fixture/composer.lock"
            format=--format=json
            ;;
        js)
            # minimist 1.2.5 carries GHSA-xvch-5gv4-984h, fixed in 1.2.6.
            advisory=GHSA-xvch-5gv4-984h
            printf '%s\n' '{"name": "audit-fixture", "version": "1.0.0", "dependencies": {"minimist": "1.2.5"}}' > "$fixture/package.json"
            printf '%s\n' '{"name": "audit-fixture", "version": "1.0.0", "lockfileVersion": 3, "requires": true, "packages": {"": {"name": "audit-fixture", "version": "1.0.0", "dependencies": {"minimist": "1.2.5"}}, "node_modules/minimist": {"version": "1.2.5", "resolved": "https://registry.npmjs.org/minimist/-/minimist-1.2.5.tgz"}}}' > "$fixture/package-lock.json"
            format=--json
            ;;
        *) echo "unknown tool: $tool" >&2; exit 2 ;;
    esac
    if audit "$fixture" "$format" > "$fixture/report" 2>&1; then
        echo "FAIL: the $tool audit passed a lockfile carrying $advisory"
        exit 1
    fi
    if ! grep -q "$advisory" "$fixture/report"; then
        echo "FAIL: the $tool audit failed without reporting $advisory:"
        tail -n 20 "$fixture/report"
        exit 1
    fi
    echo "PASS: the $tool audit refuses a lockfile carrying $advisory"
    exit 0
fi

audit .
