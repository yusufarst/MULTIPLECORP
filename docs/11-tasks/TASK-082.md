# TASK-082 — Google OAuth clients for staging and production

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on OPI-06

**RISK CLASS:** HIGH

**EXECUTOR:** OWNER

**OBJECTIVE:**

The Owner, under the Owner's own Google Cloud access, creates the Google OAuth client of staging and of production — the consent screen, the scopes `openid email` and each environment's exact redirect URI — and hands each client's ID and secret to the operator to be stored in that environment's configuration only, the free-tier record of COST_POLICY §4C.4 confirmed at set-up; Google sign-in, with its button, comes on in an environment only once that environment's client exists, so that GLC-09 holds before Google sign-in is enabled in production.

**WHY THIS EXISTS:**

The Owner decided Google sign-in for existing accounts only (DIR-037 D6), and it needs one Google OAuth client per environment, created under the Owner's own Google Cloud access at no recurring cost (DEP-10). None exists, and its quotas, verification rules and cost have not been confirmed against Google's current terms ([GAP-042](../00-governance/GAP_REGISTER.md#gap-042--the-google-oauth-client-is-not-set-up-for-any-environment)); H9-13 hands the client's set-up to P9, and GLC-09 keeps Google sign-in off in production until the production client exists. Password sign-in works without it.

**DEPENDS ON:**

- TASK-071 — The operator's inputs before staging
- the operator's or the Owner's input OPI-06 recorded ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)) — BLOCKED until recorded

**BLOCKS:**

- none

**PARALLEL SAFE:** YES — the Owner's set-up in Google Cloud touches no code and no host step, its records are rows of its own, and no Task waits on it

**IN SCOPE:**

- **The input it waits on.** OPI-06 — the Owner's Google Cloud access for the OAuth clients — is recorded as confirmed in INFRASTRUCTURE §9's row, the access itself used with the Owner and never recorded; this Task stays BLOCKED until it is recorded, and while it is not, Google sign-in stays off in every environment and GLC-09 holds by that (GAP-042)
- **The two clients** (H9-13; DEP-10; INFRASTRUCTURE §4.3): created by the Owner under the Owner's own Google Cloud access, one for staging and one for production — the consent screen, the scopes `openid email` and no other, each environment's exact redirect URI built from the domain and staging's name OPI-30 keeps outside the repository (TASK-071)
- **The secrets' custody** (SX-01; SX-02): each client's ID and secret handed to the operator and stored in that environment's configuration only, readable only by the service account, never in the repository, a log, an issue or a document; a rotation done in Google Cloud and in the environment, leaving every Google link unchanged (AU-22)
- **The free-tier record** (COST_POLICY §4C.4): the limits, quotas and consent-screen rules confirmed against Google's current terms at set-up, with no cost or quota that applies — the inventory's Rp0 row for sign-in only, no Google API beyond it; a cost or a quota found STOPS for the Owner's COST_POLICY §4C.2 decision
- **Google sign-in on only with its client**: in an environment Google sign-in, and its button drawn to Google's guidelines with its font self-hosted by TASK-019 (GR-13), come on only once that environment's client exists (INFRASTRUCTURE §4.3); in production, GLC-09 is met by the production client set up before Google sign-in is enabled
- **The staging check** (SM-20; SECURITY_TEST_MATRIX §9): the real redirect checked once on staging with the staging client by the operator at the next staging run, its result recorded with this Task; outbound HTTPS from the VPS to Google is tested in TASK-084 (OPI-08; GLC-08)
- **The record**: each client by environment and role, its consent screen's state and the free-tier confirmation — never an ID, a secret or a redirect URI with the real domain

**OUT OF SCOPE:**

