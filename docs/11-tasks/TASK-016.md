# TASK-016 — Application shell, navigation and the company context

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the application shell every authenticated page sits in — sidebar, *Menu lengkap*, icon rail, top bar, phone app bar and bottom bar, the account menu, "Buat", breadcrumbs and back actions — with the navigation entries each account's capabilities allow, the company context (WF-ACC-01), the system page PG-005 and the global states of every page.

**WHY THIS EXISTS:**

IA §6–§7 and NAVIGATION_CONTRACTS define one shell and one company context model; UXH-11 asks for a 401 answer to an unauthenticated Inertia request, a status for a step-up and the absolute-session deadline outside the page data — all shell-level behaviour, built once.

**DEPENDS ON:**

- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit
- TASK-012 — Field projection, read classes and cross-company links
- TASK-015 — Design system — tokens, components, patterns and states

**BLOCKS:**

- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-025 — Companies, identity assets, bank accounts and tax configuration
- TASK-026 — Clients and suppliers
- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-028 — Rack locations and minimum stock
- TASK-030 — Evidence and uploaded documents
- TASK-031 — Correction cases
- TASK-061 — Activity history and security log

**PARALLEL SAFE:** NO — every page Task renders inside the shell

**IN SCOPE:**

- the shell of DESIGN_SYSTEM PT-01–PT-02 and IA §6, the phone bars and *Lainnya*, the account menu, "Buat" (INT-003, INT-004), GR-02, GR-11, GR-14 and GR-22
- the navigation entries the account's capabilities allow, sent by the server as hints (H7-03; IA §13), absent without the capability
- the company context model of IA §7 and WF-ACC-01: the working filter and labels (PT-04), the scope always the server's (CS-01–CS-08)
- the system page PG-005 (ROUTE-005; NAV-005) for any address the account cannot open (AZ-11; IP-21), without an oracle
- the global states of IA §8.8 and the conventions NV-01–NV-08: back actions, state preservation, the breadcrumbs
- UXH-11's shell rules: a 401 to an unauthenticated Inertia request intercepted in place (IP-11; INT-010), the step-up answered by a status the client intercepts (IP-10), the absolute-session deadline as a non-sensitive timestamp outside WS-07's page data, background requests marked
- the page addressing of IA §11 and the route conventions RC-01–RC-06 for every page route

**OUT OF SCOPE:**

- the sign-in pages (TASK-017, TASK-018)
- each page's content (its own Task)
- the home pages' signals (TASK-058)

**REFERENCES:**

