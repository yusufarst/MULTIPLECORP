# TASK-070 release regression record

Status: REVIEW | Updated: 2026-11-20 | Owner: Planning

*Written by the executor of [TASK-070](../../11-tasks/TASK-070.md) under [DIR-127](../../00-governance/DECISION_LOG.md#dir-127--the-owners-resume-and-the-verification-and-retry-policy-2026-11-20) and [DIR-128](../../00-governance/DECISION_LOG.md#dir-128--the-owners-code-first-directive-2026-11-20). It records Layer 3 on the release candidate in fresh CI, the inputs of the release integrity report section by section (RELEASE_PLAN §11), the requirement-to-test traceability over the FEATURE_COVERAGE_MATRIX, the pre-mortem causes PMC-01–PMC-15 (RELEASE_PLAN §12.1), and the staging and UAT parts prepared for the operator and the validators. Synthetic data only; no host, secret or credential appears here.*

## 1. Layer 3 in fresh CI

- **The release candidate** is `main` after TASK-069 (`2ad5f63`) with TASK-070's records and its traceability script — no application code changed since. Its code was regressed in full by CI run 44763287325 on `ccdc3ee`, whose tree is `2ad5f63`'s: every job PASS — Verify (the repository gates, TL-01–TL-08, TL-13, TL-14), the four browser layers on Chromium and WebKit with their aggregate (TL-09–TL-12: every critical journey CJ-01–CJ-22 but CJ-16 at the phone and desktop widths, the localization, responsive and accessibility audits of every page), Performance and migration (TL-15, TL-16 with the previous release on the expanded schema) and the deployment definition (TL-05 on the Coolify stack). This head's own run, the same layers again with the coverage traceability added, is cited in the squash commit's message (DIR-127).
- **The major roles**: the Owner, an Admin of each grant shape of SECURITY_TEST_MATRIX §2 and the new Admin of RG-04, through the journeys and TASK-069's route crawl, declarations and SM-04 refusals at every capability route.
- **The critical integrations**: the renderer (TL-05's renditions on the real renderer), the queue and the scheduler's sweeps, exports end to end, the opening import, the breached-password check and Google sign-in both simulated in CI; Google's real redirect is the operator's on staging (S-03 of the [staging runbook](../../10-release/STAGING_LAYER3_RUNBOOK.md)).
- **`ReconcileGuards`** reports no difference after every integration test (TS-11) and at the end of every journey; SM-05's OD-16 reconciliation returns no row after every authorization and integration test but F-04's consequences held open ([TASK-069 record](TASK-069_SECURITY_RECORD.md)).
- **The verification command** runs on CI's clean runner (DIR-128 replaced its local run). No test was weakened, skipped or deleted; CJ-16 is skipped in CI by design and runs on staging (S-01).

## 2. The release integrity report's inputs

| Section | Input on the release candidate | Reads |
| --- | --- | --- |
| TASKS | TASK_PLAN: 95 Tasks; DONE 79 once this Task merges; BLOCKED 4 on the operator's and the Owner's inputs (TASK-071, TASK-075, TASK-081, TASK-084); TASK-072 IN_PROGRESS waiting on TASK-071; TODO 11 | FAIL until every required Task is DONE — the release stays NO-GO |
| ROUTES | TASK-069's crawl: 220 routes, broken 0, unexpected 404, 405 and 500 0; SM-02's audit and the contract register one to one, ROUTE-277's path held open (F-01) | PASS in CI; F-01 for the Owner's decision |
| INTERACTIONS | TL-08 PASS; 174 of 176 INT- and 65 of 65 NAV- contracts with their passing tests, INT-277 and INT-278 TASK-072's | PASS in CI for every built control |
| CRITICAL JOURNEYS | CJ-01–CJ-22 but CJ-16 PASS on Chromium and WebKit (TL-09); CJ-16 on staging (S-01) | PASS in CI; CJ-16 NOT RUN until staging |
| UI/UX | TASK-065's gate with TASK-066's and TASK-067's items entered (§5); TL-10–TL-12 PASS; the gate's findings GF-01 and GF-04–GF-13 for the Owner's decision, GF-01 to resolve before production approval | REVIEW until the Owner decides GF-01 and GF-04–GF-13 |
| BROWSER | every journey's console and network capture in CI: critical console errors 0, critical failed requests 0 (TL-09 fails a journey otherwise); staging's captures from S-02 | PASS in CI; staging NOT RUN |
| BUILD | TL-13 PASS — the frontend build and the production caches and configuration check | PASS |
| SECURITY | TASK-069: every SECURITY_TEST_MATRIX row with its passing tests in CI; the staging-only cases NOT RUN (S-03–S-06); F-03 and F-04 for the Owner's decision | PASS in CI; staging NOT RUN |
| PERFORMANCE | TASK-068: QB-01–QB-17 within budget in CI (TL-15); PFM-01's times, PFM-12, PFM-13 and PFM-16 on staging (S-07–S-09); GF-14, TL-15's 30-minute overrun, an open risk | PASS in CI; staging NOT RUN |
| TOOLCHAIN, COST, DATABASE, INTEGRITY | compiled with the release record by TASK-073's `scripts/release/report.mjs`: no recurring cost added by TASK-062–TASK-070; no migration in TASK-065–TASK-070 | at the release |

## 3. Requirement-to-test traceability (GAP-024; criterion 4)

`scripts/release/coverage-trace.mjs`, run by the Verify job as *Release coverage traceability (TASK-070; GAP-024)*, reads all 3,259 FEATURE_COVERAGE_MATRIX entries on this head, TASK-070 DONE: **2,896 traced** through Tasks that are all DONE — each DONE Task having met its acceptance criteria with passing tests (DEFINITION_OF_DONE) —, 1,683 of them also cited by a test themselves; **47 dispositions** checked against §3 (DEFERRED 6, FUTURE 13, SATISFIED 3, DROPPED 3, GOVERNANCE 22); **316 open**, each with the Task it waits on — TASK-071 20, TASK-072 60, TASK-074 46, TASK-075 18, TASK-076 65, TASK-077 39, TASK-078 7, TASK-079 43, TASK-080 13, TASK-081 29, TASK-082 9, TASK-083 11, TASK-084 53, TASK-085 18, TASK-086 41, TASK-087 38 (an entry mapped to several Tasks counts with each). No hole is filled by assumption: an open entry stays open until its Task is DONE.

## 4. Staging and UAT, prepared

- [STAGING_LAYER3_RUNBOOK](../../10-release/STAGING_LAYER3_RUNBOOK.md): S-01–S-10 — CJ-16, the Layer 3 journeys, SM-20, SM-08, SM-07 and SM-19, SM-21, PFM-01, PFM-13, PFM-12 and PFM-16, the backup's restore —, each with its command, expected result and evidence; run by the operator only.
- [UAT_PREPARATION](../../10-release/UAT_PREPARATION.md): the device and browser matrix, the real-device scripts R-01–R-09 (S2-J01, S2-J03, S2-J05 at 390 and 320 CSS px, UT-06, UT-12, UT-18–UT-24, the composer, scanners, printing, files, device time), the UAT items with their scripts (UXH-08, HO-35, DOC-01–DOC-14 with FX-10, DEP-11, OS-01, the per-page copy lists, UAT-01–UAT-04) and the record form; run by the validators in TASK-086.

## 5. The suites' joins (criterion 9)

[TASK-065's gate record](TASK-065_UI_GATE_RECORD.md) now answers every one of AICWDF §27's twenty-one items for every page: the language items from TASK-067's audit of every page state in both languages (PASS 85 on each engine) and the accessibility critical checks from TASK-066's audit (PASS 85 on each engine), entered by this Task; back and navigation through the 65 NAV- contracts' passing tests; destructive action safety through the INT- contracts (TL-08).

## 6. The pre-mortem causes (criterion 10)

| Cause | Detection on the release candidate | Agent Tasks named — DONE | Still to come, for the go-live checklist |
| --- | --- | --- | --- |
| PMC-01 rules that do not fit | CJ-01–CJ-08, CJ-13, CJ-17 PASS in CI | 031, 035, 055, 056, 058, 062, 063 | UAT-03 and UAT-04 (TASK-086); GLC-15, GLC-17 |
| PMC-02 money | §5's integrity tests on FX-01 and FX-03, CJ-04, CJ-08, CJ-14 PASS; `ReconcileGuards` clean | 003, 009, 013, 036, 049, 054, 060, 063 | the nightly reconciliation's alert (TASK-072, TASK-075) |
| PMC-03 stock | §6's race tests, CJ-05, CJ-07 PASS; `ReconcileGuards` clean | 008, 013, 038, 043, 059, 062 | — |
| PMC-04 cost and profit | §5's conservation tests, CJ-05, CJ-14 PASS | 036, 037, 042, 044, 048, 054, 060, 063 | — |
| PMC-05 isolation and authorization | SM-01, SM-02, SM-04, SM-12, SM-13, SM-25, CJ-09 PASS | 011, 012, 016, 024, 061, 064, 069 | — |
| PMC-06 accounts and the Owner | SM-06, SM-07, SM-16–SM-22, SM-24, CJ-10 PASS in CI | 017, 020, 023 | SM-07, SM-19, SM-21 on staging (S-05, S-06); TASK-076, TASK-080, TASK-085; GLC-09, GLC-10, GLC-13, GLC-23 |
| PMC-07 duplicated or lost commands | §6's replay, lost-response and duplicate tests, CJ-21 PASS | 013, 021, 022, 051, 064 | the failed-job and owed-work alerts (TASK-075) |
| PMC-08 the opening state | IMP-11's reconciliation tests, FX-09 PASS | 057 | CJ-16 and UAT-02 on staging; TASK-077, TASK-081, TASK-087; CUT-01–CUT-07; GLC-14 |
| PMC-09 a release breaking production | the health and smoke checks and the deployment definition (TL-05) PASS; the previous release on the expanded schema (TL-16) PASS | 073 | TASK-072's DONE, TASK-081, TASK-084; RBK-01–RBK-06 |
| PMC-10 data lost | — in CI | — | TASK-074, TASK-076, TASK-081, TASK-084; the restore drill S-10; GLC-04–GLC-06, GLC-20, GLC-22, GLC-25, GLC-26 |
| PMC-11 failures unnoticed | the heartbeat's checks in CI (TASK-072's parts) | — | TASK-072, TASK-075, TASK-076, TASK-080, TASK-083; GLC-03, GLC-11, GLC-21 |
| PMC-12 too slow | TASK-068's budgets PASS in CI | 068 | PFM-01, PFM-12, PFM-13, PFM-16 on staging (S-07–S-09, TASK-079); PFM-17 on the devices (R-09); GLC-05 |
| PMC-13 not adopted | TASK-065's gate, TASK-066's audit, TASK-067's copy lists PASS | 014, 016, 059, 065, 067, 069, 070 | the real-device checks and UAT-03, UAT-04 (TASK-086); GLC-15, GLC-17 |
| PMC-14 wrong documents | CJ-15, SM-10's renderer tests PASS | 022, 025, 029, 049, 063 | DOC-01–DOC-14 with FX-10 in the UAT (TASK-086); the FAILED-rendition alert (TASK-075) |
| PMC-15 agents lose the thread | the plan check, CI on every pull request, the traceability checks of TASK-069 and this Task PASS | 001, 003, 073, 070 | the Owner's merge of each release (ER-07) |

Every cause has its detecting test or check: in CI where CI can detect it, otherwise in the staging runbook or the UAT, prepared here. The agent Tasks each cause names that are not DONE are the Tasks after this one in the plan — TASK-072 and TASK-074–TASK-087, which wait on TASK-071's operator inputs or on this Task —, listed above for the go-live checklist; no cause lacks its detection.

## 7. Failures

The regression found no new failure on the release candidate: run 44763287325 passed every job. The open items stay with their owners: F-01, F-03 and F-04 ([TASK-069 record](TASK-069_SECURITY_RECORD.md)), GF-01–GF-13 ([TASK-065 record](TASK-065_UI_GATE_RECORD.md)) for the Owner's decision; GF-14 an open risk (DIR-124); the screen-reader scripts SR-01–SR-07 for TASK-086.

## 8. The criteria

1 — Layer 3 in fresh CI on the release candidate, every check PASS (§1). 2 — every layer TL-01–TL-16 PASS, `ReconcileGuards` clean, no test weakened (§1). 3 — the report's inputs for TASKS, ROUTES, INTERACTIONS, CRITICAL JOURNEYS, UI/UX, BROWSER, BUILD, SECURITY and PERFORMANCE with their evidence, no planned check reported as run (§2). 4 — every matrix entry traced or reported open with its Task, every disposition checked (§3). 5 — the staging runbook (§4). 6 and 7 — the device UAT's matrix, scripts, items and record form (§4). 8 — no new failure; the open findings with their owners (§7). 9 — the gate's twenty-one items answered for every page (§5). 10 — every pre-mortem cause with its detection, the Tasks after this one listed for the go-live checklist (§6).
