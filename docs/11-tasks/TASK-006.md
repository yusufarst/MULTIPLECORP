# TASK-006 — Schema — ORG, PTY, CAT and DOC tables

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-20 the Owner's decision [DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier) adds the foreign keys of TASK-005's columns that reference this Task's tables — a scope line, criterion 6 and its integration test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) on this Task's five execution discoveries adds the files' company and purpose guard of DIR-089 (5) — a scope line, the database impact's words, criterion 7 and its integration test — and records the disposition of criterion 1 below the acceptance criteria, its other parts given to TASK-007–TASK-009, TASK-029 and TASK-030; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs; the DATABASE text it builds is approved under APPR-018.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create, expand-only, the ORG, PTY, CAT and DOC tables of DATABASE §4.2–§4.4 and §4.10 with their constraints and indexes.

**WHY THIS EXISTS:**

Companies, numbering and tax, parties, the catalogue and documents are tier 1 (ARCHITECTURE §3); every higher module refers to them.

**DEPENDS ON:**

- TASK-005 — Schema foundations — IAM and OPS tables, history and types

**BLOCKS:**

- TASK-007 — Schema — PRJ, CST, PUR and ADM tables
- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-022 — Jobs, files and the document renderer

**PARALLEL SAFE:** NO — the schema Tasks run their migrations in one order

**IN SCOPE:**

- the tables `companies`, `company_bank_accounts`, `numbering_schemes`, `number_sequences`, `retired_numbers`, `tax_types`, `tax_rate_versions`, `company_tax_profiles`, `tax_treatment_rules`; `client_organizations`, `client_units`, `client_addresses`, `client_contacts`, `suppliers`; `products`, `units`, `product_units`, `product_barcodes`, `product_price_history`, `product_images`; `document_types`, `documents`, `document_versions`, `document_renditions`, `file_objects`, `evidence_documents`, `evidence_document_versions`, `bank_statement_lines`, `evidence_balances`, `evidence_links`, with their C- rows — C-01, C-10–C-12, C-18–C-20, C-23, C-32, C-48, C-49, C-52, C-53, C-58 and C-59 — and IX- indexes — IX-02, IX-07, IX-08, IX-11, IX-14, IX-15 and IX-17
- a constraint or index that spans the tables of several schema Tasks — C-05, C-09, C-23, C-28, C-32, C-47–C-49, C-53, C-59, IX-08, IX-11 and IX-14–IX-17 among them — built here for these tables, the other schema Tasks building their own parts (DATABASE §18; PERFORMANCE §12)
- the multi-unit representation of DATABASE §7 and the document architecture of §10; the file metadata of §22
- the foreign keys of TASK-005's columns that reference `companies` and `file_objects`, added expand-only with `ALTER TABLE … ADD CONSTRAINT` ([DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier)): `user_company_grants.company_id`, `correction_cases.company_id`, `import_rows.company_id`, `legacy_archive_items.company_id` and `audit_events.company_id` to `companies`; `legacy_archive_items.file_object_id` in the company-scoped form DATABASE §15 states, `import_batches.source_file_id` and `export_requests.file_object_id` to `file_objects`
- the files' company and purpose, as DATABASE §4.10 and §22 state them ([DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (5); C-32): `file_objects`' company by purpose, a CHECK; and a trigger on each table that references `file_objects` — `evidence_document_versions`, `document_renditions`, `product_images`, `companies`' identity assets, `legacy_archive_items`, `import_batches` and `export_requests` —, at every insert and update of the reference, refusing another company's file, a file of no company outside the purposes DATABASE allows it and a file of another purpose; no column added
- the migrate-from-empty test extended (TL-16)

**OUT OF SCOPE:**

- the behaviour that writes these tables — the module Tasks
- any table of another schema Task

**REFERENCES:**

