# TASK-008 — Schema — INV tables, inventory and lot-cost representations

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create, expand-only, the INV tables of DATABASE §4.7 with the inventory and lot-cost representations of §5 and §6.

**WHY THIS EXISTS:**

One physical pool with separate economic attribution, the ledger with its guard balances, lots, reservations, counts and the unattributed-loss cases are the inventory's truth (DATABASE §5; CAP-06).

**DEPENDS ON:**

- TASK-007 — Schema — PRJ, CST, PUR and ADM tables

**BLOCKS:**

- TASK-009 — Schema — FUL and FIN tables, finance and tax representations
- TASK-028 — Rack locations and minimum stock
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution

**PARALLEL SAFE:** NO — the schema Tasks run their migrations in one order

**IN SCOPE:**

- the tables `locations`, `inventory_lots`, `serial_units`, `stock_movements`, `stock_movement_entries`, `receiving_lines`, `dispatch_lines`, `stock_consumptions`, `stock_consumption_balances`, `stock_restorations`, `stock_product_balances`, `stock_scope_balances`, `stock_lot_balances`, `stock_reversal_balances`, `reservations`, `reservation_events`, `stock_counts`, `stock_count_scopes`, `stock_count_lines`, `stock_count_findings`, `unattributed_loss_cases`, `unattributed_loss_candidates`, `unattributed_loss_exposures`, `unattributed_loss_group_exposures`, `unattributed_loss_resolutions`, `unattributed_loss_resolution_lines`, with their C- rows — C-03, C-05, C-24, C-27, C-37 and C-55 — and IX- indexes — IX-11 and IX-14
- the movement catalogue and sign discipline of §5.3, the lot claims of §5.4, the serial representation of §8 and the conservation identity of §6.2
- the migrate-from-empty test extended (TL-16)

**OUT OF SCOPE:**

- the behaviour that writes these tables — the module Tasks
- any table of another schema Task

**REFERENCES:**

- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory)
- [DATABASE §5](../04-architecture/DATABASE/s05-inventory.md#5-inventory-architecture)
- [DATABASE §6](../04-architecture/DATABASE/s06-lot-cost-hpp.md#6-lot-cost-and-hpp-architecture)
- [DATABASE §8](../04-architecture/DATABASE/s07-10-representations.md#8-serial-representation)
- [DATABASE §13–17](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
- [DATABASE §18–19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue)
- [DATABASE §20](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design)
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-
- Architecture challenge — the sequence 11a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (48): D-DB-01–D-DB-14; C-03; C-05; C-24; C-27; C-37; C-55; IX-11; IX-14; table locations; table inventory_lots; table serial_units; table stock_movements; table stock_movement_entries; table receiving_lines; table dispatch_lines; table stock_consumptions; table stock_consumption_balances; table stock_restorations; table stock_product_balances; table stock_scope_balances; table stock_lot_balances; table stock_reversal_balances; table reservations; table reservation_events; table stock_counts; table stock_count_scopes; table stock_count_lines; table stock_count_findings; table unattributed_loss_cases; table unattributed_loss_candidates; table unattributed_loss_exposures; table unattributed_loss_group_exposures; table unattributed_loss_resolutions; table unattributed_loss_resolution_lines

**EXPECTED MODULES / FILE AREAS:**

- `database/migrations/` for these modules; their Eloquent models with casts and relations; synthetic factories

**BUSINESS RULES:**

- the constraints of DATABASE §18 for these tables, each enforced where its row says — DB or APP — and named after its C- row; history append-only and corrections by revision, void, reversal or return, never a hard delete (DATABASE §13; BR-CR-01); the snapshot fields of DATABASE §14 (BR-SN-01–BR-SN-03)

**ARCHITECTURE RULES:**

- one PostgreSQL database and one schema; module ownership by table-name family (D-DB-01); public identifiers UUIDv7 beside internal keys (DATABASE §16); money `numeric(18,2)` and quantities `numeric(18,3)` exactly as DATABASE §17 states; dates in WIB as §17 states
- migrations expand-only: add, never drop or rename within a release (PRQ-04; GAP-012); every table, column, key, check, unique and exclusion constraint from DATABASE as written — a gap found is an executor discovery for Planning, never an invented column

**SECURITY / PERMISSION REQUIREMENTS:**

- the database roles of DATABASE §28 and INFRASTRUCTURE §4.4 — the runtime role without DDL, the migration role used only by migrations (H9-01); no production connection

**COMPANY SCOPE AND FIELD PROJECTION:**

- each company-owned table carries its company key as DATABASE §15 states, so TASK-011's scope resolver and TASK-012's projection can apply it; POOL and MASTER tables as classified there

**CONCURRENCY / IDEMPOTENCY:**

- the guard and balance rows of DATABASE §3.1 created with their checks, so the lock order and guards of TASK-013 have their rows; first-use rows where FU- needs them

**AUDIT:**

- the tables are created; writing to them is the commands' Tasks — the audit tables themselves are TASK-005's

**DATABASE IMPACT ASSESSMENT:**

- Schema change: PROPOSED — DATABASE §4.7, §5 and §6, expand-only
- Migration required: YES — expand-only
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the tables of the modules named, by family
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

- Context7: REQUIRED — PostgreSQL and Laravel migration features for partial unique, exclusion and check constraints, generated columns and constraint triggers
- Graphify: N/A — no domain code to analyse
- Browser E2E: N/A — no page
- Other: PostgreSQL in the compose stack (TASK-001)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which foreign keys reach the tables of earlier schema Tasks
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: the constraint kinds these tables need in the installed PostgreSQL
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page
- Indonesian default: N/A — no page
- English support: N/A — no page
- Plain-language Indonesian: N/A — no page
- Language switch impact: NONE
- Mobile-first: N/A — no page
- Responsive: N/A — no page
- Adaptive: N/A — no page
- Visual breathing room: N/A — no page
- Comfortable density: N/A — no page
- shadcn/ui reuse: N/A — no page
- Design reference: N/A — no page
- Primary action: N/A — no page
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — no interaction

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

1. Migrating creates exactly the tables named, with every column, key and constraint DATABASE states.
2. Each C- row of these tables — C-03, C-05, C-24, C-27, C-37 and C-55 — is enforced where its row says, proven by a test that violates it.
3. Each IX- index of these tables — IX-11 and IX-14 — exists and its query uses it.
4. AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 holds at the database level (C-04), with the reservation bounds of C-05.
5. The migrations are expand-only — nothing a running release still reads is dropped or renamed, the mixed-version safeguards of PRQ-04 kept — and run from empty in CI (architecture challenge 11a).

**TEST REQUIREMENTS:**

- Lint: TL-01 on migrations and models
- Typecheck: TL-02
- Unit: casts and derived-value conventions (TL-03)
- Feature: N/A — no endpoint
- Integration: each C- row of these tables violated and refused at its level (TL-05); C-04 and C-05 under two connections
- Authorization: N/A — no route
- Route integrity: N/A — no route
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no copy
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13 still passes
- Security: the runtime role's privileges on the new tables as DATABASE §28 states
- Performance: each IX- index of these tables present and used in `EXPLAIN` (TL-15)
- Migration: migrate from empty after TASK-005 and the earlier schema Tasks; no destructive step (TL-16)
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

- TASK-009 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add, drop or rename a table or column the DATABASE documents do not state, or relax a constraint to make a test pass
- write a destructive migration or a data migration against any shared environment
