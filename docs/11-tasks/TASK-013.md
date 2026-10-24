# TASK-013 — Command envelope, command identity, idempotency and the lock order

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the command envelope every business action uses: one client `command_id` per intent with re-authorized replay, the command log, the canonical lock order LK-00–LK-31 with the data-dependent lock-set protocol, guards and first-use rows, stale-state checks, transactions, failure mapping, retry and timeouts — with the business date and the correction taxonomy's common checks.

**WHY THIS EXISTS:**

One `command_id` per intent and the canonical lock order are invariants 5–7 of EXECUTION_CONTEXT (CI-01–CI-09; LK-00–LK-31; AX-01–AX-37 in one transaction each); the concurrency scenarios M-01–M-46 and GAP-005, GAP-008 depend on one envelope, not one per module.

**DEPENDS ON:**

- TASK-009 — Schema — FUL and FIN tables, finance and tax representations
- TASK-010 — Audit and security logging
- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit

**BLOCKS:**

- TASK-016 — Application shell, navigation and the company context
- TASK-017 — Password sign-in, sessions and credential links
- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-022 — Jobs, files and the document renderer
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-025 — Companies, identity assets, bank accounts and tax configuration
- TASK-026 — Clients and suppliers
- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-028 — Rack locations and minimum stock
- TASK-031 — Correction cases
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check
- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation

**PARALLEL SAFE:** NO — every command Task runs inside the envelope

**IN SCOPE:**

- SF-CMD (WORKFLOWS §2) and ARCHITECTURE §5: the action as the unit of work, authorization re-check and transaction
- command identity and replay (CI-01–CI-09) with the command log; replay re-authorized; outcomes COMMITTED, REJECTED, CONFLICT, PENDING and FAILED (HO-01–HO-02; IP-01–IP-05)
- the lock order LK-00–LK-31, lock classes and rules (LR-), and the data-dependent lock-set protocol with its restart budget
- transactions and isolation (TX-), guards and first-use rows (GU-, FU-), stale state (ST-), statement outcomes (SQ-01–SQ-23), separate duplicates (BD-) as each action's Task applies them, retry and timeouts (RY-)
- the business date control SF-DATE with the date rules BR-DT-01–BR-DT-06 and C-50: a committed fact whose business date is later than today (WIB) is refused at the command and by C-50's trigger, attached to every table whose rows carry a committed business date, while an earlier real date commits for the Owner and an Admin alike, with its audit and `recorded_at` kept; the correction taxonomy's common checks BR-CR-01–BR-CR-06 and FS- prohibitions
- `GuardMaintenance` and `ReconcileGuards` as audited operator console commands (H9-03; PRQ-05; GAP-031)
- the shared interactions INT-015–INT-017 on the server side
- HO-20's framework behaviours this envelope relies on, each verified by a TL-05 test so that a changed version fails it — the concurrency-error detector, savepoint nesting, the raw lock string, and whether the framework reconnects by itself between the registration keys and the transaction (FU-10) —, and HO-13: lock-order conformance measured from the locks actually held, the implicit locks of updates and foreign keys included, asserting ascending canonical order, with the plan of every multi-row lock statement verified (LR-05; TEST_STRATEGY §6)

**OUT OF SCOPE:**

- each action's own statement sequence and checks — its module Task (CONCURRENCY_IDEMPOTENCY §7)
- numbering concurrency (TASK-021) and jobs (TASK-022)

**REFERENCES:**