- the Google sign-in code — Socialite, the provider subclass reading the verified ID token, the button (TASK-019)
- outbound HTTPS from the VPS to Google (TASK-084)
- any Google API or paid Google service beyond sign-in (COST_POLICY's inventory)
- registering accounts: Google sign-in serves existing linked accounts only and never defines roles, capabilities or company scope (AU-22; AZ-13)

**REFERENCES:**

- [INFRASTRUCTURE §4.3](../09-operations/INFRASTRUCTURE.md#43-secrets-and-keys) — the Google OAuth client of each environment; [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-06, OPI-08, OPI-30
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-10, DEP-11
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-22–AU-24; [§7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-01, SX-02; [§10](../05-security/SECURITY.md#p9--operations) H9-13
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-08, GLC-09
- [COST_POLICY](../00-governance/COST_POLICY.md#recurring-cost-inventory) — the Google OAuth client's row and its free-tier record
- [DESIGN_SYSTEM Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-13
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-20; [§9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-13
- [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md#auth-profile--in-force-under-appr-010) — the auth profile
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-042--the-google-oauth-client-is-not-set-up-for-any-environment) GAP-042
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (6): DEP-10; H9-13; GR-13; OPI-06; GLC-09; GAP-042

**EXPECTED MODULES / FILE AREAS:**

- no code; OPI-06's row of INFRASTRUCTURE §9 and the Google client's free-tier record in COST_POLICY, changed under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md#decision-authority) to the confirmed facts; the IDs and secrets in each environment's configuration on the VPS, set by the operator

**BUSINESS RULES:**

- N/A — no business rule; Google sign-in serves existing linked accounts only (DIR-037 D6)

**ARCHITECTURE RULES:**

- one client per environment, for sign-in only — no Google API beyond it (COST_POLICY's inventory); password sign-in stays for every account, so switching Google sign-in off leaves the links inert
- Google never defines roles, capabilities or company scope (AZ-13; EXECUTION_CONTEXT's auth profile)

**SECURITY / PERMISSION REQUIREMENTS:**

- the client ID and secret are secrets of SX-01, set per environment in its configuration only; the Owner's Google Cloud access stays the Owner's (OPI-06)
- the scopes `openid email` only and each environment's exact redirect URI (H9-13); no Google script, frame or One Tap prompt on any page — TASK-019's (WS-08; H8-20)
- Google sign-in only with active TOTP and always followed by the TOTP challenge (AU-22) — unchanged by the client's existence

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no data path; Google never defines company scope (AZ-13)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command, lock or job; the Google flows' races (M-41–M-43) are TASK-019's

**AUDIT:**

- N/A — no business action; GOOGLE_LINKED, GOOGLE_UNLINKED and GOOGLE_SIGNIN_FAILED are TASK-019's security events (LG-02); the Owner's record names each client by environment and role

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code; the clients serve IAM's existing Google boundary (TASK-019)
- Stable core kept provider-neutral: YES — Google sits behind Socialite and IAM's authentication boundary (ARCHITECTURE §8)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — password sign-in stays for every account and no business data is held by Google (COST_POLICY's free-tier record)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — sign-in only, no other Google service

**AUTH IMPACT:**

- Authentication change: NONE — the designed Google sign-in becomes usable once its client exists
- Authentication implementation: LARAVEL_NATIVE — Socialite (D-SEC-17; [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: AICWDF-COMPAT-8 — unchanged; Google sign-in always followed by the TOTP challenge (AU-22)
- Google sign-in: ON — in each environment only once its client exists (DIR-037 D6; INFRASTRUCTURE §4.3)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — no auto-registration (AU-22)
- Google credentials owner action required: YES — the Owner creates both clients under the Owner's own Google Cloud access (DEP-10; OPI-06)
- Authorization change: NONE — Google never defines roles, capabilities or company scope (AZ-13)
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the client is Rp0 for sign-in only, its free-tier record confirmed at set-up (COST_POLICY §4C.4)
- Estimated additional monthly production cost: 0
- Owner approval required: NO — the zero-cost row is already approved with D6; a cost or quota found at set-up STOPS for the Owner (COST_POLICY §4C.2)

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — the Owner works in Google Cloud's own console; the consent screen, scopes and redirect URIs follow H9-13
- Graphify: N/A — no code
- Browser E2E: N/A — no journey is added; SM-20's single real redirect on staging is the operator's check
- Other: the Owner's own Google Cloud access (OPI-06); the operator for the secrets' custody and the staging check; Google's current terms, read and dated for the free-tier record
- Missing safe tool behavior: N/A — nothing is installed; no paid Google service is adopted (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the exact redirect route TASK-019 registers; staging's name and the domain OPI-30 keeps
- Expected affected areas: each environment's configuration and COST_POLICY's free-tier record only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured; the console's terms are read on Google's official pages and dated
- Version-sensitive assumptions: none — the quotas and consent-screen rules are confirmed at set-up (GAP-042)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; the Google button is TASK-019's, drawn to Google's guidelines (GR-13)
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page is built or changed
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page is built or changed
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; without a client the button is absent and password sign-in serves

**ROUTE IMPACT:**

- none — the redirect and callback routes are TASK-019's (EN-03's exceptions); each client registers the exact redirect URI of its environment

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. OPI-06 is recorded as confirmed before any client is created; until then the Task stays BLOCKED and Google sign-in stays off in every environment (GLC-09; GAP-042).
2. The staging and the production client each exist under the Owner's own access, with the consent screen, the scopes `openid email` and that environment's exact redirect URI (H9-13; DEP-10).
3. Each client's ID and secret sit only in their environment's configuration, readable only by the service account, and none appears in the repository, a log or an issue (SX-01; SM-15).
4. The free-tier record is confirmed against Google's current terms with no cost or quota that applies; a cost found has stopped for the Owner (COST_POLICY §4C.2, §4C.4).
5. The real redirect passes once on staging with the staging client (SM-20).
6. Google sign-in and its button come on in an environment only once that environment's client exists (INFRASTRUCTURE §4.3; GR-13; GLC-09).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task; the Google flows' tests are TASK-019's
- Integration: N/A — no code in this Task
- Authorization: N/A — no route; Google defines no access (AZ-13)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no journey is added; SM-20's staging redirect below
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no build in this Task
- Security: SM-20's real redirect once on staging with the staging client (TL-14); the secret-like scan of the recorded rows (SM-15)
- Performance: N/A — no target in this Task
- Migration: N/A — no migration or import code in this Task
- Other: the free-tier record checked against COST_POLICY §4C.4's fields — limits, quota behaviour, lock-in, exit and data portability

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the Owner's record of each client and the operator's SM-20 check stand in their place
- [ ] Authorization — the Owner's Google Cloud access kept by the Owner; each secret held by the operator in its environment only
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no journey; SM-20's single real redirect on staging recorded
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: no build
- [ ] Security checks if required — the scopes `openid email` only, the exact redirect URIs, no secret or ID outside the environments' configuration
- [ ] Database safety review — no database read or changed
- [ ] Final diff review — only OPI-06's row and the free-tier record changed, without an ID, a secret or a real domain

**COMPLETION EVIDENCE:**

- Test outputs: the Owner's record — each client by environment, its consent screen, scopes and redirect registered, the free-tier confirmation dated — and the operator's record of SM-20's staging redirect, in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey is added; the staging redirect is part of the record
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20); the Google client's COST_POLICY §4C.4 record confirmed
- Files changed: OPI-06's row of INFRASTRUCTURE §9 and the free-tier record of COST_POLICY — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); while OPI-06 is not confirmed the Task stays BLOCKED and GLC-09 holds by Google sign-in staying off; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the Owner's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- none — no Task depends on this one; TASK-087 reads GLC-09 at go-live

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let an agent hold the Owner's Google Cloud access, a client ID or a client secret (INF-01; SX-01; SX-04)
- turn Google sign-in on in an environment before its client exists, or ask for any scope beyond `openid email` (INFRASTRUCTURE §4.3; H9-13)
- adopt a paid Google service or any Google API beyond sign-in (COST_POLICY)
- let Google sign-in create an account or define a role, a capability or a company scope (AU-22; AZ-13)
