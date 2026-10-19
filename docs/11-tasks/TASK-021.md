# TASK-021 — Numbering, its settings page and the Owner's raise after a restore

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Number documents, projects and purchases per company, type and period, unique and monotonic, with the numbering schemes, their start, seeds, retired numbers and go-live as a state, the settings page PG-061, and the Owner's audited raise after a restore that OG-3 added.

**WHY THIS EXISTS:**

Business numbers are legal identity: C-10 makes them unique per company, type and period, monotonic and append-only; DIR-083 OE-11 adds the Owner's raise so that, after a restore, no numbered type issues again below the numbers already used on paper (GAP-064); the import's seeds and the cutover's go-live depend on it (GAP-058; PRQ-07).

**DEPENDS ON:**

- TASK-006 — Schema — ORG, PTY, CAT and DOC tables
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure

**PARALLEL SAFE:** NO — it owns the counters every issuing Task draws from

**IN SCOPE:**

- the numbering schemes and sequences of DATABASE §4.2 with C-10's uniqueness and monotonicity; retired numbers; numbers tombstoned for discarded drafts as their actions state
- numbering concurrency NM-01–NM-10 and the lock LK-31 (CONCURRENCY_IDEMPOTENCY §14); the scenarios M-15, M-17, M-18 and M-31
- ConfigureNumbering and StartNumbering (ROUTE-136, ROUTE-137; INT-136, INT-137) on PG-061 with NAV-061, the start and seed confirmations and the scheme refusals ADMIN_FLOW §12 states (UXS-28–UXS-32)
- go-live as a state of numbering; the operator's one-time go-live command itself is TASK-077's (PRQ-07; HO-32; UXH-10)
- the Owner's raise after a restore (DIR-083 OE-11): through the numbering command (NX-01, locked at LK-31; ROUTE-137 as amended), recording per company, type and period the highest paper number; raising each affected counter, or creating the live period's, to the next number; once per recovery, never lowering one; no seed — no seed value or boundary moved and no defect alert —; audited with the incident (NM-03, NM-04; ADMIN_FLOW §12.1)
- the issuing types' draw at issue only (SF-ISSUE), the draw itself called by TASK-029 and the other issuing Tasks

**OUT OF SCOPE:**

- issuing any document (TASK-029 and the issuing Tasks)
- the opening import's seeds (TASK-057, using this Task's seeding rule)
- the runbook step and the cutover (TASK-076, TASK-077, TASK-087)

**REFERENCES:**