- [INFORMATION_ARCHITECTURE §6](../07-ux-design/INFORMATION_ARCHITECTURE.md#6-application-shell-and-global-navigation)
- [INFORMATION_ARCHITECTURE §7](../07-ux-design/INFORMATION_ARCHITECTURE.md#7-company-context-model)
- [INFORMATION_ARCHITECTURE §8](../07-ux-design/INFORMATION_ARCHITECTURE.md#8-screen-inventory)
- [INFORMATION_ARCHITECTURE §11](../07-ux-design/INFORMATION_ARCHITECTURE.md#11-page-addressing)
- [INFORMATION_ARCHITECTURE §13](../07-ux-design/INFORMATION_ARCHITECTURE.md#13-role-based-navigation)
- [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md#1-conventions) NV-01–NV-08, NAV-005
- [WORKFLOWS](../03-workflows/WORKFLOWS/s05-01-02-access-project.md) WF-ACC-01
- [ADMIN_FLOW §6](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input) IP-10, IP-11
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-11
- [PERMISSIONS_MATRIX §6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules)
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-07
- the architecture challenge's sequences 8a, 19a ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (148): BR-ACC-01–BR-ACC-03; WF-ACC-01; L-03; ROUTE-005; RC-01–RC-06; INT-003–INT-004; INT-007; INT-010; C-32; C-54; CS-01–CS-08; AU-07; WS-07; TM-04; TM-10; TM-12; TM-15; TM-17; TM-19; TM-27–TM-28; TM-61; H6-06; H7-02–H7-03; H7-05–H7-08; H7-10–H7-11; H7-13; H7-19; HO-01–HO-03; HO-05–HO-10; HO-38; RB-01; RB-04–RB-06; RB-09; UXN-01–UXN-26; D-IA-01–D-IA-06; D-IA-18–D-IA-19; D-IA-21–D-IA-23; PG-005; SCR-04; UXI-03; UXI-15–UXI-18; UXI-20; D-UX-01–D-UX-10; IP-06; IP-11; IP-21; MSG-33–MSG-34; UXS-04; UXS-22–UXS-23; UXS-25; UXH-11; PT-01–PT-02; PT-04; GR-02; GR-11; GR-14; GR-22; NAV-005; NV-01–NV-08; component Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link; component Phone app bar and bottom bar; *Lainnya*; component Filter toolbar and company view; component Menu and "Tindakan Lain"; account menu

**EXPECTED MODULES / FILE AREAS:**

- the shell layout components, the navigation provider, the Inertia error and status handlers, the PG-005 page

**BUSINESS RULES:**

- BR-ACC-01–BR-ACC-03: the access boundary as the shell presents it

**ARCHITECTURE RULES:**

- the shell renders the server's decisions — entries, scope, labels — and never decides them (DESIGN_SYSTEM §10)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-04, Session theft on a shared or unattended device, a previous user's Google session included (AU-06, AU-07, AU-08, WS-05, AU-22); TM-10, Forged reference to another company's record in a payload (CS-04, C-32); TM-12, Nested or related resources of Company B (CS-04, CS-07); TM-15, Search results or counts of Company B (DP-06, CS-07); TM-17, Report filters naming Company B (DP-08, CS-08); TM-19, Inertia partial reload, deferred props or shared props (WS-07, DP-15, CS-07); TM-27, Error messages, validation or unique import keys as an existence oracle (WS-10, AZ-11, CS-04, DP-18, DP-20); TM-28, Browser history, back-forward cache or client cache after logout, expiry or remote termination (AU-06, WS-07, WS-08); TM-61, A previous user's Google session on a shared office PC or floor device used to sign in (AU-22, AU-07, AU-08, AU-05)
- navigation entries are hints, never authorization (H7-03); WS-07's shared props only; PG-005 answers every unopenable address alike

**COMPANY SCOPE AND FIELD PROJECTION:**

- the working filter narrows within the server's scope; a revoked grant on an open page is answered by the next request's denial and the shell's reload rule (RV-; CJ-09)

**CONCURRENCY / IDEMPOTENCY:**

- the in-place re-authentication keeps the unsent input and the pending `command_id` (ADMIN_FLOW §6; HO-02)

**AUDIT:**

- N/A — the shell runs no business command

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform presentation
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

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

- Context7: REQUIRED — Inertia's error and status handling, partial reloads and history
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — the shell's navigation paths on desktop and phone
- Other: the accessibility engine (skip link, landmarks)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which pages each navigation entry opens
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Inertia's handling of 401, 409 and 419 answers and its history encryption
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — GL terms for every entry
- Language switch impact: the shell's labels switch in place (NV-07)
- Mobile-first: YES — the phone bars first
- Responsive: YES — the bands of DESIGN_SYSTEM §6
- Adaptive: YES — sidebar, icon rail and phone bars by band
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: Sidebar, Sheet, Breadcrumb, DropdownMenu, Command, Tooltip (DESIGN_SYSTEM §10)
- Design reference: IA §6 and DESIGN_SYSTEM PT-01–PT-04
- Primary action: "Buat" where the account may create
- Secondary actions: the account menu and *Lainnya*
- Destructive actions: "Keluar" (INT-018, with TASK-017)
- Redundancy constraints: one entry per destination per surface
- Loading state: PT-19 in the content region
- Empty state: N/A — the shell is never empty
- Error state: PG-005 and PT-19's denied state

**ROUTE IMPACT:**

- ROUTE-005 — PG-005's view; the route conventions RC-01–RC-06 applied by every page Task

**INTERACTION CONTRACTS:**

- INT-003, INT-004, INT-007 (the company filter), INT-010 (the session ended)

**NAVIGATION CONTRACT:**

- Parent: none — the shell holds every page
- Entry: after sign-in, the landing page of NAV-002
- Back action: as NV- states for each page
- Cancel: N/A — no form in the shell
- Success destination: N/A — the shell runs no command
- Error behavior: PG-005 for an address the account cannot open
- State preservation: the working filter and the open section kept as NV- states
- Mobile behavior: the phone bars and *Lainnya* (GR-11, GR-22)

**ACCEPTANCE CRITERIA:**

1. Each account sees exactly the entries its capabilities allow, and an absent entry's route still refuses on the server.
2. The working filter never widens the server's scope, and every record shows its company label (PT-04).
3. An unauthenticated Inertia request answers 401 and the shell re-authenticates in place, keeping unsent input and the pending command's `command_id`, so the retry replays rather than runs twice (IP-11; CI-01–CI-09; HO-02; UXH-11; architecture challenge 19a).
4. A command needing step-up answers a status the client intercepts, never a redirect (IP-10).
5. PG-005 answers every unopenable address alike, without telling a missing record from a forbidden one (IP-21).
6. A command refused at its in-command re-authorization after a grant was revoked shows its denial on the open page as IP-21 presents it (SQ-22; architecture challenge 8a).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A — no PHP logic beyond the handlers covered by TL-04
- Feature: the 401, 409 and 419 answers and PG-005 (TL-04)
- Integration: N/A — no data path of its own
- Authorization: entries by capability and the server's refusal behind an absent entry (TL-06)
- Route integrity: ROUTE-005 audited (TL-07)
- Interaction: INT-003, INT-004, INT-007, INT-010 (TL-08)
- Browser E2E: the shell's paths on desktop and phone; re-authentication in place (TL-09)
- Localization/language switch: both languages (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11's bands
- Accessibility: skip link, landmarks, focus order (TL-12)
- Production build: TL-13
- Security: no oracle on PG-005; WS-07's props (TL-14)
- Performance: the shell adds no query per entry (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: N/A — nothing further

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

- TASK-021 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- decide a capability or a scope in the client
- show a disabled entry in place of an absent one
