# TASK-007 — Schema — PRJ, CST, PUR and ADM tables

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-20 the Owner's decision [DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier) adds the foreign keys of TASK-005's columns that reference this Task's tables — a scope line, criterion 6 and its integration test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) adds the foreign keys of TASK-006's columns that reference this Task's tables — a scope line, criterion 7 and its integration test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Amendment: on 2026-10-23 the Owner's decision [DIR-090](../00-governance/DECISION_LOG.md#dir-090--task-007s-discoveries) (2), (3), (4) and (6), with APPR-019's canonical text, adds the guards that hold this Task's references within their parent and a direct cost attribution within the purchase's company, and the SIPLAH details' draft cascade — a scope line, its database impact, criterion 8 and its integration test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create, expand-only, the PRJ, CST, PUR and ADM tables of DATABASE §4.5, §4.6, §4.8 and §4.11 with their constraints and indexes.

**WHY THIS EXISTS:**

Projects with their commercial chain, costing, procurement and the administrative requirements are tiers 2–4 (ARCHITECTURE §3).

**DEPENDS ON:**

- TASK-006 — Schema — ORG, PTY, CAT and DOC tables

**BLOCKS:**

- TASK-008 — Schema — INV tables, inventory and lot-cost representations

**PARALLEL SAFE:** NO — the schema Tasks run their migrations in one order

**IN SCOPE:**

- the tables `projects`, `project_siplah_details`, `project_lines`, `commercial_confirmations`, `confirmation_line_pins`, `quotation_revisions`, `quotation_revision_lines`, `line_scope_cancellations`, `project_line_balances`, `project_commercial_balances`, `project_state_transitions`; `purchases`, `purchase_lines`, `purchase_line_tax_components`, `purchase_charges`, `purchase_charge_line_shares`, `purchase_line_balances`, `purchase_line_closures`, `purchase_line_adjustments`; `lot_cost_entries`, `lot_cost_balances`, `cost_attributions`, `purchase_charge_balances`, `purchase_charge_share_balances`, `expenses`; `admin_requirements`, `admin_requirement_links`, `admin_requirement_waivers`, with their C- rows — C-05, C-09, C-21, C-23, C-25, C-26, C-28–C-32, C-38, C-47–C-49, C-53, C-57 and C-59 — and IX- indexes — IX-08, IX-11, IX-14, IX-16 and IX-17
- a constraint or index that spans the tables of several schema Tasks — C-05, C-09, C-23, C-28, C-32, C-47–C-49, C-53, C-59, IX-08, IX-11 and IX-14–IX-17 among them — built here for these tables, the other schema Tasks building their own parts (DATABASE §18; PERFORMANCE §12)
- the project and commercial representation of DATABASE §9
- the foreign keys of TASK-005's columns that reference `projects`, added expand-only with `ALTER TABLE … ADD CONSTRAINT` ([DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier)): `correction_cases` (`company_id`, `project_id`) to `projects` (`company_id`, `id`), C-32's composite key, and `correction_cases.causal_project_id`, an intentional cross-company link (DATABASE §15)
- the foreign keys of TASK-006's columns that reference these tables, added expand-only with `ALTER TABLE … ADD CONSTRAINT` ([DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (1)), composite with `company_id` where DATABASE §4.10 and §15 state it: `documents.project_id` and `purchase_id`; `document_versions.quotation_revision_id`; `evidence_links.project_id`, `commercial_confirmation_id`, `purchase_id`, `expense_id` and `admin_requirement_id`
- the migrate-from-empty test extended (TL-16)
- APPR-019's guards ([DIR-090](../00-governance/DECISION_LOG.md#dir-090--task-007s-discoveries) (2)–(4)), no column added: a pin's and a quotation line's project line within their confirmation's or revision's project, a charge share's line within its charge's purchase, a share-schedule row's share of its own charge and version, a direct cost attribution's purchase records within the purchase's company that bears it, a fallback expense's line within its charge's purchase, and `project_siplah_details` in a DRAFT project's cascade

**OUT OF SCOPE:**

- the behaviour that writes these tables — the module Tasks
- any table of another schema Task

**REFERENCES:**

- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial)
- [DATABASE §4.6](../04-architecture/DATABASE/s04-06-pur.md#46-pur--procurement)
- [DATABASE §4.8](../04-architecture/DATABASE/s04-08-cst.md#48-cst--costing--expenses)
- [DATABASE §4.11](../04-architecture/DATABASE/s04-11-adm.md#411-adm--administration)
- [DATABASE §9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation)
- [DATABASE §13–17](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
- [DATABASE §18–19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue)
- [DATABASE §20](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design)
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-
- Architecture challenge — the sequence 11a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (68): REF-057; D-DB-01–D-DB-14; C-05; C-09; C-21; C-23; C-25–C-26; C-28–C-32; C-38; C-47–C-49; C-53; C-57; C-59; IX-08; IX-11; IX-14; IX-16–IX-17; table projects; table project_siplah_details; table project_lines; table commercial_confirmations; table confirmation_line_pins; table quotation_revisions; table quotation_revision_lines; table line_scope_cancellations; table project_line_balances; table project_commercial_balances; table project_state_transitions; table purchases; table purchase_lines; table purchase_line_tax_components; table purchase_charges; table purchase_charge_line_shares; table purchase_line_balances; table purchase_line_closures; table purchase_line_adjustments; table lot_cost_entries; table lot_cost_balances; table cost_attributions; table purchase_charge_balances; table purchase_charge_share_balances; table expenses; table admin_requirements; table admin_requirement_links; table admin_requirement_waivers; migration-rule DB24-03; migration-rule DB24-07

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

- Schema change: PROPOSED — DATABASE §4.5, §4.6, §4.8 and §4.11 tables, expand-only; APPR-019's triggers, keys and draft cascade on them (DIR-090)
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
2. Each C- row the database enforces on these tables — C-05, C-09, C-21, C-23, C-25, C-26, C-28–C-32, C-38, C-47–C-49, C-53, C-57 and C-59 — is enforced where its row says, proven by a test that violates it.
3. Each IX- index of these tables — IX-08, IX-11, IX-14, IX-16 and IX-17 — exists and its query uses it.
4. The per-line conservation and pin rows of the commercial chain hold as their C- rows state.
5. The migrations are expand-only — nothing a running release still reads is dropped or renamed, the mixed-version safeguards of PRQ-04 kept — and run from empty in CI (architecture challenge 11a).
6. Each foreign key DIR-088 assigns to this Task from TASK-005's columns exists and refuses a row that references no `projects` row, or another company's where the key carries the company, proven by a referential-integrity test before this Task is DONE (C-32).
7. Each of the eight foreign keys DIR-089 (1) assigns to this Task from TASK-006's columns exists and refuses a row that references no row of its table, or another company's where the key carries the company, proven by a referential-integrity test before this Task is DONE (C-32).
8. Each guard APPR-019 adds (DIR-090 (2)–(4)) refuses a reference outside its parent or a direct cost attribution outside the purchase's company, and a DRAFT project's deletion takes its SIPLAH details with it, proven by a negative test before this Task is DONE.

**TEST REQUIREMENTS:**

- Lint: TL-01 on migrations and models
- Typecheck: TL-02
- Unit: casts and derived-value conventions (TL-03)
- Feature: N/A — no endpoint
- Integration: each C- row of these tables violated and refused at its level (TL-05); C-05 and the pins' rows; each foreign key DIR-088 adds from TASK-005's columns and DIR-089 (1) from TASK-006's violated and refused; each guard of APPR-019 violated and refused, and the SIPLAH details' draft cascade (DIR-090)
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

**CRITERIA DISPOSITIONS (DIR-090):**

- Criterion 1 is met for every column, key and constraint DATABASE states that this Task's tables can hold now; the keys and triggers of its columns on TASK-008's and TASK-009's tables — the lots, restorations, consumptions, drop-ship lines and deduction settlements, and §4.8's basis-kind and bearer triggers — are TASK-008's and TASK-009's (DIR-090 (1)), not claimed here.
- Criteria 2–8 as their tests prove them; criterion 8 adds APPR-019's guards (DIR-090 (2)–(4)).

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS, locally and in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and the TypeScript compiler PASS on the workstation and in CI (TL-02)
- [x] Relevant automated tests: Unit, Architecture, Integration — every C- row of these tables, the forward keys of DIR-088 and DIR-089 (1), APPR-019's guards, the runtime's privileges —, TL-15's index register and TL-16, PASS locally and in CI
- [x] Authorization: N/A — no route; TL-06 EMPTY until TASK-011
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: N/A — no INT- contract
- [x] Browser E2E if required: N/A — no page; the smoke run still PASS on Chromium and WebKit in CI
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no page
- [x] Production build: TL-13 — the frontend build and the production caches and configuration — PASS on the workstation and in CI
- [x] Security checks if required: the runtime role's privileges on the new tables (DATABASE §28); every reference within its company (C-32), within its parent and a direct cost within the purchase's company (APPR-019); the secret-like scan and both dependency audits PASS
- [x] Database safety review: the 30 migrations only add — nothing dropped, renamed or retyped, no data migration; the SIPLAH details' key re-created with the draft cascade inside this Task's unreleased migrations —, each `down()` reverses its migration (TL-16, 83 migrations up, down and up); local and CI databases only, no production connection
- [x] Final diff review: each table, column, key and constraint against DATABASE as written, the gaps raised as discoveries and decided by the Owner (DIR-090, APPR-019), nothing invented; ER-06's independent review, its findings fixed or raised

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `e494bce`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 39972736029 (push) and 39972742496 (pull request #7), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15's index register and load run, TL-16 from empty); before it, the build commit `793307a`, runs 39968247763 and 39968303179, PASS. On the Owner's workstation, 2026-10-23: `scripts/verify.sh` PASS, `tests/Integration/Schema` 552 tests, Unit 126, Performance and the k6 smoke run (4,102 checks) PASS, TL-16 PASS on a new empty database
- Build result: `TL-13 Frontend build: PASS` and `TL-13 Production caches and configuration: PASS — Evidence: e494bce, CI ubuntu-24.04 run 39972736029`
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract
- Browser evidence: N/A — no page or journey; the smoke run still passes on both engines (`TL-09 E2E/browser: PASS — Evidence: e494bce, CI ubuntu-24.04 run 39972736029`)
- Localization evidence: N/A — no page or copy
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted
- Files changed: `database/migrations/` (`2026_10_23_000054`–`000083`), `app/Support/Database/TableRules.php`, `app/Modules/PRJ/`, `PUR/`, `CST/` and `ADM/` (models and READMEs), `database/factories/`, the tests of `tests/Integration/Schema/`, `tests/Performance/IndexRegisterTest.php` and `tests/Unit/Support/ModelConventionsTest.php`; TOOLCHAIN's row; DIR-090's and APPR-019's records — the source record, DECISION_LOG, DECISION_INDEX, DATABASE §4.5, §4.6, §4.8 and §19, TASK-008, TASK-009 —; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `793307a` and `e494bce`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-007-schema-prj-cst-pur-adm`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-23 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each database feature verified on the running server (TOOLCHAIN's TASK-007 row). Readings within the approved text are listed in CURRENT_HANDOFF. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-008 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add, drop or rename a table or column the DATABASE documents do not state, or relax a constraint to make a test pass
- write a destructive migration or a data migration against any shared environment
