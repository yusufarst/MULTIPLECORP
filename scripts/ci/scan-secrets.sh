#!/bin/sh
# The secret-like scan of SECURITY SX-06 over the repository's files, run in the Node.js container
# (TASK-002; TASK-003 makes it a gate of SM-15). `--self-test` proves detection on a token generated
# in a temporary directory, never in the repository.
set -eu

scan() {
    npx --no-install secretlint --secretlintrc .secretlintrc.json --secretlintignore .secretlintignore "$@"
}

if [ "${1:-}" = "--self-test" ]; then
    dir=$(mktemp -d)
    trap 'rm -rf "$dir"' EXIT
    token="ghp_$(head -c 512 /dev/urandom | tr -dc 'A-Za-z0-9' | head -c 36)"
    printf 'GITHUB_TOKEN=%s\n' "$token" > "$dir/leak.env"
    if scan "$dir/leak.env" > /dev/null 2>&1; then
        echo 'FAIL: the generated token was not detected'
        exit 1
    fi
    echo 'PASS: the secret-like scan detects a generated token'
    exit 0
fi

scan "**/*"
echo 'PASS: no secret-like value in the repository files'
