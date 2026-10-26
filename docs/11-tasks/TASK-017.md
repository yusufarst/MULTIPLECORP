# TASK-017 — Password sign-in, sessions and credential links

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Sign in with e-mail and password under the Owner-selected profile, with server-side sessions, timeouts, throttling without lockout, credential links for onboarding and reset, password confirmation and step-up, and logout — the pages PG-001 and PG-003.

**WHY THIS EXISTS:**

CAP-13 and AC-13 require secure sign-in, sessions and resets; SECURITY AU-01–AU-15 and AU-17 design them on Fortify (D-SEC-17), and GAP-043 asks the first authentication Tasks to verify the framework behaviours read only in source.

**DEPENDS ON:**

- TASK-005 — Schema foundations — IAM and OPS tables, history and types
- TASK-010 — Audit and security logging
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-015 — Design system — tokens, components, patterns and states
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-018 — Two-step verification (TOTP) and recovery codes
- TASK-022 — Jobs, files and the document renderer

**PARALLEL SAFE:** NO — it changes the authentication pipeline every page depends on

**IN SCOPE:**

- accounts and password hashing (AU-01, AU-02); the password profile AICWDF-COMPAT-8 with the local blocklist and the breached-password check, skipped and recorded when unreachable (AU-03; DIR-044 R-07)
- login without enumeration and throttling without lockout (AU-04, AU-05; WS-12)
- server-side sessions in PostgreSQL, the idle and absolute timeouts, no "remember me", termination on deactivation, concurrent sessions (AU-06–AU-10)
- credential tokens and the onboarding and reset links of AU-11–AU-12 (DEP-08), consumed on PG-003 with the token in the fragment
- self-service of AU-13 as far as the password goes (the page is TASK-023's), password confirmation and step-up of AU-15 (IP-10; INT-011), logout (INT-018)
- the prohibitions of AU-17: no impersonation, no shared identity
- the pages PG-001 and PG-003 with NAV-001 and NAV-003, ROUTE-001, ROUTE-003, ROUTE-101, ROUTE-106, ROUTE-108 and ROUTE-109 with their INT- rows
- GAP-043: the Fortify behaviours SECURITY reads in source verified against the installed version (SM-22)
- HO-20's session behaviours, each verified by a TL-05 test so that a changed version fails it: session deletion by account, the session middleware variant that skips the write for a background request, and how a rehash at login is written (RV-10) — deactivation's use of them tested with TASK-024 (TEST_STRATEGY §6)

**OUT OF SCOPE:**

- TOTP and recovery codes (TASK-018)
- Google (TASK-019)
- the first Owner and the recovery path (TASK-020)
- PG-065 (TASK-023) and the users page (TASK-024)

**REFERENCES:**

- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-01–AU-15, AU-17
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-04, WS-05, WS-12
- [SECURITY §9](../05-security/SECURITY.md#9-design-decisions) D-SEC-01–D-SEC-17
- [ROUTE_CONTRACTS §3](../03-workflows/ROUTE_CONTRACTS.md#sign-in-and-the-session-en-03-exceptions-security-au-04au-24)
- [INTERACTION_CONTRACTS §3](../03-workflows/INTERACTION_CONTRACTS.md#sign-in-and-the-session-en-03-exceptions-security-au-04au-24)
- [NAVIGATION_CONTRACTS §2.1](../07-ux-design/NAVIGATION_CONTRACTS.md#21-system-account-and-beranda) NAV-001, NAV-003
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-06–SM-08, SM-14, SM-16, SM-22
- [PERFORMANCE §10](../06-api-performance/PERFORMANCE.md#10-session-and-authentication-load) PF-37–PF-40
- [ADMIN_FLOW J-ACC-01](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#10-journeys)
- Architecture challenge — the sequence 19a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (146): O-06; CAP-13; DEP-08; REF-001; REF-056; IMG-01; ROUTE-001; ROUTE-003; ROUTE-101; ROUTE-106; ROUTE-108–ROUTE-109; INT-001; INT-010–INT-011; INT-018; INT-101; INT-106; INT-108–INT-109; RG-11; AU-01–AU-13; AU-15; AU-17; WS-04–WS-05; WS-12; LG-01; TM-01–TM-08; TM-28–TM-29; TM-42–TM-43; TM-45; TM-52; TM-58; TM-61; TM-63; TM-66–TM-69; TM-71; TM-73; D-SEC-01–D-SEC-05; D-SEC-07–D-SEC-13; D-SEC-15–D-SEC-17; H6-06–H6-07; H6-11; H6-15–H6-16; H7-01; H7-04–H7-05; H7-09; H7-12–H7-15; H7-17–H7-19; H8-16–H8-18; H9-04; H9-11; CI-09; HO-02; HO-20; HO-40; PF-37–PF-40; RB-03; RB-07–RB-08; RB-10; PG-001; PG-003; SCR-01–SCR-02; UXI-16–UXI-17; UXI-19–UXI-22; UXI-32; D-UX-03; IP-10–IP-11; J-ACC-01; MSG-19–MSG-20; MSG-27–MSG-29; MSG-33–MSG-34; UXS-26; UXH-11; PT-28; GR-12; NAV-001; NAV-003; SM-06; SM-08; SM-16–SM-17; component Sign-in compositions; GAP-043–GAP-044

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's authentication actions and Fortify configuration; the session configuration; PG-001 and PG-003

**BUSINESS RULES:**

- N/A — no business rule; SECURITY §2 is the rule set

**ARCHITECTURE RULES:**

- Fortify registers only the features AU- names; its other routes are not registered (ARCHITECTURE §1); IAM's boundary keeps the library out of the domain

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-01, Brute force and credential stuffing (AU-03, AU-05, AU-16, AU-18, WS-11, LG-07); TM-02, User enumeration through login, reset, the two-factor step, Google sign-in or timing (AU-04, AU-12, AU-22); TM-03, Session fixation, also across the steps of a two-factor or Google login (AU-06, AU-16, AU-22); TM-04, Session theft on a shared or unattended device, a previous user's Google session included (AU-06, AU-07, AU-08, WS-05, AU-22); TM-05, Credential-link interception, replay or leakage in logs, Referer, history or flashed input (AU-11, AU-12, LG-03, LG-04); TM-06, Owner-account takeover (AU-16, AU-18, AU-02, AU-03, AU-05, AU-15, AU-20, LG-07); TM-07, Host-header poisoning of links (WS-11, AU-12); TM-08, CSRF on a state change (WS-04, WS-05); TM-28, Browser history, back-forward cache or client cache after logout, expiry or remote termination (AU-06, WS-07, WS-08); TM-29, Revoked grant or deactivated account with an open session or a queued export (AZ-03, AU-09, DP-09, DP-14); TM-42, Decompression, zip or size bombs (FL-02, FL-03, FL-06, WS-12); TM-43, Expensive search or report exhaustion (WS-03, WS-12); TM-45, Shared accounts or impersonation hiding the actor (AZ-12, AU-01, AU-17); TM-52, Credential link consumed by someone other than its holder — the issuing Owner or anyone who saw the delivery message — to set the password and act as the account (AU-11, AU-12, AU-13, PJ-17, LG-02); TM-58, Password-hashing exhaustion by a distributed login flood (AU-05, WS-12, H6-12, H9-02); TM-61, A previous user's Google session on a shared office PC or floor device used to sign in (AU-22, AU-07, AU-08, AU-05); TM-63, Guessing TOTP or recovery codes from one or many addresses, concurrently or in sequence (AU-16, AU-05, AU-19, LG-07); TM-66, Theft or reuse of recovery codes — viewed on an unattended session, then used to replace the device (AU-19, AU-05, AU-20, LG-07); TM-67, Enrolment bypass or hijack — a high-risk account acting without TOTP, or a password holder enrolling a device before the person (AU-18, AU-16, AU-12, AU-13, LG-07); TM-68, Reset abuse — an Owner persuaded to reset an Admin's TOTP, or a reset or recovery link used to take an account over (AU-20, AU-12, AU-14); TM-69, Offline guessing of 8-character passwords after a database leak (AU-02, AU-03, AU-16); TM-71, The breached-password check unavailable or tampered with (AU-03, LG-07); TM-73, Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (AU-05, AU-20, AU-24, AU-10, LG-07)
- the AU- controls above; CSRF and cookies (WS-04, WS-05); rate limits on Valkey (WS-12); security events for sign-in outcomes (LG-; TASK-010)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the session carries the account only; company scope is resolved per request by TASK-011, never cached in the session beyond what AU-06 allows

**CONCURRENCY / IDEMPOTENCY:**

- sign-in is outside the command envelope (EN-03; CI-09); the session identifier regenerated at the password step (H8-17); concurrent sessions allowed (AU-10)

**AUDIT:**

- the business audit event of each account-changing identity step — a password set through a credential link, and the use of an OWNER_RECOVERY link — written in the step's own transaction: the account as actor with authority class SYSTEM, or, for an OWNER_RECOVERY link, no actor with the credential link named (PERMISSIONS_MATRIX RG-11; CONCURRENCY_IDEMPOTENCY CI-09; LG-01); its security event follows after the commit
- security events for sign-in, logout, failures, throttling, credential-link issue and use (LG-); no password or token in any record

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM's authentication boundary (ARCHITECTURE §8)
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
- Google sign-in: OFF in this Task — TASK-019 adds it
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

- Context7: REQUIRED — Fortify and the framework's session, hashing and rate limiting at the installed versions
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — the sign-in and reset journeys
- Other: the breached-password range service reached only from CI and staging with synthetic passwords
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which routes Fortify would register by default and must not
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Fortify's features and their routes; the framework's Argon2id or bcrypt driver; session regeneration
- Version-sensitive assumptions: each Fortify behaviour SECURITY reads in source re-verified (GAP-043)

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
- Design reference: PG-001 and PG-003 as INFORMATION_ARCHITECTURE §8.1 and the sign-in compositions state
- Primary action: the page's one submit
- Secondary actions: as the page's NAV- contract names
- Destructive actions: none
- Redundancy constraints: one submit per page
- Loading state: the submit's pending state, no double submission
- Empty state: N/A — a sign-in page always shows its form
- Error state: the generic answers of AU-04 and AU-05, never which part was wrong

**ROUTE IMPACT:**

- ROUTE-001, ROUTE-003 (views); ROUTE-101 (sign in), ROUTE-106 (credential link), ROUTE-108 (logout), ROUTE-109 (password confirmation)

**INTERACTION CONTRACTS:**

- INT-101, INT-106, INT-108, INT-109; INT-001 (with TASK-014), INT-010, INT-011, INT-018

**NAVIGATION CONTRACT:**

- Parent: none — unauthenticated
- Entry: the sign-in address; after "Keluar" or the absolute limit (NAV-001)
- Back action: as NAV-001 and NAV-003
- Cancel: N/A — nothing to cancel; leaving is NAV-001's back action
- Success destination: the landing page, or PG-002 or PG-004 (ROUTE-101)
- Error behavior: the generic and throttled answers in place
- State preservation: the e-mail kept, never the password
- Mobile behavior: the phone composition

**ACCEPTANCE CRITERIA:**

1. A wrong e-mail, a wrong password, an unknown account and an inactive account get the same answer, status and timing (AU-04).
2. Repeated failures are throttled per identifier and per address without locking the account (AU-05).
3. A password outside AICWDF-COMPAT-8 or on the blocklist is refused; an unreachable breached-password service is recorded and skipped (AU-03).
4. A credential link works once, expires as AU-11–AU-12 state, and carries its token only in the fragment.
5. Idle and absolute timeouts end the session; deactivation ends every session (AU-07, AU-09).
6. Fortify registers no route that the contracts do not list (SM-22).
7. Signing in again in place after a 401 or 419 lets the pending command be retried with its `command_id`: for the same account it replays rather than runs twice, and for another account it answers CONFLICT without disclosure (CI-01–CI-09; HO-02; UXH-11; UXI-16; architecture challenge 19a, with TASK-013 and TASK-016).
8. Each account-changing identity step writes its business audit event in its own transaction — the account as actor with authority class SYSTEM, or no actor with the link named for an OWNER_RECOVERY link — and its security event after the commit; a rolled-back step leaves neither (RG-11; CI-09; LG-01).
9. HO-20's session behaviours — session deletion by account, the skipped session write of a background request and how a rehash at login is written (RV-10) — each pass a TL-05 test that fails on a changed framework version, deactivation's use of them tested with TASK-024 (TEST_STRATEGY §6).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the password profile and the blocklist (TL-03; SM-16)
- Feature: every authentication flow of SM-06–SM-07 (TL-04)
- Integration: sessions in PostgreSQL and the limiter on Valkey; each identity step's audit row in its own transaction, a rollback leaving neither it nor the security event; HO-20's session deletion by account, skipped background write and rehash at login (TL-05)
- Authorization: EN-03's unauthenticated exceptions only (TL-06)
- Route integrity: ROUTE-001, -003, -101, -106, -108, -109 audited (TL-07)
- Interaction: their INT- rows (TL-08)
- Browser E2E: sign-in, reset and logout journeys (TL-09)
- Localization/language switch: both languages on PG-001 and PG-003 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12 on both pages
- Production build: TL-13
- Security: SM-06–SM-08, SM-14, SM-16, SM-22, and SM-17's path from the login page and the in-place re-login to the two-factor challenge, end to end with TASK-018 (TL-14)
- Performance: PF-37–PF-40; PFM-19 — authentication adds no business query (TL-15)
- Migration: N/A — the tables are TASK-005's
- Other: GAP-043's verification recorded

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Feature/Auth/SignInTest.php` — a wrong password, an unknown account and an inactive one answer MSG-19 alike, with 422 and no identifier recorded; each does one Argon2id verification and makes no hash, a hash of another algorithm followed by the dummy verification; the dummy hash generated from the configured driver and parameters (H9-11). Criterion 2: `SignInTest.php` — five failures a minute per identifier and address, the doubling cooldown, the address's thirty in fifteen minutes, no account locked —; `tests/Integration/Authentication/SignInRacesTest.php` — a burst of seven gets five, the cooldown started once, the places of a success and of an unverified attempt given back —; `SessionBehaviourTest.php` — the counts on Valkey under keyed hashes, the store down refusing. Criterion 3: `tests/Feature/Auth/CredentialLinkTest.php` and `tests/Unit/IAM/PasswordRulesTest.php` — AICWDF-COMPAT-8, the local list, the personal-text rule, the breached range refused and, unreachable, skipped with PASSWORD_CHECK_SKIPPED. Criterion 4: `CredentialLinkTest.php` — once, expired, revoked and unknown alike, a dead link answered before any rule, per token hash and per address —, `SignInRacesTest.php` — two submissions of one link set one password, the account locked before the link — and `tests/Browser/E2E/sign-in.spec.ts` — the token out of the address and the history. Criterion 5: `SignInTest.php` — the absolute limit, a session without its sign-in time bounded, deactivation and a password changed elsewhere end the session; the idle limit the store's lifetime (`config/session.php`). Criterion 6: `tests/Feature/BootTest.php`'s exact route list — no route of Fortify or of passkeys. Criterion 7: `tests/Interaction/SignInTest.php` — the real sign-in in place, the same account's command replayed once, another account's CONFLICT without disclosure. Criterion 8: `CredentialLinkTest.php` — the audit event in the consumption's transaction, SYSTEM, the account as actor or none with the link named for OWNER_RECOVERY, before/after with the password redacted, the security event after the commit, a rollback leaving neither. Criterion 9: `tests/Integration/Authentication/SessionBehaviourTest.php` — the session rows by account, the skipped background write, the rehash at login —, `SignInRacesTest.php` — RV-10's conditional rehash racing a reset, the session stamped with the digest of the hash it wrote —; deactivation's use of them TASK-024's

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS locally and in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS locally and in CI (TL-02)
- [x] Relevant automated tests: Unit — the password profile, the blocklist, the address keys (TL-03); Feature — every flow of SM-06–SM-07 (`tests/Feature/Auth/`, TL-04); Integration — sessions in PostgreSQL, the limiter on Valkey, the races of SM-14, HO-20's behaviours (`tests/Integration/Authentication/`, TL-05); PASS locally and in CI
- [x] Authorization: EN-03's unauthenticated exceptions only — ROUTE-001, -003, -101 and -106 `access:none`, ROUTE-108 and -109 authenticated (TL-06; the route audit)
- [x] Route integrity: ROUTE-001, -003, -101, -106, -108 and -109 in the route audit over every registered route (TL-07)
- [x] Interaction behavior: INT-001, INT-010, INT-011, INT-018, INT-101, INT-106, INT-108, INT-109 (`tests/Interaction/SignInTest.php`, the browser suite) (TL-08)
- [x] Browser E2E if required: the sign-in, reset and logout journeys on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-001 and PG-003 at every band edge in both languages (`tests/Browser/Responsive/sign-in.spec.ts`, TL-11)
- [x] Visual breathing room/density if UI: both pages on DESIGN_SYSTEM's tokens and density, the English strings unclipped at every band (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS locally and in CI
- [x] Security checks if required: SM-06–SM-08, SM-14, SM-16 and SM-22 by the tests above; TL-12's axe on both pages at 390 and 1,280 px with AU-04's answer shown; the secret-like scan and both dependency audits PASS (TL-14); SM-17's path to the two-factor challenge end to end with TASK-018
- [x] Database safety review: no migration and no schema change — the tables are TASK-005's —; the consumption's lock order users before credential_tokens (D-CC-03); local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources, nothing invented; ER-06's one independent review, its 13 points dispositioned as COMPLETION EVIDENCE's notes state

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `6ed3216`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40260163938 (push) and 40260167074 (pull request #22), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the Integration and Authorization suites and Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty). On the Owner's workstation, 2026-10-26, after ER-06's fixes: the suites Unit 264, Feature 91, Integration 898, Authorization 98, Interaction 12, Routes 11, Architecture 50 and Performance 4; TypeScript, ESLint, Pint, Larastan/PHPStan and the browser suite PASS — the sign-in specs 26 on Chromium and WebKit; WebKit's timeouts under two workers against the single-threaded local server passed with one, CI the proof
- Build result: `TL-13 Frontend build: PASS — Evidence: 6ed3216, CI ubuntu-24.04 run 40260163938`
- Route evidence: TL-07's route audit PASS — ROUTE-001 `GET /masuk`, ROUTE-101 `POST /masuk` (`access:none,login`), ROUTE-003 `GET /atur-kata-sandi` and ROUTE-106 `POST /atur-kata-sandi` (`access:none,credential_link`, the credential-link limiter), ROUTE-108 `POST /keluar` and ROUTE-109 `POST /konfirmasi-kata-sandi` (`access:self`) —; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/SignInTest.php` (INT-010, INT-011, INT-101, INT-109; criterion 7), `tests/Feature/Auth/` (INT-106, INT-108), the browser suite (INT-001, INT-018)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 6ed3216, CI ubuntu-24.04 run 40260163938`; TL-10, TL-11 and TL-12 PASS in the same run
- Localization evidence: PG-001 and PG-003 in both languages, the switch keeping the e-mail and never the password, and PG-003's token in memory (`tests/Browser/Localization/sign-in.spec.ts`) (DOD-16); 9 keys added through `lang/registry/added-keys.json` for the Owner's review
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — `laravel/fortify` 1.41.0 and its dependencies MIT, project-local; nothing hosted
- Files changed: `composer.json`, `composer.lock`; `config/hashing.php`, `config/fortify.php`, `config/session.php`, `.env.example`, `phpunit.xml`; `bootstrap/app.php`, `bootstrap/providers.php`; `routes/web.php`, `routes/fixtures.php`; `docker/nginx/default.conf`; `app/Http/Responses/SystemAnswer.php`; `app/Modules/IAM/Authentication/`, `app/Modules/IAM/Http/Controllers/`; `app/Console/Commands/PurgeCredentialLinks.php`; `resources/js/Pages/Auth/`, `resources/js/lib/navigation.ts`; `lang/id.json`, `lang/en.json`, `lang/registry/added-keys.json`; `tests/Feature/`, `tests/Integration/Authentication/`, `tests/Interaction/SignInTest.php`, `tests/Performance/SignInQueriesTest.php`, `tests/Unit/IAM/`, `tests/Browser/`, `tests/Support/HashProbe.php`, `tests/Support/Fixtures/AccountFixture.php`; TOOLCHAIN's row; this file, the statuses of TASK-018 and TASK-022, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `7343a1b`, `e7190d7`, `9b3cfd5`, `d744aba` and `6ed3216`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-017-sign-in`, after DIR-096's two governance commits `7be00eb` and `6c3f328`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-26 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each Fortify and framework behaviour re-read in the installed source and pinned by a test (GAP-043; TOOLCHAIN's TASK-017 row); the local runs used a container-local copy of the working tree, CI the final proof. ER-06's one review found 13 points: the dummy hash made per request, an existence oracle in timing (AU-04; H9-11); the rehash not RV-10's conditional update; AU-05's counts checked before they were taken (H6-07); the consumption's lock order inverted (D-CC-03); no verification cap (H6-12; PF-39); a dead link told from an unknown one by the personal-text rule (AU-12); a session without its sign-in time unbounded (AU-07); the browser's own session kept by a set password (AU-06, AU-15); no purge (H9-04); a hash of another algorithm failing fast; no before/after in the consumption's audit (RG-11); the hour's key an unkeyed hash and an IPv4-mapped address one bucket; and tests short of their claims — each fixed and tested; the step-up throttle, the eleventh, now counted atomically within its own slot, AU-15 asking only for throttling and logging, so no escalating cooldown is added. Codex's review of `d744aba` found one P2 — PG-003's throttled submissions answered a bare 429, which the form cannot show —, fixed in `6ed3216`: the route's limiter and the verification cap answer MSG-20 on the link alert, the token kept in memory, with its tests. The purge's cron entry is TASK-072's. Readings within the approved text are listed in CURRENT_HANDOFF. The READY gates of the Tasks TASK-017 blocks were re-checked at this DONE: TASK-018 and TASK-022 READY — every dependency DONE. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-018 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- register a Fortify feature SECURITY turns off — registration, reset by e-mail, profile update, passkeys
- log or store a password, token or link in clear