- [WORKFLOWS §2](../03-workflows/WORKFLOWS/s01-04-foundations.md#2-standard-command-envelope--sf-cmd)
- [ARCHITECTURE §5](../04-architecture/ARCHITECTURE.md#5-actions--the-command-envelope)
- [ARCHITECTURE §15](../04-architecture/ARCHITECTURE.md#15-transactions-and-failure-semantics)
- [CONCURRENCY_IDEMPOTENCY §4–6](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#4-canonical-lock-order)
- [CONCURRENCY_IDEMPOTENCY §8–9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows)
- [CONCURRENCY_IDEMPOTENCY §10–13](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay)
- [CONCURRENCY_IDEMPOTENCY §18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios)
- [BUSINESS_RULES §2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-01–BR-CR-06
- [PERFORMANCE §9](../06-api-performance/PERFORMANCE.md#9-cache-policy)
- [DATABASE §18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-50
- [TEST_STRATEGY §6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) HO-13, HO-20
- the architecture challenge's sequences 2a, 3a, 6a, 6c, 10a–10b, 15c, 18a, 19a ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (382): O-06; CAP-13; AC-12; AC-16; OS-02; REF-056–REF-058; IMG-03–IMG-04; OWN-01–OWN-02; OWN-04; BR-DT-01–BR-DT-06; BR-CR-01–BR-CR-06; FS-02–FS-12; FS-14; SF-CMD; SF-DATE; CM-01–CM-38; L-04; L-06; L-09; L-11; L-14; L-17; L-23–L-24; L-27; L-32–L-34; L-37–L-38; L-46; L-48; INT-015–INT-017; C-16; C-45–C-46; C-48; C-50; C-53; C-55; WS-01–WS-03; TM-09; TM-43; H6-02–H6-05; H6-09–H6-10; H6-12; H6-16; H9-03; TX-01–TX-10; D-CC-01–D-CC-12; LR-01–LR-12; LK-00–LK-31; GU-01–GU-07; FU-01–FU-13; ST-01–ST-09; CI-01–CI-09; BD-16; BD-19; SQ-01–SQ-23; RY-01–RY-09; RV-01–RV-18; M-01–M-46; HO-01–HO-02; HO-04–HO-05; HO-07–HO-08; HO-11–HO-15; HO-20; CP-01–CP-07; UXI-02–UXI-08; UXI-10–UXI-11; UXI-16; UXI-41; IP-01–IP-05; IP-07; MSG-04; MSG-06; MSG-13; MSG-15; MSG-20; UXS-01–UXS-04; UXS-07–UXS-08; UXS-10–UXS-17; PT-25; PRQ-04–PRQ-05; GAP-005; GAP-008; GAP-015–GAP-016; GAP-031

**EXPECTED MODULES / FILE AREAS:**

- the platform command envelope, command log service, lock-set helper, failure mapper; the console commands; `tests/Concurrency/`

**BUSINESS RULES:**

- BR-DT-01–BR-DT-06 (dates and backdating); BR-CR-01–BR-CR-06 (corrections); FS-02–FS-12, FS-14 (forbidden shortcuts) as common checks

**ARCHITECTURE RULES:**

- one action per business command; one transaction per indivisible action; locks only in the canonical order; Valkey never a system of record (CP-; ARCHITECTURE §1)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-09, Mass assignment of company, authority or state fields (WS-01, WS-02, DP-03); TM-43, Expensive search or report exhaustion (WS-03, WS-12)
- replay re-authorizes the current actor (CI-); allowed fields only and server-derived fields (WS-01–WS-03)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is its target's (CS-03), resolved through TASK-011's resolver before any lock

**CONCURRENCY / IDEMPOTENCY:**

- this Task is the mechanism: LK-00–LK-31, LR-, TX-, GU-, FU-, ST-, CI-, SQ-, RY-, RV- and the scenarios M-01–M-46 as executable tests on real PostgreSQL where their actions exist, each later Task adding its own (HO-11), with `ReconcileGuards` clean after each and its guard-missing and drift fixtures (HO-12)

**AUDIT:**

- the command log and the business audit written in the command's transaction (LG-01; TASK-010)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: PROPOSED — C-50's trigger: one function attached to every table whose rows carry a committed business date (DATABASE §18)
- Migration required: YES — expand-only
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform application layer
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

- Context7: REQUIRED — PostgreSQL row locks, `lock_timeout`, `statement_timeout` and serialization behaviour; the framework's database transactions
- Graphify: N/A — the codebase is young
- Browser E2E: N/A — no page
- Other: a two-connection test harness (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which actions each lock class serves
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: PostgreSQL lock modes, `lock_timeout`, `statement_timeout` and serialization failure codes; the framework's transaction retry
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — server side — the presentation of outcomes is TASK-015's
- Indonesian default: N/A — server side — the presentation of outcomes is TASK-015's
- English support: N/A — server side — the presentation of outcomes is TASK-015's
- Plain-language Indonesian: N/A — server side — the presentation of outcomes is TASK-015's
- Language switch impact: NONE
- Mobile-first: N/A — server side — the presentation of outcomes is TASK-015's
- Responsive: N/A — server side — the presentation of outcomes is TASK-015's
- Adaptive: N/A — server side — the presentation of outcomes is TASK-015's
- Visual breathing room: N/A — server side — the presentation of outcomes is TASK-015's
- Comfortable density: N/A — server side — the presentation of outcomes is TASK-015's
- shadcn/ui reuse: N/A — server side — the presentation of outcomes is TASK-015's
- Design reference: N/A — server side — the presentation of outcomes is TASK-015's
- Primary action: N/A — server side — the presentation of outcomes is TASK-015's
- Secondary actions: N/A — server side — the presentation of outcomes is TASK-015's
- Destructive actions: N/A — server side — the presentation of outcomes is TASK-015's
- Redundancy constraints: N/A — server side — the presentation of outcomes is TASK-015's
- Loading state: N/A — server side — the presentation of outcomes is TASK-015's
- Empty state: N/A — server side — the presentation of outcomes is TASK-015's
- Error state: N/A — server side — the presentation of outcomes is TASK-015's

**ROUTE IMPACT:**

- none of its own

**INTERACTION CONTRACTS:**

- INT-015–INT-017 (duplicate warning, CONFLICT, FAILED) on the server side

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

1. The same `command_id` replayed — a second press, or a retry after a lost answer or after signing in again in place on 401 or 419 — returns what already ran or runs once, re-authorized, never twice (CI-01–CI-09; BD-06; HO-02; architecture challenge 15c, 19a).
2. Two concurrent commands that meet at one lock serialize in the canonical order, without deadlock, as the matching M- scenario states.
3. Each SQ- outcome maps to its answer — REJECTED, CONFLICT, PENDING or FAILED — as CONCURRENCY_IDEMPOTENCY §12 states.
4. A committed fact whose business date is later than today (WIB) is refused at the command and by C-50's trigger; an earlier real date commits for the Owner and an Admin alike, with its audit, `recorded_at` kept (SF-DATE; BR-DT-01–BR-DT-04; C-50).
5. `GuardMaintenance` and `ReconcileGuards` run only from the console, audited, and report a difference without repairing silently.
6. A receipt racing its purchase line's cancellation or remainder closure: under the lock order one commits and the other answers REJECTED or CONFLICT, the line's guards intact (AX-04, AX-12, AX-32; BD-01; architecture challenge 2a — on the envelope's test commands here, on the module's commands in TASK-037 and TASK-039).
7. Two dispatches or reservations racing for the last unit: one COMMITTED, one REJECTED, AVAILABLE never negative (C-04; M-01, M-02; architecture challenge 3a — here on test commands, in TASK-038 and TASK-041 on theirs).
8. One payment applied twice at once: Σ applications never above the payment's real money and outstanding never below 0, atomically (BR-FIN-03, BR-FIN-04; C-13, C-15; AX-19; architecture challenge 6a — here on test commands, in TASK-051 on its own).
9. One real bank line has one identity and Σ Cash-In per line never exceeds it, and a likely duplicate is warned before a second submission (C-19; BD-02, BD-03; IP-05; architecture challenge 6c — here on test commands, in TASK-051 on its own).
10. Two dispatches drawing one lot at once recognize each unit's HPP exactly once, the lot's cost drawn under the lock order (BR-XD-07; AX-03; architecture challenge 18a — here on test commands, in TASK-036 and TASK-041 on theirs).
11. A grant revoked after the page opened refuses the command at its in-command re-authorization (SQ-22; the RV- races; architecture challenge 8a).
12. No command deletes a committed fact: a correction revises, voids, reverses or returns it with its reason and linkage (BR-CR-01; FS-02; CM-01–CM-38; architecture challenge 10a).
13. The business audit row is written in the command's own transaction (LG-01; architecture challenge 10b, with TASK-010).
14. HO-20's behaviours this envelope relies on — the concurrency-error detector, savepoint nesting, the raw lock string and FU-10's reconnection — and HO-13's lock order, measured from the locks actually held, each pass a TL-05 test that fails on a changed framework version (TEST_STRATEGY §6).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the failure mapper and the lock-set planner (TL-03)
- Feature: replay and outcomes on a sample command (TL-04)
- Integration: two-connection races of M- scenarios for the envelope — architecture challenge 2a, 3a, 6a, 6c and 18a on test commands — and a replay after a simulated lost answer (TL-05; 15c, 19a); C-50's trigger refusing a direct write of a future business date; HO-20's concurrency-error detector, savepoint nesting, raw lock string and FU-10's reconnection; HO-13's held locks in ascending canonical order, with the plans of the multi-row lock statements (LR-05)
- Authorization: re-authorization at replay (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: INT-015–INT-017 server answers (TL-08)
- Browser E2E: N/A — CJ-21 in TASK-064
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: allowed fields only (WS-01) (TL-14)
- Performance: lock waits within budget; PFM-20 for commands once they exist (TL-15)
- Migration: C-50's trigger migration from empty and over the schema of TASK-005–TASK-009, expand-only (TL-16)
- Other: GAP-031: `ReconcileGuards` detects a seeded difference

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

- TASK-016 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- take a lock outside the canonical order or retry a command with a new `command_id`
- use the cache or queue as a source of truth
