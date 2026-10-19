# TASK-018 — Two-step verification (TOTP) and recovery codes

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

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
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (73): O-06; CAP-13; AC-12–AC-13; AC-16; OS-03; REF-001; REF-003–REF-004; REF-049; REF-056–REF-057; OWN-02; OWN-04; ROUTE-002; ROUTE-004; ROUTE-104–ROUTE-105; ROUTE-107; INT-104–INT-105; INT-107; AU-16; AU-18–AU-21; TM-01; TM-03; TM-06; TM-57; TM-63–TM-70; TM-72–TM-73; D-SEC-16–D-SEC-20; H6-13; H6-15; H7-15; H7-17–H7-18; H8-02; H8-17–H8-19; H8-21–H8-22; PG-002; PG-004; SCR-01–SCR-02; GR-12; NAV-002; NAV-004; SM-02; SM-17–SM-19; SM-21; component Sign-in compositions; GAP-033; GAP-043–GAP-044

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's two-factor actions and Fortify's two-factor configuration; PG-002 and PG-004

**BUSINESS RULES:**

- N/A — SECURITY §2 is the rule set

**ARCHITECTURE RULES:**

- Fortify's own two-factor routes not registered where SECURITY replaces them (TM-65); the confirmation writes secret, codes and time step in one transaction under the account row lock (H6-15)

**SECURITY / PERMISSION REQUIREMENTS:**

- a confirmed secret never shown again; the QR code rendered locally from non-persisted flash data (AU-16, AU-12's rule); one code accepted once, in sequence and concurrently (M-39; C-65)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the account itself; no company data on these pages

**CONCURRENCY / IDEMPOTENCY:**

- `two_factor_last_step` updated conditionally so one code cannot pass twice (RV-12; M-39); confirmation under the account row lock (H6-15)

**AUDIT:**

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
5. "Kembali" on PG-002 returns to PG-001 with the e-mail kept, and the pending attempt no longer completes a sign-in.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: period, window and last-step rules with a controlled clock (TL-03)
- Feature: SM-17–SM-19 flows (TL-04)
- Integration: the concurrent double submission of one code (TL-05; M-39)
- Authorization: the enrolment-only session reaches PG-004 alone (TL-06)
- Route integrity: ROUTE-002, -004, -104, -105, -107 audited (TL-07)
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

**TEST CHECKLIST:**

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

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
