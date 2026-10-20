#!/bin/sh
# Refuses a commit message that carries AI, model or tool attribution (AGENTS.md "Git commit
# attribution"; DIR-022; TASK-002): a co-author or generator trailer, or a line crediting an AI
# assistant. Usage: check-commit-messages.sh <revision range> | --self-test
set -eu

# Reads messages on stdin; prints each attributing line.
attributions() {
    grep -inE \
        -e '^[[:space:]]*(co-authored-by|generated-by|assisted-by|claude-session|ai-assisted-by)[[:space:]]*:' \
        -e '(generated|created|written|authored|assisted)[[:space:]]+(with|by)[^a-z0-9]*(claude|anthropic|chatgpt|openai|codex|copilot|gemini|cursor|devin|an? ai)' \
        -e 'noreply@anthropic\.com|claude\.ai/code' \
        || true
}

if [ "${1:-}" = "--self-test" ]; then
    for message in \
        'feat: add a thing

Co-Authored-By: Claude <noreply@anthropic.com>' \
        'fix: a thing

Generated-By: an assistant' \
        'docs: a thing

Generated with Claude Code (https://claude.ai/code)' \
        'chore: a thing

Assisted-By: a model'; do
        [ -n "$(printf '%s\n' "$message" | attributions)" ] || { printf 'FAIL: not refused:\n%s\n' "$message"; exit 1; }
    done
    [ -z "$(printf 'docs: update CLAUDE.md and the Owner identity rule\n' | attributions)" ] || { echo 'FAIL: a clean message was refused'; exit 1; }
    echo 'PASS: every attributed sample is refused and the clean one accepted'
    exit 0
fi

range=${1:?usage: check-commit-messages.sh <revision range> | --self-test}
status=0

for commit in $(git rev-list "$range"); do
    found=$(git log -1 --format=%B "$commit" | attributions)
    if [ -n "$found" ]; then
        printf 'FAIL: commit %s carries attribution:\n%s\n' "$commit" "$found"
        status=1
    fi
done

[ "$status" -eq 0 ] && echo "PASS: no attribution in the commits of $range"
exit $status