- [DATABASE §4.2](../04-architecture/DATABASE/s04-02-org.md#42-org--organization)
- [DATABASE §4.3](../04-architecture/DATABASE/s04-03-pty.md#43-pty--parties)
- [DATABASE §4.4](../04-architecture/DATABASE/s04-04-cat.md#44-cat--catalog)
- [DATABASE §4.10](../04-architecture/DATABASE/s04-10-doc.md#410-doc--documents--evidence)
- [DATABASE §7, 10](../04-architecture/DATABASE/s07-10-representations.md#7-multi-unit-representation)
- [DATABASE §12](../04-architecture/DATABASE/s11-12-finance-tax.md#12-tax-architecture)
- [DATABASE §22](../04-architecture/DATABASE/s20-24-index-storage-migration.md#22-file-and-document-storage-metadata)
- [DATABASE §13–17](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
- [DATABASE §18–19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue)
- [DATABASE §20](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design)
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-
- Architecture challenge — the sequence 11a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (70): REF-057; D-DB-01–D-DB-14; C-01; C-10–C-12; C-18–C-20; C-23; C-32; C-48–C-49; C-52–C-53; C-58–C-59; IX-02; IX-07–IX-08; IX-11; IX-14–IX-15; IX-17; table companies; table company_bank_accounts; table numbering_schemes; table number_sequences; table retired_numbers; table tax_types; table tax_rate_versions; table company_tax_profiles; table tax_treatment_rules; table client_organizations; table client_units; table client_addresses; table client_contacts; table suppliers; table products; table units; table product_units; table product_barcodes; table product_price_history; table product_images; table document_types; table documents; table document_versions; table document_renditions; table file_objects; table evidence_documents; table evidence_document_versions; table bank_statement_lines; table evidence_balances; table evidence_links; migration-rule DB24-03; migration-rule DB24-08; migration-rule DB24-09

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

- Schema change: PROPOSED — DATABASE §4.2–§4.4 and §4.10 tables, and DIR-089 (5)'s guard of the files' company and purpose, expand-only
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
2. Each C- row the database enforces on these tables — C-01, C-10–C-12, C-18–C-20, C-23, C-32, C-48, C-49, C-52, C-53, C-58 and C-59 — is enforced where its row says, proven by a test that violates it.
3. Each IX- index of these tables — IX-02, IX-07, IX-08, IX-11, IX-14, IX-15 and IX-17 — exists and its query uses it.
4. C-10's numbering uniqueness and monotonicity hold at the database level as its row states.
5. The migrations are expand-only — nothing a running release still reads is dropped or renamed, the mixed-version safeguards of PRQ-04 kept — and run from empty in CI (architecture challenge 11a).
6. Each foreign key DIR-088 assigns to this Task from TASK-005's columns exists and refuses a row that references no `companies` or `file_objects` row, or another company's where the key carries the company, proven by a referential-integrity test before this Task is DONE (C-32).
7. The database holds every file to its owning record's company and to its purpose, as DATABASE §4.10 and §22 state under DIR-089 (5): `file_objects` refuses a company its purpose does not take, and each reference to `file_objects` refuses another company's file, a file of no company outside the purposes DATABASE allows it and a file of another purpose, at insert and at update, and an import source, an export or an evidence file taken by a second record of its table, each refusal proven by a negative test; a test proves every reference to `file_objects` guarded (C-32). An actor's right to a file — its uploader or requester — is not this Task's and is not claimed (TASK-030, TASK-057, TASK-060).

**CRITERIA DISPOSITIONS (DIR-089):**

- Criterion 1: every table, column, key and constraint DATABASE states for these tables exists but three parts, which DIR-089 gives to the Tasks that can build them: the foreign keys of `documents`' nine subjects, of `document_versions.quotation_revision_id` and `invoice_version_id` and of fifteen of `evidence_links`' seventeen targets, added by TASK-007 (8), TASK-008 (4) and TASK-009 (14) when they create the referenced tables, each with its referential-integrity test (DIR-089 (1)); the type-free evidence identity index, designed for its queries, built and proven in `EXPLAIN` by TASK-030 (DIR-089 (2)); the fourteen rows of `document_types`, data that TASK-029 seeds (DIR-089 (3)). Those parts are not met by this Task and are not claimed.
- Criteria 2–6 are unaffected; criterion 7 is DIR-089 (5)'s. A product image has no end of life in V1 (DIR-089 (4); DATABASE §19), so this Task adds no column for it.

**TEST REQUIREMENTS:**

- Lint: TL-01 on migrations and models
- Typecheck: TL-02
- Unit: casts and derived-value conventions (TL-03)
- Feature: N/A — no endpoint
- Integration: each C- row of these tables violated and refused at its level (TL-05); C-10 and C-11 under two connections; each foreign key DIR-088 adds from TASK-005's columns violated and refused; DIR-089 (5)'s CHECK and each file reference's guard violated and refused, and every reference to `file_objects` found guarded
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

- [x] Lint: Pint and ESLint PASS, locally and in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and the TypeScript compiler PASS on the workstation and in CI (TL-02); its 17 errors on `2d5e19c` fixed in `4c6fc59`
- [x] Relevant automated tests: Unit, Architecture, Integration — every C- row of these tables, C-10 and C-11 under two connections, the forward keys, the runtime's privileges and DIR-089 (5)'s guard —, TL-15's index register and TL-16, PASS locally and in CI
- [x] Authorization: N/A — no route; TL-06 EMPTY until TASK-011; an actor's right to a file is TASK-030's, TASK-057's and TASK-060's (DIR-089 (5))
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: N/A — no INT- contract
- [x] Browser E2E if required: N/A — no page; the smoke run still PASS on Chromium and WebKit in CI
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no page
- [x] Production build: TL-13 — the frontend build and the production caches and configuration — PASS on the workstation and in CI
- [x] Security checks if required: the runtime role's privileges on the new tables (DATABASE §28); every file held to its owning record's company and purpose, one owner per import source, export and evidence file (DIR-089 (5); C-32); the secret-like scan and both dependency audits PASS
- [x] Database safety review: the 34 migrations only add — nothing dropped, renamed or retyped, no data migration —, each `down()` reverses its migration (TL-16, 53 migrations up, down and up); local and CI databases only, no production connection
- [x] Final diff review: each table, column, key and constraint against DATABASE as written, the gaps raised as discoveries and decided by the Owner (DIR-089), nothing invented; ER-06's independent review of the build in the cloud session, its findings fixed or raised; the review of DIR-089 (5)'s delta stopped by the Owner's directive (DIR-089 part 2), CI the final proof

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `9bd807b`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 39946466968 (push) and 39946472209 (pull request #6), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, Larastan/PHPStan at level `max`, the database tests, the audits, the secret-like scan and the production check among them), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15's index register and the k6 smoke run, TL-16 from empty). The Owner's workstation, 2026-10-23: `scripts/verify.sh` and `scripts/verify.sh browser performance` PASS on `4c6fc59` (CI run 39925682508 PASS), and for DIR-089 (5)'s delta Pint, Larastan/PHPStan, `FileOwnershipTest` (27 tests) and TL-16 PASS
- Build result: `TL-13 Frontend build: PASS` and `TL-13 Production caches and configuration: PASS — Evidence: 9bd807b, CI ubuntu-24.04 run 39946466968`
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract
- Browser evidence: N/A — no page or journey; the smoke run still passes on both engines (`TL-09 E2E/browser: PASS — Evidence: 9bd807b, CI ubuntu-24.04 run 39946466968`)
- Localization evidence: N/A — no page or copy
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted; PostgreSQL 18.6's `pg_trgm` and `btree_gist`, shipped with it
- Files changed: `database/migrations/` (`2026_10_20_000020`–`000051`, `2026_10_23_000052` and `000053`), `app/Support/Database/`, `app/Modules/ORG/`, `PTY/`, `CAT/` and `DOC/` (models and READMEs), the TASK-005 models' relations, `database/factories/`, the tests of `tests/Integration/Schema/`, `tests/Performance/IndexRegisterTest.php`, `tests/Unit/Support/` and `tests/Support/Refusal.php`; DIR-089's records — the eighty-seventh source record, DECISION_LOG, DECISION_INDEX, DATABASE (APPR-018), the contracts it amends, TASK_PLAN —, TOOLCHAIN's rows, this file, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `2d5e19c`, `4c6fc59`, `62d9945` and `9bd807b` — the last the fix of the automated review's P1 finding on pull request #6: a seeded counter could stand below its seed, now refused by the CHECK `number_sequences_c10_seed_floor` (seed positive, `next_value` ≥ seed, at insert and update; C-10; NM-03) with its regression test, `OrganizationConstraintsTest` and `NumberingConcurrencyTest` PASS —, the Owner's identity, no signature and no AI attribution; this record's update follows them; earlier the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-006-schema-org-pty-cat-doc`
- Notes: criteria 2–7 as their tests prove them; criterion 1 met but for the three parts DIR-089 gives to TASK-007–TASK-009, TASK-029 and TASK-030, not claimed (CRITERIA DISPOSITIONS). Environments: the cloud session of 2026-10-20 and the Owner's Windows workstation of 2026-10-23 (TOOLCHAIN, "Execution environments (TASK-001)"). Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-007 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add, drop or rename a table or column the DATABASE documents do not state, or relax a constraint to make a test pass
- write a destructive migration or a data migration against any shared environment
