# TASK-019 — Google sign-in for existing accounts

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Google sign-in for existing accounts only — the OpenID Connect authorization flow, linking by the account holder, the identity lifecycle and unlink contract —, always followed by the TOTP challenge and never defining roles, capabilities or company scope.

**WHY THIS EXISTS:**

D6 and K2 make Google a convenience for existing accounts with active TOTP (AU-22–AU-24; AZ-13); the production clients are the Owner's (GAP-042), so this Task builds and tests the flow with a faked provider and Google stays off where no client exists (GLC-09).

**DEPENDS ON:**

- TASK-018 — Two-step verification (TOTP) and recovery codes

**BLOCKS:**

- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants

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
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (46): O-06; CAP-13; AC-12–AC-13; AC-16; OS-03; REF-001; REF-003–REF-004; REF-049; REF-056–REF-057; OWN-02; OWN-04; ROUTE-102–ROUTE-103; INT-102–INT-103; AU-22–AU-24; TM-02–TM-04; TM-59–TM-62; TM-70; TM-72–TM-73; D-SEC-20–D-SEC-21; H6-14–H6-15; H7-16–H7-18; H8-19–H8-20; BD-19; RB-03; GR-13; SM-19–SM-20; GAP-043

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's Google adapter behind its authentication boundary; Socialite's configuration

**BUSINESS RULES:**

- N/A — SECURITY §2 is the rule set

**ARCHITECTURE RULES:**

- Socialite isolated in IAM's adapter; the domain never sees provider data beyond the subject and verified e-mail AU-22 names

**SECURITY / PERMISSION REQUIREMENTS:**

- state, nonce and PKCE (AU-22); the generic failure answer; no provider token stored — only the provider, `sub`, the e-mail at link time and the link facts (AU-24); Google never decides roles, capabilities or scope (AZ-13)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — scope comes only from the account's grants (AZ-13)

**CONCURRENCY / IDEMPOTENCY:**

- one active link per provider subject under its unique key (BD-19)

**AUDIT:**

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

1. A Google identity without a linked account, or a linked account without active TOTP, cannot sign in.
2. Every Google sign-in passes the TOTP challenge before any page.
3. A subject is linked to at most one active account (BD-19).
4. With no client configured, the Google button is absent and the routes refuse.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the claim checks (TL-03)
- Feature: SM-20 with the provider faked (TL-04)
- Integration: the link's unique key under two connections (TL-05)
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

- TASK-023 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- create or store a real Google client, secret or account
- let Google register an account or set a role, capability or scope
