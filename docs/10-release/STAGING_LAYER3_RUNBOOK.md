# Staging Layer 3 runbook

Status: REVIEW | Updated: 2026-11-20 | Owner: Planning

*Written by the executor of [TASK-070](../11-tasks/TASK-070.md) (criterion 5; TEST_STRATEGY §4; SECURITY_TEST_MATRIX §9). It lists every Layer 3 check that runs on staging — the ephemeral, synthetic stack the operator starts before a release (DIR-079 CB-04), never touching the second production application (INFRASTRUCTURE INF-07) —, each with the command or steps, its expected result and the evidence the operator keeps. **The operator runs every check; no agent reaches the VPS.** Nothing runs on production. A check not run is recorded NOT RUN with its reason, never as passed.*

## 1. Before the checks

1. The staging stack is up from the release candidate's image under the operator's own procedure ([COOLIFY_DEPLOYMENT](../09-operations/COOLIFY_DEPLOYMENT.md) §5) — the candidate's commit named in the release record ([records/_template](records/_template/RELEASE_RECORD.md)).
2. Its data is synthetic only: the first Owner installed with `php artisan iam:install-owner`, then `php artisan staging:seed --owner=<that e-mail>` (TASK-072); no production data, dump or credential ever reaches staging ([PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)).
3. `php artisan ops:smoke` passes inside the `app` container (REL-11), and `guards:reconcile` reports no difference before and after the checks (TS-11).
4. Each evidence file is kept with the release record, named by check and date; no secret, host name or address is written into the repository (SX-06).

## 2. The checks

| # | Check | How the operator runs it | Expected result | Evidence kept |
| --- | --- | --- | --- | --- |
| S-01 | CJ-16 — the opening import, numbering and go-live at the cutover, on staging | `CJ16_STAGING=1` with `CJ16_SEEDS` and `E2E_BASE_URL` set to the staging origin, then `npx playwright test tests/Browser/E2E/journey-cj16.spec.ts` from the operator's machine; `CJ16_GO_LIVE_RECORDED=1` once the operator has recorded go-live | every step PASS on Chromium and WebKit; the seeds and go-live as PG-061 shows them | the Playwright report and the journey's console-and-network capture |
| S-02 | The Layer 3 journeys CJ-01–CJ-22 on staging, phone and desktop | the browser suite's E2E layer against the staging origin (`E2E_BASE_URL`), one worker | every journey PASS; no critical console error and no failed request in any capture | the reports and each journey's `console-and-network.json`, which the release integrity report's BROWSER section reads |
| S-03 | SM-20 — Google sign-in with the staging OAuth client (TASK-082) | `STAGING_URL=https://<staging origin> sh scripts/staging/security-cases.sh`, its SM-20 line; then by hand: a linked account signs in, an unlinked Google identity is refused | the redirect to Google with `state`, `nonce` and the staging callback; the linked sign-in reaches its landing page; no account created from Google (AU-22) | the script's evidence file; the operator's notes of the two sign-ins |
| S-04 | SM-08 — HTTPS redirect, HSTS stage, TLS floor, headers, cookie flags, debug off, minimal health check | the same script, its SM-08 lines | every SM-08 line PASS; HSTS at the stage WS-06 has reached | the script's evidence file |
| S-05 | SM-07 and SM-19 — the Owner-recovery link and the reset paths a–f | inside the `app` container: `php artisan iam:owner-recovery-link <the staging Owner's e-mail>`; the link used once in a browser; the paths a–f of H8-19 followed | the link shown once; OWNER_RECOVERY_LINK_ISSUED in the security log; the password set once; a second use refused | the command's output, the security log's rows (no token copied) |
| S-06 | SM-21 — key compromise and the Owner's restoration | `php artisan iam:key-compromise`, then `php artisan iam:restore-owner <e-mail>` and `php artisan iam:end-recovery`, each as BACKUP_RECOVERY TT-F30 describes | every session ended; the restoration refused a second time for one account; the end of recovery atomic (GAP-050 stays OPEN until RV-18 adopts it) | each command's output and the audit and security log rows |
| S-07 | PFM-01's times | `MODE=warm k6 run tests/Performance/load/staging.js` with `EMAIL`/`PASSWORD` or `COOKIE` of a persona without a second step | each page's p95 within its QB time — 300 ms for QB-01, QB-05, QB-07 and QB-10, 400 ms for QB-02 and QB-09, 150 ms for QB-06 | the k6 summary |
| S-08 | PFM-13 — AS-03's peak | `MODE=peak DURATION=<minutes> k6 run tests/Performance/load/staging.js` | every p95 within its budget, no error | the k6 summary |
| S-09 | PFM-12 and PFM-16 | `php scripts/staging/measure-hashing.php`; `php scripts/staging/measure-jobs.php` after the renditions and exports of S-02 | PFM-12's figure for OPI-07; a rendition READY within 30 s at 1x, an export within its bounds | each script's output |
| S-10 | The backup and its restore on staging (BACKUP_RECOVERY; TASK-080) | the operator's backup and restore procedure on the staging data | the restore verified by `ops:smoke` and `guards:reconcile` | the procedure's record |

## 3. After the checks

- The operator enters each result in the release record: PASS, FAIL with its output, or NOT RUN with its reason. A FAIL keeps the release a NO-GO until its re-run passes (TS-06; TS-12).
- The staging summary the release integrity report reads is filled from these results ([records/_template/staging-summary.json](records/_template/staging-summary.json)); `node scripts/release/report.mjs` compiles the report with the release record (TASK-073).
- The staging stack is torn down after the release decision; its synthetic data is discarded.
