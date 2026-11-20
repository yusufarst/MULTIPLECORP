#!/bin/sh
# The staging-only security cases of SECURITY_TEST_MATRIX §9 (TASK-069, criterion 11; TS-08): run by the operator against
# the ephemeral synthetic staging stack (DIR-079 CB-04), never by an agent, never in CI, never against production.
# It checks from outside what only a real host can show — SM-08's HTTPS redirect, HSTS stage, TLS floor, headers, cookie
# flags and debug off, and SM-20's real Google redirect with the staging OAuth client —, then lists the operator-run
# commands of SM-07, SM-19 and SM-21, whose evidence the operator records by hand. Each case is NOT RUN until this
# script's evidence file and the operator's records exist; none is reported as passed from CI.
#
#   STAGING_URL=https://<the staging host> sh scripts/staging/security-cases.sh [evidence file]
#
# It needs curl, and openssl for the TLS floor. It sends no credential and changes nothing on the host.
set -u

url=${STAGING_URL:?set STAGING_URL to the staging origin, https://...}
url=${url%/}
host=$(printf '%s' "$url" | sed -E 's#^https://([^/:]+).*#\1#')
evidence=${1:-staging-security-$(date -u +%Y%m%dT%H%M%SZ).json}
results=''
failed=0

record() {
    results="$results{\"case\": \"$1\", \"check\": \"$2\", \"result\": \"$3\"},"
    printf '%-6s %-5s %s\n' "$1" "$3" "$2"
    [ "$3" = FAIL ] && failed=1
    return 0
}

expect() {
    if eval "$3"; then record "$1" "$2" PASS; else record "$1" "$2" FAIL; fi
}

case $url in
    https://*) ;;
    *) echo "STAGING_URL must be an https:// origin" >&2; exit 2 ;;
esac

headers=$(curl -sS -D - -o /dev/null "$url/masuk")
body404=$(curl -sS "$url/alamat-yang-tidak-ada-$(date +%s)")
plain=$(curl -sS -o /dev/null -w '%{http_code} %{redirect_url}' "http://$host/masuk")
health=$(curl -sS -w ' %{http_code}' "$url/up")
google=$(curl -sS -D - -o /dev/null "$url/masuk/google")
header() { printf '%s\n' "$headers" | grep -i "^$1:" | head -n 1; }

# SM-08 (H8-08; WS-05, WS-06, WS-08; AU-06).
expect SM-08 'plain HTTP redirected to HTTPS (WS-06)' 'case "$plain" in 301\ https://*|308\ https://*) true ;; *) false ;; esac'
expect SM-08 'HSTS at one of the stages, no preload (WS-06)' 'header Strict-Transport-Security | grep -Eq "max-age=(300|604800|31536000)" && ! header Strict-Transport-Security | grep -qi preload'
if command -v openssl > /dev/null; then
    expect SM-08 'TLS 1.1 refused' '! printf "" | openssl s_client -connect "$host:443" -servername "$host" -tls1_1 > /dev/null 2>&1'
    expect SM-08 'TLS 1.2 accepted' 'printf "" | openssl s_client -connect "$host:443" -servername "$host" -tls1_2 > /dev/null 2>&1'
else
    record SM-08 'TLS floor (openssl missing)' 'NOT RUN'
fi
expect SM-08 'CSP with a nonce, frame-ancestors none (WS-08)' 'header Content-Security-Policy | grep -q "nonce-" && header Content-Security-Policy | grep -q "frame-ancestors '"'"'none'"'"'"'
expect SM-08 'nosniff (WS-08)' 'header X-Content-Type-Options | grep -qi nosniff'
expect SM-08 'Referrer-Policy (WS-08)' 'header Referrer-Policy | grep -qi "same-origin\|strict-origin\|no-referrer"'
expect SM-08 'the session cookie __Host-, Secure, HttpOnly, SameSite=Lax (AU-06)' 'printf "%s\n" "$headers" | grep -i "^set-cookie: __Host-" | grep -i secure | grep -i httponly | grep -qi "samesite=lax"'
expect SM-08 'the XSRF-TOKEN cookie Secure and SameSite=Lax (WS-05)' 'printf "%s\n" "$headers" | grep -i "^set-cookie: XSRF-TOKEN" | grep -i secure | grep -qi "samesite=lax"'
expect SM-08 'debug off: an unknown address shows no trace (APP_DEBUG=false)' '! printf "%s" "$body404" | grep -Eqi "stack trace|whoops|vendor/laravel|exception"'
expect SM-08 'no server version or framework header' '! header X-Powered-By | grep -q . && ! header Server | grep -Eq "[0-9]+\.[0-9]+"'
expect SM-08 'the health check minimal (SX-03; RB-09)' 'case "$health" in *" 200") ! printf "%s" "$health" | grep -Eqi "version|laravel|php|[0-9]+\.[0-9]+\.[0-9]+" ;; *) false ;; esac'

# SM-20 (H8-20; AU-22): the real redirect to Google with the staging OAuth client; the operator then completes one
# sign-in of an account linked on staging and one of an unlinked identity, recording that no account was created.
location=$(printf '%s\n' "$google" | grep -i '^location:' | head -n 1)
expect SM-20 'the redirect to Google with state, nonce and the staging callback (AU-22)' 'printf "%s" "$location" | grep -q "accounts.google.com" && printf "%s" "$location" | grep -q "state=" && printf "%s" "$location" | grep -q "nonce=" && printf "%s" "$location" | grep -q "redirect_uri=https%3A%2F%2F$host"'
record SM-20 'a linked account signs in; an unlinked identity creates no account (operator, by hand)' MANUAL

# SM-07, SM-19, SM-21 (H8-07, H8-19, H8-21): the operator's commands inside the staging app container, each recorded with
# its output and the security log's events — never on production.
record SM-07 'php artisan iam:owner-recovery-link <the staging Owner e-mail>: the link shown once, OWNER_RECOVERY_LINK_ISSUED logged, the password set once (AU-14)' MANUAL
record SM-19 'the reset and recovery paths a–f with the recovery link above, on staging only (AU-14; AU-20; AU-24)' MANUAL
record SM-21 'php artisan iam:key-compromise, then iam:restore-owner <email> and iam:end-recovery: sessions ended, the restoration refused twice for one account, the end atomic (RV-18; M-46)' MANUAL

printf '{"origin_host_recorded": false, "results": [%s]}\n' "${results%,}" > "$evidence"
echo "evidence: $evidence"
[ "$failed" -eq 0 ] || { echo 'staging security cases: FAIL'; exit 1; }
echo 'staging security cases: the automatic checks PASS; the MANUAL cases wait for the operator'"'"'s records'
