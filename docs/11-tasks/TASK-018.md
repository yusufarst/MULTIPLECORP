# TASK-018 — Two-step verification (TOTP) and recovery codes

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Two-step verification by TOTP, mandatory for every high-risk account and optional for the others: enrolment in the enrolment-only session, the challenge, recovery codes, the account-wide second-factor limit, the reset and recovery contract and the key management of the secrets — the pages PG-002 and PG-004.

**WHY THIS EXISTS:**

The Owner and every account with an ADM+ grant or `finance.view`, `cost.view`, `profit.view` or `evidence.view` must use TOTP (AU-16, AU-18; D5; DIR-042 K1); GAP-033 stays open until its authentication evidence exists, and GAP-044 until the login journeys are tested with TOTP.

**DEPENDS ON:**

- TASK-017 — Password sign-in, sessions and credential links

**BLOCKS:**

- TASK-019 — Google sign-in for existing accounts
- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-074 — Backup tooling, the off-server copy and the drill procedures

**PARALLEL SAFE:** NO — it changes the sign-in pipeline of TASK-017

**IN SCOPE:**

- AU-16: six-digit codes, 30-second period, one step either side set explicitly, no "remember this device", the enrolment secret held encrypted only in the session for at most 15 minutes, a code accepted only at a later time step
- AU-18: the high-risk classification and the enrolment-only session (PG-004)
- AU-19: eight recovery codes, their use (ROUTE-105) and regeneration
- AU-20: the reset and recovery contract with its paths, as far as the account and the challenge go; AU-21: the secrets and codes encrypted with the application key, rotation through the previous-keys list
- the account-wide second-factor limit (DIR-044 R-01)
- the pages PG-002 and PG-004 with NAV-002 and NAV-004, ROUTE-002, ROUTE-004, ROUTE-104, ROUTE-105, ROUTE-107 and their INT- rows
- the open item ROUTE_CONTRACTS §4 leaves to P11 for "Kembali" on PG-002, closed at Level 1 in run PC-5: the pending attempt is discarded on the server when PG-001 is requested (ROUTE-001's view), and expires at its own limit otherwise — no route is added (NAV-002; OQ-14 as DIR-069 decided it)
- GAP-043 and GAP-044 for the two-factor flows

**OUT OF SCOPE:**

- enrolment changes from PG-065 (TASK-023) and the Owner's reset of another account's TOTP (TASK-024)
- the key-compromise incident command (TASK-020, TASK-076)

**REFERENCES:**

- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-16, AU-18–AU-21
- [SECURITY §9](../05-security/SECURITY.md#9-design-decisions) D-SEC-16, D-SEC-18–D-SEC-20
- [ROUTE_CONTRACTS §3](../03-workflows/ROUTE_CONTRACTS.md#sign-in-and-the-session-en-03-exceptions-security-au-04au-24) and [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own)
- [NAVIGATION_CONTRACTS §2.1](../07-ux-design/NAVIGATION_CONTRACTS.md#21-system-account-and-beranda) NAV-002, NAV-004
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-17–SM-19, SM-21
- [CONCURRENCY_IDEMPOTENCY §18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-39
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-033--the-owner-account-has-no-second-authentication-factor-in-v1) GAP-033
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (66): O-06; CAP-13; ROUTE-002; ROUTE-004; ROUTE-104–ROUTE-105; ROUTE-107; INT-104–INT-105; INT-107; C-65; RG-11; AU-16; AU-18–AU-21; LG-01; TM-01; TM-03; TM-06; TM-57; TM-63–TM-70; TM-72–TM-73; D-SEC-16–D-SEC-20; H6-13; H6-15; H7-15; H7-17–H7-18; H8-02; H8-17–H8-19; H8-21–H8-22; CI-09; M-39; PG-002; PG-004; SCR-01–SCR-02; GR-12; NAV-002; NAV-004; SM-02; SM-17–SM-19; SM-21; component Sign-in compositions; GAP-033; GAP-043–GAP-044

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's two-factor actions and Fortify's two-factor configuration; PG-002 and PG-004

**BUSINESS RULES:**

- N/A — SECURITY §2 is the rule set

**ARCHITECTURE RULES:**

- Fortify's own two-factor routes not registered where SECURITY replaces them (TM-65); the confirmation writes secret, codes and time step in one transaction under the account row lock (H6-15)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-01, Brute force and credential stuffing (AU-03, AU-05, AU-16, AU-18, WS-11, LG-07); TM-03, Session fixation, also across the steps of a two-factor or Google login (AU-06, AU-16, AU-22); TM-06, Owner-account takeover (AU-16, AU-18, AU-02, AU-03, AU-05, AU-15, AU-20, LG-07); TM-57, Operator abuse of the Owner-recovery command, which now also resets TOTP and unlinks Google, and of the OWNER-restoration command, whose honest use also reverses a removal of a pre-window Owner made legitimately during the compromise window, as a restored state does for an Owner removed legitimately between its backup and the window's start (AU-14, AU-20, AU-25, LG-07, H9-06, H9-07, H9-14, RV-04); TM-63, Guessing TOTP or recovery codes from one or many addresses, concurrently or in sequence (AU-16, AU-05, AU-19, LG-07); TM-64, Replay of an observed TOTP code, concurrently or later (AU-16, H6-13); TM-66, Theft or reuse of recovery codes — viewed on an unattended session, then used to replace the device (AU-19, AU-05, AU-20, LG-07); TM-67, Enrolment bypass or hijack — a high-risk account acting without TOTP, or a password holder enrolling a device before the person (AU-18, AU-16, AU-12, AU-13, LG-07); TM-68, Reset abuse — an Owner persuaded to reset an Admin's TOTP, or a reset or recovery link used to take an account over (AU-20, AU-12, AU-14); TM-69, Offline guessing of 8-character passwords after a database leak (AU-02, AU-03, AU-16); TM-70, Real-time phishing that relays the password, or a Google sign-in, together with the TOTP code (AU-16, AU-22, LG-07); TM-72, One phone holding both the person's Google session and the authenticator, so that whoever holds it unlocked can pass both steps (AU-22, AU-20, AU-24, LG-07); TM-73, Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (AU-05, AU-20, AU-24, AU-10, LG-07)
- a confirmed secret never shown again; the QR code rendered locally from non-persisted flash data (AU-16, AU-12's rule); one code accepted once, in sequence and concurrently (M-39; C-65)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the account itself; no company data on these pages

**CONCURRENCY / IDEMPOTENCY:**

- `two_factor_last_step` updated conditionally so one code cannot pass twice (RV-12; M-39); confirmation under the account row lock (H6-15)

**AUDIT:**

- the business audit event of each account-changing step — TOTP enrolment and replacement, recovery-code use and regeneration — written in the step's own transaction, the account as actor with authority class SYSTEM (PERMISSIONS_MATRIX RG-11; CONCURRENCY_IDEMPOTENCY CI-09; LG-01), its security event following after the commit
- security events for enrolment, challenge outcomes, recovery-code use and regeneration and the limit (LG-07; H9-15)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM's authentication boundary
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: PROPOSED — builds the designed controls of SECURITY §2, nothing beyond them
- Authentication implementation: LARAVEL_NATIVE — Fortify and Socialite (D-SEC-17; [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: AICWDF-COMPAT-8 with the common and breached-password blocklist (AU-03; D-SEC-15)
- Google sign-in: OFF in this Task — TASK-019 adds it, always followed by this challenge
- Google registration behavior: EXISTING_ACCOUNT_ONLY — no auto-registration (AU-22)
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO — Fortify and Socialite stay behind IAM's authentication boundary (ARCHITECTURE §8)

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Fortify's two-factor implementation and its routes at the installed version
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — enrolment, challenge and recovery-code journeys
- Other: a TOTP generator in tests with a controlled clock
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which Fortify two-factor routes must stay unregistered
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Fortify's two-factor confirmation and recovery codes; the TOTP library's window and period settings
- Version-sensitive assumptions: the library's defaults are not relied on — period and window set explicitly (AU-16)

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — the switch on the sign-in pages (INT-001)
- Plain-language Indonesian: YES — the MSG- texts as written
- Language switch impact: keeps the typed e-mail, never a secret
- Mobile-first: YES
- Responsive: YES
- Adaptive: N/A — one sign-in composition per band
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the sign-in compositions of DESIGN_SYSTEM §10
- Design reference: PG-002 and PG-004 as INFORMATION_ARCHITECTURE §8.1 and the sign-in compositions state
- Primary action: the page's one submit
- Secondary actions: as the page's NAV- contract names
- Destructive actions: none
- Redundancy constraints: one submit per page
- Loading state: the submit's pending state, no double submission
- Empty state: N/A — a sign-in page always shows its form
- Error state: the generic answers of AU-04 and AU-05, never which part was wrong

**ROUTE IMPACT:**

- ROUTE-002, ROUTE-004 (views); ROUTE-104 (the code), ROUTE-105 (a recovery code), ROUTE-107 (confirming enrolment)

**INTERACTION CONTRACTS:**

- INT-104, INT-105, INT-107; "Kembali" on PG-002 as ROUTE_CONTRACTS §4 and NAV-002 state

**NAVIGATION CONTRACT:**

- Parent: PG-001
- Entry: after the password or Google step (NAV-002); an enrolment-only session (NAV-004)
- Back action: "Kembali" → PG-001 with the e-mail kept, the pending attempt discarded on the server when PG-001 is requested
- Cancel: as "Kembali"
- Success destination: the landing page; PG-004 for an account that must enrol
- Error behavior: the generic and cooldown answers in place; an expired attempt → PG-001
- State preservation: the e-mail only
- Mobile behavior: the phone composition

**ACCEPTANCE CRITERIA:**

1. A high-risk account cannot reach any page but PG-004 until its TOTP is confirmed (AU-18).
2. A code at or before the last accepted step is refused, and one code submitted twice — in sequence or concurrently — passes once (M-39).
3. Recovery codes are eight, each usable once, and regenerating them voids the old set (AU-19).
4. An abandoned enrolment leaves nothing stored; a confirmed secret is never shown again (AU-16).
5. TOTP enrolment and replacement, and each recovery-code use and regeneration, write their business audit event in the step's own transaction and their security event after the commit; a rolled-back step leaves neither (RG-11; CI-09; LG-01).
6. "Kembali" on PG-002 returns to PG-001 with the e-mail kept, and the pending attempt no longer completes a sign-in.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: period, window and last-step rules with a controlled clock (TL-03)
- Feature: SM-17–SM-19 flows (TL-04)
- Integration: the concurrent double submission of one code (TL-05; M-39); each step's audit row in its own transaction, a rollback leaving neither it nor the security event
- Authorization: the enrolment-only session reaches PG-004 alone (TL-06)
- Route integrity: ROUTE-002, -004, -104, -105, -107 audited; SM-02's enrolment-only cases — an enrolment-only route refusing a full session and reaching nothing else — and no Fortify two-factor route registered without the project route that replaces it (TL-07)
- Interaction: their INT- rows (TL-08)
- Browser E2E: enrolment, challenge and recovery journeys (TL-09)
- Localization/language switch: both languages (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: the code entry's labelling and errors (TL-12)
- Production build: TL-13
- Security: SM-17–SM-19 and SM-21's key cases (TL-14)
- Performance: PFM-19 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-043 and GAP-044 for these flows

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Feature/Auth/TwoFactorTest.php` — an Owner without TOTP signs in to an enrolment-only session that reaches PG-004, ROUTE-109 and logout and nothing else, a GET redirected to PG-004 and anything else refused, sharing the display name alone; a high-risk account's session not established with TOTP ended (TOTP_REQUIRED) —, `tests/Authorization/TwoFactorRequirementTest.php` — K1 on the resolver's inputs: the OWNER role, an effective ADM_PLUS capability and again once its revoked prerequisite is granted again, each of the four views, a company in scope, never an inactive account —, `tests/Interaction/TwoFactorTest.php` (SM-02's enrolment-only cases) and `tests/Browser/E2E/two-factor.spec.ts`. Criterion 2: `tests/Unit/IAM/TotpTest.php` — six digits, 30 seconds, one step either side, a step later than the last only, with a controlled clock —; `TwoFactorTest.php` — the same code again, an older one and a wrong one answered alike —; `tests/Integration/Authentication/TwoFactorSessionsTest.php` — two real PostgreSQL sessions with a barrier before the update, one ACCEPTED and one STALE (M-39) —; `TwoFactorRacesTest.php` — the race at the row lock. Criterion 3: `TwoFactorTest.php` — eight codes, each once, after a password only, an emptied set an encrypted empty list —, `TwoFactorSessionsTest.php` — one code from two pending challenges removed once, then refused (M-40) —, and the enrolment's confirmation writing eight new codes over the old in path b. Criterion 4: `TwoFactorTest.php` — the secret held encrypted in the session and nothing stored before the confirmation, a lapsed enrolment's secret replaced, the QR code, the text key and the codes as flash data and never a prop, PG-004 never shown after the confirmation. Criterion 5: `TwoFactorTest.php` — TOTP_ENROLLED, TOTP_REPLACED and RECOVERY_CODE_USED with their audit events in the step's transaction, the account as actor under SYSTEM; a rollback of the enrolment's confirmation and of a recovery-code use leaving neither. Criterion 6: `TwoFactorTest.php` and `two-factor.spec.ts` — "Kembali" to PG-001 with the e-mail kept, the attempt discarded by ROUTE-001's view and completing nothing

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS locally and in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS locally and in CI (TL-02)
- [x] Relevant automated tests: Unit — the period, the window and the last-step rule with a controlled clock (TL-03); Feature — SM-17–SM-19's flows and the second-factor limit with its cooldown, escalation and durable floor (TL-04); Integration — M-39 and M-40 under real sessions, the limit's count on Valkey under concurrent processes, the session row of a pending challenge (TL-05); PASS locally and in CI
- [x] Authorization: the enrolment-only session reaches PG-004 alone; K1's high-risk test (TL-06)
- [x] Route integrity: ROUTE-002, -004, -104, -105 and -107 in the route audit, which refuses any route of Fortify's controllers; SM-02's enrolment-only cases (TL-07)
- [x] Interaction behavior: INT-104, INT-105, INT-107 and the in-place re-login's code step (TL-08)
- [x] Browser E2E if required: the challenge, recovery and enrolment journeys on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-002 and PG-004 at every band edge (`tests/Browser/Responsive/two-factor.spec.ts`, TL-11)
- [x] Visual breathing room/density if UI: both pages on DESIGN_SYSTEM's tokens and density, one narrow column (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS locally and in CI
- [x] Security checks if required: SM-17–SM-19 and SM-21's key case — secrets read through `APP_PREVIOUS_KEYS` —; TL-12's axe on PG-002 with its answer and PG-004 with its QR code at 390 and 1,280 px; the secret-like scan and both dependency audits PASS (TL-14)
- [x] Database safety review: no migration and no schema change — TASK-005's four TOTP fields written together (C-62) —; every code step one transaction under the account row (LK-03); local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources, nothing invented; ER-06's one independent review, its eight points fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `1a0e557`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40294350383 (push) and 40294356575 (pull request #23), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the Integration and Authorization suites and Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty). On the Owner's workstation, 2026-10-26, after ER-06's fixes: the suites Unit, Feature, Integration, Authorization, Interaction, Routes, Architecture and Performance, TypeScript, ESLint, `tests/Frontend/` 30, Pint, Larastan/PHPStan, the production build and the browser suite PASS — Unit 270, Feature 116, Integration 906, Authorization 105, Interaction 15, Routes 11, Architecture 50 and Performance 4, IndexRegisterTest's one QueryException under the workstation's load passing on its rerun; the two-factor, sign-in and shell browser specs 82 on Chromium and WebKit
- Build result: `TL-13 Frontend build: PASS — Evidence: 1a0e557, CI ubuntu-24.04 run 40294350383`
- Route evidence: TL-07's route audit PASS — ROUTE-002 `GET /masuk/verifikasi`, ROUTE-104 `POST /masuk/verifikasi` and ROUTE-105 `POST /masuk/verifikasi/pemulihan` (`access:none,two_factor_challenge`), ROUTE-004 `GET /verifikasi-dua-langkah` and ROUTE-107 `POST /verifikasi-dua-langkah` (`access:enrolment`) —; no route of Fortify's controllers; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/TwoFactorTest.php` (INT-104, INT-105, INT-107; IP-11's code step)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 1a0e557, CI ubuntu-24.04 run 40294350383`; TL-10, TL-11 and TL-12 PASS in the same run
- Localization evidence: PG-002 in both languages from PG-001's `bahasa` (NV-07), PG-004 in the account's own language (AU-13) (`tests/Browser/Localization/two-factor.spec.ts`) (DOD-16); 8 keys added through `lang/registry/added-keys.json` for the Owner's review
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted
- Files changed: `app/Modules/IAM/Authentication/` — the steps, the challenge, the limit, TOTP, the session handler, the pipeline's second-factor step and the session guard —, `app/Modules/IAM/Http/Controllers/`, `app/Modules/IAM/Services/EffectiveCapabilities.php`, `app/Modules/IAM/Data/Authority.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Middleware/RequireStepUp.php`, `app/Support/SecurityLog/`; `routes/web.php`; `resources/js/Pages/Auth/`, `resources/js/lib/session-guard.tsx`, `resources/js/lib/i18n.ts`, `resources/js/components/session-dialogs.tsx`; `lang/id.json`, `lang/en.json`, `lang/registry/added-keys.json`; `phpunit.xml`; `tests/`; TOOLCHAIN's row; this file, the statuses of TASK-019 and TASK-021, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `dc9f3fb`, `bd21192`, `bddcb22` and `1a0e557`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-018-totp`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-26 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each Fortify, TOTP-library, QR and framework behaviour re-read in the installed source and pinned by a test (GAP-043; TOOLCHAIN's TASK-018 row); the local runs used a container-local copy of the working tree, CI the final proof. The second-factor limit's atomic operation is one read and write of the account's entry under the limiter store's lock, rather than a script of the store, with the same counts, seeding and decay (AU-05). The code steps' throttles per account and address and per address are AU-05's login throttles, counting failures alone: a first version on the framework's route limiter counted successes too, which a shared office address would have filled — found by the browser suite and replaced. ER-06's one review found no HIGH point and eight others: the session's digest of the enrolment (AU-06), the tests' real sessions and their gaps (M-39, M-40, K1, SM-21, the floor's partial count), the failure recorded before the count and a breach that cannot be recorded voiding the challenge, a state changed under the lock voiding rather than counting, the step-up cleared after path b, a lapsed enrolment secret dropped, the OWNER role read once with the resolver's query, PG-004's language switch removed — each fixed and tested. Codex's review of `bddcb22` found one P2 — a breach rebuilt from the durable floor to the second, so its own failures of that second were seeded into the next window and kept a correct code refused after the cooldown —, fixed in `1a0e557` with its test. CI on this record's first commit `d6ebeb6` failed once: M-39's real-session test compared the stored step with the clock's after the race, and the real clock had crossed a step boundary; the test now fixes the step first — a test-only change, the code as tested on `1a0e557`. Readings within the approved text are listed in CURRENT_HANDOFF. The READY gates of the Tasks TASK-018 blocks were re-checked at this DONE: TASK-019 and TASK-021 READY — every dependency DONE —; TASK-020 waits on TASK-019, TASK-074 on TASK-021, TASK-022, TASK-029 and TASK-072. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-019 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- offer "remember this device" or show a confirmed secret again
- add a route for the pending attempt's voiding — the Level-1 closure uses ROUTE-001's view
