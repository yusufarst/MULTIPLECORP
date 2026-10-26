# TASK-019 — Google sign-in for existing accounts

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Google sign-in for existing accounts only — the OpenID Connect authorization flow, linking by the account holder, the identity lifecycle and unlink contract —, always followed by the TOTP challenge and never defining roles, capabilities or company scope.

**WHY THIS EXISTS:**

D6 and K2 make Google a convenience for existing accounts with active TOTP (AU-22–AU-24; AZ-13); the production clients are the Owner's (GAP-042), so this Task builds and tests the flow with a faked provider and Google stays off where no client exists (GLC-09).

**DEPENDS ON:**

- TASK-018 — Two-step verification (TOTP) and recovery codes

**BLOCKS:**

- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-082 — Google OAuth clients for staging and production

**PARALLEL SAFE:** NO — it changes the sign-in pipeline

**IN SCOPE:**

- ROUTE-102 and ROUTE-103 with INT-102 and INT-103: the redirect and the callback with state, nonce and PKCE as AU-22 states, one generic Google failure
- existing accounts only, no auto-registration; the account must have active TOTP and always passes the challenge of TASK-018
- the link and its lifecycle (AU-23, AU-24): one active link per provider subject (BD-19); the unlink contract
- Socialite configured per environment from environment variables, the provider faked in tests; Google sign-in off wherever no client is configured
- GR-13: the Google button to Google's guidelines with its font self-hosted (WS-08)

**OUT OF SCOPE:**

- creating the OAuth clients — the Owner's (TASK-082)
- linking and unlinking from PG-065 (TASK-023) and the Owner's unlink of another account (TASK-024)

**REFERENCES:**

- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-22–AU-24
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-13
- [ROUTE_CONTRACTS §3](../03-workflows/ROUTE_CONTRACTS.md#sign-in-and-the-session-en-03-exceptions-security-au-04au-24)
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-19, SM-20
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-10
- [DESIGN_SYSTEM Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-13
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-042--the-google-oauth-client-is-not-set-up-for-any-environment) GAP-042
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (37): O-06; CAP-13; ROUTE-102–ROUTE-103; INT-102–INT-103; RG-11; AU-22–AU-24; LG-01; TM-02–TM-04; TM-59–TM-62; TM-70; TM-72–TM-73; D-SEC-20–D-SEC-21; H6-14–H6-15; H7-16–H7-18; H8-19–H8-20; CI-09; BD-19; RB-03; GR-13; SM-19–SM-20; GAP-043

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's Google adapter behind its authentication boundary; Socialite's configuration

**BUSINESS RULES:**

- N/A — SECURITY §2 is the rule set

**ARCHITECTURE RULES:**

- Socialite isolated in IAM's adapter; the domain never sees provider data beyond the subject and verified e-mail AU-22 names

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-02, User enumeration through login, reset, the two-factor step, Google sign-in or timing (AU-04, AU-12, AU-22); TM-03, Session fixation, also across the steps of a two-factor or Google login (AU-06, AU-16, AU-22); TM-04, Session theft on a shared or unattended device, a previous user's Google session included (AU-06, AU-07, AU-08, WS-05, AU-22); TM-59, A forged, replayed or injected Google callback — a stolen authorization code, a sign-in or link started on another site, a replayed ID token (AU-22, AU-23); TM-60, Google link takeover — an attacker's Google identity linked to a victim's account, a victim's identity linked to an attacker's account, or a link derived from a matching e-mail (AU-23, AU-22, AU-24, LG-07); TM-61, A previous user's Google session on a shared office PC or floor device used to sign in (AU-22, AU-07, AU-08, AU-05); TM-62, Google outage or loss of the person's Google account (AU-22, AU-24); TM-70, Real-time phishing that relays the password, or a Google sign-in, together with the TOTP code (AU-16, AU-22, LG-07); TM-72, One phone holding both the person's Google session and the authenticator, so that whoever holds it unlocked can pass both steps (AU-22, AU-20, AU-24, LG-07); TM-73, Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (AU-05, AU-20, AU-24, AU-10, LG-07)
- state, nonce and PKCE (AU-22); the generic failure answer; no provider token stored — only the provider, `sub`, the e-mail at link time and the link facts (AU-24); Google never decides roles, capabilities or scope (AZ-13)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — scope comes only from the account's grants (AZ-13)

**CONCURRENCY / IDEMPOTENCY:**

- one active link per provider subject under its unique key (BD-19)

**AUDIT:**

- the business audit event of the Google link and its lifecycle — link and unlink — written in the step's own transaction (PERMISSIONS_MATRIX RG-11; CONCURRENCY_IDEMPOTENCY CI-09; LG-01; AU-23, AU-24), its security event following after the commit
- security events for Google sign-in, link and unlink (LG-07; H9-15)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM's Google adapter
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)
- the provider replaceable behind the adapter

**AUTH IMPACT:**