- [DATABASE §4.2](../04-architecture/DATABASE/s04-02-org.md#42-org--organization)
- [CONCURRENCY_IDEMPOTENCY §14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency)
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01
- [ADMIN_FLOW §12](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#12-numbering-and-company-onboarding)
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#companies-and-settings--perusahaan-penomoran-dokumen-pajak-pg-059-pg-061-pg-062-owner_only-j-md-01) ROUTE-136, ROUTE-137
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-061
- [TEST_STRATEGY §15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) — the raise's tests
- [RELEASE_PLAN §6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) RBK-02; GLC-25
- the architecture challenge's sequences 12a–12b, 14a ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (41): ROUTE-061; ROUTE-136–ROUTE-137; INT-136–INT-137; LK-31; NX-01; NM-01–NM-10; M-15; M-17–M-18; M-31; HO-27; HO-32–HO-33; HO-39; D-IA-13; PG-061; SCR-55; J-MD-01; UXS-28–UXS-32; UXH-10; NAV-061; GLC-25; PRQ-07; GAP-016; GAP-058; GAP-064

**EXPECTED MODULES / FILE AREAS:**

- the ORG module's numbering service and actions; PG-061

**BUSINESS RULES:**

- C-10 and BR-DOC-02: unique per company, type and period, monotonic, append-only

**ARCHITECTURE RULES:**

- one numbering service; a number drawn only inside the issuing action's transaction; never reused

**SECURITY / PERMISSION REQUIREMENTS:**

- PG-061 and both commands under `companies.manage` (OWNER_ONLY), with step-up where ROUTE-136 and ROUTE-137 say

**COMPANY SCOPE AND FIELD PROJECTION:**

- per company: a scheme and its sequences belong to one company; the Owner's scope is every company (CS-01)

**CONCURRENCY / IDEMPOTENCY:**

- NM-01–NM-10 under LK-31; two issues never draw one number (M-15, M-17); the raise refused if it would lower a counter or if the same recovery already raised it (NX-01)

**AUDIT:**

- every scheme change, start and raise audited; the raise's event names the incident — the restore and its recovery point — (NM-03; LG-01)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-006
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: ORG, tier 1, called by DOC and the issuing modules
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

- Context7: REQUIRED — PostgreSQL row locks and sequences behaviour as NM- states
- Graphify: OPTIONAL — once issuing Tasks exist
- Browser E2E: REQUIRED — PG-061's journey J-MD-01's numbering part and the raise's page path
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the module
- Pre-change questions: which actions draw numbers and through which lock
- Expected affected areas: ORG and every issuing module

**CONTEXT7:**

- Topics to verify: PostgreSQL locking for gap-free counters in the installed version
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG- texts of ADMIN_FLOW §12
- Language switch impact: keeps unsent input
- Mobile-first: YES — a desktop page readable on the phone (IA §8.6)
- Responsive: YES
- Adaptive: N/A — one layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the design system's forms and confirmations
- Design reference: ADMIN_FLOW §12.1 and IA §8.6's PG-061
- Primary action: the page's save or start
- Secondary actions: the raise, for the Owner after a restore
- Destructive actions: none — no control lowers or resets a counter (ADMIN_FLOW §12.1)
- Redundancy constraints: one start per scheme
- Loading state: PT-19
- Empty state: a company without a scheme
- Error state: the scheme refusals and C-10's refusal

**ROUTE IMPACT:**

- ROUTE-061 (PG-061's view), ROUTE-136, ROUTE-137

**INTERACTION CONTRACTS:**

- INT-136, INT-137

**NAVIGATION CONTRACT:**

- Parent: *Pengaturan* (NAV-061)
- Entry: *Pengaturan* › *Penomoran Dokumen*
- Back action: as NAV-061
- Cancel: leaves without saving, with the unsent-input warning (INT-009)
- Success destination: the change in place
- Error behavior: the refusal at its field
- State preservation: the selected company
- Mobile behavior: readable, actions on desktop

**ACCEPTANCE CRITERIA:**

1. Two concurrent issues of one type in one period draw two consecutive numbers and never the same one.
2. A number once drawn is never drawn again, also after a draft is discarded.
3. After a restore no numbered type issues again before the Owner's raise; the raise sets each affected counter to the next number after the highest paper number per company, type and period, once per recovery, and refuses a lower value or a second raise for the same recovery (DIR-083 OE-11; NM-03, NM-04, NX-01; RBK-02; GLC-25; architecture challenge 12a, 12b).
4. The raise moves no seed value or boundary and raises no defect alert, and its audit event names the incident.
5. Only the Owner reaches PG-061 and its commands.
6. Sequences seeded by the opening import start after the last legacy number per company, type and period and stay unique and monotonic; an opening purchase whose legacy number does not fit its company's scheme draws its number at the commit, a fitting one keeping its legacy number (DIR-079 CB-01; DATABASE §24; C-10; architecture challenge 14a, with TASK-057).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the scheme and period rules (TL-03)
- Feature: ROUTE-136 and ROUTE-137 with the raise (TL-04)
- Integration: two-connection draws (M-15, M-17, M-18), the halt before the raise and the raise's refusals (TL-05; TEST_STRATEGY §15; architecture challenge 12a, 12b), and a sequence seeded after the last legacy number (14a)
- Authorization: OWNER_ONLY (TL-06)
- Route integrity: ROUTE-061, -136, -137 audited (TL-07)
- Interaction: INT-136, INT-137 (TL-08)
- Browser E2E: PG-061's path and the raise (TL-09; UXS-28–UXS-32)
- Localization/language switch: both languages (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: step-up where stated (TL-14)
- Performance: the draw within its lock budget (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-016's period boundary at midnight WIB

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

- TASK-029 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- offer a control that lowers or resets a counter
- treat the raise as a seed or move a seed boundary
