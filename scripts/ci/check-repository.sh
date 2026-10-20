#!/bin/sh
# The repository gates of the minimal CI (TASK-001): every action of a workflow pinned to a
# commit SHA, no secret but the CI's own token, lockfile-only installs (SECURITY_TEST_MATRIX
# SM-15; SECURITY SX-05) and no committed text file with CRLF line endings.
# `--self-test` proves that each gate detects its failure on a generated fixture.
set -eu

workflows=.github/workflows

unpinned_actions() {
    grep -RnE '^[[:space:]-]*uses:' "$1" \
        | grep -vE "uses:[[:space:]]*[\"']?\./" \
        | grep -vE "@[0-9a-f]{40}([[:space:]\"'#]|$)" || true
}

foreign_secrets() {
    # Only GitHub expressions, ${{ … secrets.NAME … }}, name a secret.
    grep -RnoE '\$\{\{[^}]*secrets\.[A-Za-z0-9_]+' "$1" | grep -vE 'secrets\.GITHUB_TOKEN$' || true
}

unlocked_installs() {
    grep -RnE 'composer[[:space:]]+(update|upgrade|require)|npm[[:space:]]+(install|i|update|add)([[:space:]]|$)' "$1" || true
}

crlf_text_files() {
    # Reads `git ls-files --eol`; source records marked -text keep their bytes.
    awk '($1 == "i/crlf" || $1 == "i/mixed") && index($0, "attr/-text") == 0 { print }'
}

report() {
    if [ -n "$2" ]; then
        printf 'FAIL: %s\n%s\n' "$1" "$2"
        return 1
    fi
    printf 'PASS: %s\n' "$1"
}

if [ "${1:-}" = "--self-test" ]; then
    fixture=$(mktemp -d)
    trap 'rm -rf "$fixture"' EXIT
    {
        echo 'steps:'
        echo '  - uses: actions/checkout@v4'
        echo '  - run: npm install'
        echo '    env:'
        echo '      KEY: ${{ secrets.DEPLOY_KEY }}'
    } > "$fixture/bad.yml"
    [ -n "$(unpinned_actions "$fixture")" ] || { echo 'FAIL: the unpinned action was not detected'; exit 1; }
    [ -n "$(foreign_secrets "$fixture")" ] || { echo 'FAIL: the foreign secret was not detected'; exit 1; }
    [ -n "$(unlocked_installs "$fixture")" ] || { echo 'FAIL: the unlocked install was not detected'; exit 1; }
    [ -n "$(printf 'i/crlf\tw/crlf\tattr/\tbad.txt\n' | crlf_text_files)" ] || { echo 'FAIL: the CRLF file was not detected'; exit 1; }
    [ -z "$(printf 'i/crlf\tw/crlf\tattr/-text\trecord.txt\n' | crlf_text_files)" ] || { echo 'FAIL: a -text record was flagged'; exit 1; }
    echo 'PASS: every gate detects its fixture'
    exit 0
fi

status=0
report 'every workflow action pinned to a commit SHA' "$(unpinned_actions "$workflows")" || status=1
report 'no secret but the CI token' "$(foreign_secrets "$workflows")" || status=1
report 'lockfile-only installs' "$(unlocked_installs "$workflows")" || status=1
report 'no committed text file with CRLF line endings' "$(git ls-files --eol | crlf_text_files)" || status=1
exit $status