- Authentication change: PROPOSED — builds the designed controls of SECURITY §2, nothing beyond them
- Authentication implementation: LARAVEL_NATIVE — Fortify and Socialite (D-SEC-17; [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: AICWDF-COMPAT-8 with the common and breached-password blocklist (AU-03; D-SEC-15)
- Google sign-in: ON where a client is configured, EXISTING_ACCOUNT_ONLY; off otherwise (GLC-09)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — no auto-registration (AU-22)
- Google credentials owner action required: YES — the OAuth clients are TASK-082's, the Owner's
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO — Fortify and Socialite stay behind IAM's authentication boundary (ARCHITECTURE §8)

**COST IMPACT:**

- New recurring cost: NONE — Google sign-in costs nothing (DEP-10)
- Paid third-party service: NO
- Free/open-source alternative: N/A — Socialite is free
- Estimated additional monthly production cost: 0
- Owner approval required: NO — the client is the Owner's action (TASK-082)

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Socialite's Google driver and PKCE support at the installed version
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — the flow with the provider faked
- Other: none — no real Google account or client in tests
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which sign-in paths call the challenge after Google
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Socialite's state and PKCE handling; Google's OpenID Connect claims
- Version-sensitive assumptions: none from memory

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
- Design reference: PG-001's Google button as INFORMATION_ARCHITECTURE §8.1 and the sign-in compositions state
- Primary action: the page's one submit
- Secondary actions: as the page's NAV- contract names
- Destructive actions: none
- Redundancy constraints: one submit per page
- Loading state: the submit's pending state, no double submission
- Empty state: N/A — a sign-in page always shows its form
- Error state: the generic answers of AU-04 and AU-05, never which part was wrong

**ROUTE IMPACT:**

- ROUTE-102 (redirect), ROUTE-103 (callback)

**INTERACTION CONTRACTS:**

- INT-102, INT-103

**NAVIGATION CONTRACT:**

- Parent: PG-001
- Entry: "Masuk dengan Google" on PG-001
- Back action: Google's own cancel returns to PG-001 with the generic answer
- Cancel: as above
- Success destination: PG-002, the TOTP challenge
- Error behavior: one generic Google failure (AU-22)
- State preservation: none
- Mobile behavior: the phone composition

**ACCEPTANCE CRITERIA:**

1. An unknown or unlinked subject, an inactive account, an account without active TOTP and an account in recovery (AU-20 b) all get the same generic failure, which reveals nothing about any account, and none signs in (AU-22; H8-20).
2. Every Google sign-in passes the TOTP challenge before any page.
3. A subject is linked to at most one active account (BD-19).
4. With no client configured, the Google button is absent and the routes refuse.
5. A Google link and unlink write their business audit event in the step's own transaction and their security event after the commit (RG-11; LG-01; AU-23, AU-24).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the claim checks (TL-03)
- Feature: SM-20 with the provider faked (TL-04)
- Integration: the link's unique key under two connections; the link's and the unlink's audit rows in their own transaction (TL-05)
- Authorization: no role, capability or scope from Google (TL-06; AZ-13)
- Route integrity: ROUTE-102 and ROUTE-103 audited (TL-07)
- Interaction: INT-102, INT-103 (TL-08)
- Browser E2E: the faked journey (TL-09)
- Localization/language switch: the button and failure text in both languages (TL-10)
- Visual breathing room/density: GR-13's button
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-19, SM-20 (TL-14)
- Performance: PFM-19 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the button's absence without a client

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Feature/Auth/GoogleSignInTest.php` — an unknown and an unlinked subject, an inactive account, one without active TOTP and one in recovery, another nonce, audience and issuer, an expired token, a mismatched or malformed state, Google's cancel, a failed exchange and a missing or aged marker each answered by the one generic failure on PG-001 with GOOGLE_SIGNIN_FAILED and its reason, nothing signed in —, `tests/Interaction/GoogleSignInTest.php` — the denied and unknown callbacks alike, nothing created or linked — and `tests/Browser/E2E/google.spec.ts` — an unlinked Google account. Criterion 2: `GoogleSignInTest.php` — PG-002's challenge with the GOOGLE purpose and no session, a TOTP code only after Google, the session established by the code, LOGIN_SUCCEEDED naming `google_totp` —; `google.spec.ts` — nothing reachable before the code. Criterion 3: `tests/Feature/Auth/GoogleIdentitiesTest.php` — the no-op relink, the conflict, one identity per account —; `tests/Integration/Authentication/GoogleLinkRaceTest.php` — two accounts racing for one subject under real sessions, by the re-read and by the unique key's refusal (C-64). Criterion 4: `GoogleSignInTest.php` — with no client, no button on PG-001 and both routes refusing (GLC-09). Criterion 5: `GoogleIdentitiesTest.php` — IAM.GoogleLinked and IAM.GoogleUnlinked in the step's transaction, GOOGLE_LINKED and GOOGLE_UNLINKED after the outermost commit, a rollback of the step or of its caller leaving neither; AU-24's reasons and actors

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS locally and in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS locally and in CI (TL-02)
- [x] Relevant automated tests: Unit — the ID token's claim checks (TL-03); Feature — SM-20 with the provider faked (TL-04); Integration — the link's unique key under two real sessions (TL-05); PASS locally and in CI
- [x] Authorization: no role, capability or scope from a Google identity (AZ-13; TL-06)
- [x] Route integrity: ROUTE-102 and ROUTE-103 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-102, INT-103 (TL-08)
- [x] Browser E2E if required: the faked Google journey to the code step, and the generic failure, on Chromium and WebKit, Google never reached (TL-09; SM-20)
- [x] Responsive/adaptive if required: PG-001 with the Google button at the sign-in specs' band edges (TL-11)
- [x] Visual breathing room/density if UI: GR-13's button in Google's light theme through the design system's tokens (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS locally and in CI; the Google fixture local-only
- [x] Security checks if required: SM-20 and M-41–M-43; TL-12's axe on PG-001 with the button; the secret-like scan and both dependency audits PASS (TL-14)
- [x] Database safety review: no migration and no schema change — TASK-005's `user_external_identities`, its C-63 and C-64 —; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources, nothing invented; ER-06's one independent review, its points fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `b39fdfc`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40306778592 (push) and 40306782063 (pull request #24), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". On the Owner's workstation, 2026-10-26: the suites Unit 280, Feature 139, Integration 907, Authorization 105, Interaction 17, Routes 11, Architecture 50 and Performance 4 before ER-06's fixes and the authentication suites again after them; TypeScript, ESLint, `tests/Frontend/` 30, Pint, Larastan/PHPStan, the production build and the Google, sign-in and two-factor browser specs PASS
- Build result: `TL-13 Frontend build: PASS — Evidence: b39fdfc, CI ubuntu-24.04 run 40306778592`
- Route evidence: TL-07's route audit PASS — ROUTE-102 `GET /masuk/google` (`access:none,google_redirect`) and ROUTE-103 `GET /masuk/google/callback` (`access:none,google_callback`), each under the per-address Google limiter —; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/GoogleSignInTest.php` (INT-102, INT-103)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: b39fdfc, CI ubuntu-24.04 run 40306778592`; TL-10 and TL-12 for the button in the same run
- Localization evidence: the button in both languages (`google.spec.ts`) and the failure text in both (`GoogleSignInTest.php`) (DOD-16); 1 key added through `lang/registry/added-keys.json` for the Owner's review
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — Socialite, its dependencies and Roboto free and open-source, project-local; no Google client exists yet (GAP-042)
- Files changed: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`; `config/services.php`, `.env.example`; `app/Modules/IAM/Authentication/` — the provider, the ID token's checks, the sign-in steps and the identities —, `app/Modules/IAM/Http/Controllers/`, `app/Modules/IAM/Authentication/TwoFactorSteps.php`'s LOGIN_SUCCEEDED detail; `routes/web.php`, `routes/fixtures.php`; `resources/js/components/GoogleButton.tsx`, `resources/js/Pages/Auth/SignIn.tsx`, `resources/css/tokens.css`; `lang/`; `tests/`; TOOLCHAIN's row; this file, the statuses of TASK-020, TASK-023 and TASK-024, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `3ebd525`, `5cf862a`, `aded785`, `80e4d22` and `b39fdfc`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-019-google`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-26; Context7 not available, each Socialite behaviour re-read in the installed source and pinned by a test (GAP-043; TOOLCHAIN's TASK-019 row); CI the final proof; its first run, on `aded785`, failed in every job: package discovery loads the routes in the console, where the local Google fixture read the cache before any store was up — fixed in `80e4d22`, the fixture kept out of the console — Codex's review of `aded785` found the same as its one P1, answered by that fix; the run on `80e4d22` failed TL-13 alone: after its `--no-dev` install, package discovery still loaded the fixture routes and the test class was gone — fixed in `b39fdfc`, the class loaded only for a request. A first version of the browser journey let Chromium follow ROUTE-102's redirect to Google's real authorization page, which answered `invalid_client` for the fixture's synthetic client ID — no secret, token or account was involved; Playwright does not route redirect hops, so the spec now reads ROUTE-102's answer without following it and fails any request to Google (SM-20). ER-06's one review found no HIGH point and ten others: the identities' events after the outermost commit so TASK-023 and TASK-024 can run them inside their commands (RV-16), a Google sign-in named in LOGIN_SUCCEEDED (LG-02), the failure text in both languages, Google's cancel and a malformed state, the race's unique-key path made deterministic, the tests H8-20, M-43 and the fail-closed limiter ask for, the state dropped at every failure, the token's `azp` and verified e-mail, AU-24's actors and the recovery link named — each fixed and tested; a start from a signed-in session answered by the guest middleware's landing page (NAV-001) rather than PG-001. Readings within the approved text are listed in CURRENT_HANDOFF. The READY gates of the Tasks TASK-019 blocks were re-checked at this DONE: TASK-020, TASK-023 and TASK-024 READY — every dependency DONE —; TASK-082 waits on TASK-071 and TASK-078. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-020 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- create or store a real Google client, secret or account
- let Google register an account or set a role, capability or scope
