# TASK-005 — Schema foundations — IAM and OPS tables, history and types

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-20 the Owner's decision [DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier) on this Task's three execution discoveries records the dispositions of criteria 2 and 3 below the acceptance criteria; the criteria are unchanged and no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** VERIFYING

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create, expand-only, the IAM and OPS tables of DATABASE §4.1 and §4.13 with their constraints and indexes, and the shared foundations every later table uses: identifiers, money, quantity and date types, history and snapshot conventions, the guard registry and the database roles.

**WHY THIS EXISTS:**

Every module writes through identity, audit, the command log and correction cases; the type, identifier and history conventions must exist once, before any module table (DATABASE §1–§3, §13–§17).

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness

**BLOCKS:**

- TASK-006 — Schema — ORG, PTY, CAT and DOC tables
- TASK-010 — Audit and security logging
- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit
- TASK-017 — Password sign-in, sessions and credential links

**PARALLEL SAFE:** NO — the migrations of TASK-005–TASK-009 run in one order

**IN SCOPE:**

- the eight IAM tables — `users`, `roles`, `capabilities`, `role_capabilities`, `user_roles`, `user_capability_grants`, `user_company_grants`, `user_external_identities` — and the seven OPS tables — `audit_events`, `security_events`, `command_log`, `correction_cases`, `import_batches`, `import_rows`, `legacy_archive_items` — with their C- rows and IX- indexes
- the infrastructure stores PostgreSQL holds outside the 125 business tables, each never projected raw and holding identifiers rather than business data (PERMISSIONS_MATRIX §7.2; DP-09): the framework session table AU-06 places in PostgreSQL, as SECURITY §2 states it; the credential-token store of AU-11 — for each link its account, purpose (ONBOARDING, RESET, OWNER_RECOVERY), issuer, none for an OWNER_RECOVERY link, keyed token hash, issue and expiry times and use or revocation — with IX-04's keys, its rows the lock class LK-04; the `export_requests` record of H6-04 — requester, report, whitelisted filters, company set, view-capability set, masking context, state (PENDING, RUNNING, READY, FAILED, WITHHELD), claim time and claim count, file and expiry — with IX-05; and the framework's failed-job table, its payloads identifiers only (H9-04; LG-04); the queue, cache, lock and limiter stores being Valkey (INFRASTRUCTURE §2), no table of theirs is created, nor a job-batch table, V1 dispatching no framework batch (CONCURRENCY_IDEMPOTENCY JB-07; ARCHITECTURE §11)
- the shared conventions: UUIDv7 public identifiers, the exact numeric types, WIB business dates, the history and correction representation (DATABASE §13), the snapshot matrix (§14), the guard registry (§3.1)
- the database roles of DATABASE §28 (H9-01) as local and CI definitions
- the migrate-from-empty test of TL-16

**OUT OF SCOPE:**

- tables of any other module (TASK-006–TASK-009)
- any behaviour that writes the tables (TASK-010, TASK-013, TASK-017 onward)
- the stores' behaviour — credential links and their purge (TASK-017), export requests (TASK-060), job retention (TASK-022)

**REFERENCES:**

- [DATABASE §1–3](../04-architecture/DATABASE/s01-03-foundations.md#1-conventions)
- [DATABASE §4.1](../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access)
- [DATABASE §4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations)
- [DATABASE §13–17](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
- [DATABASE §18–19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue)
- [DATABASE §20, 23, 24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design)
- [DATABASE §28](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#28-security-ready-data-design)
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-
- [INFRASTRUCTURE §4.4](../09-operations/INFRASTRUCTURE.md#44-database-roles)
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-06, AU-11
- [CONCURRENCY_IDEMPOTENCY §17](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#17-p5-obligations-h6-01h6-12) H6-04
- [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) — the infrastructure tables outside the 125
- [INFRASTRUCTURE §2](../09-operations/INFRASTRUCTURE.md#2-topology)
- Architecture challenge — the sequences 11a, 11b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (62): REF-057; BR-SN-01–BR-SN-03; D-DB-01–D-DB-14; C-32; C-40; C-46–C-48; C-53; C-62–C-65; AU-11; H6-04; H9-01; H9-04; IX-03–IX-05; IX-09; IX-13–IX-15; IX-18–IX-19; TL-16; table users; table roles; table capabilities; table role_capabilities; table user_roles; table user_capability_grants; table user_company_grants; table user_external_identities; table audit_events; table security_events; table command_log; table correction_cases; table import_batches; table import_rows; table legacy_archive_items; migration-rule DB24-01; migration-rule DB24-02; migration-rule DB24-03; migration-rule DB24-08; migration-rule DB24-10

**EXPECTED MODULES / FILE AREAS:**

- `database/migrations/` for IAM and OPS; the Eloquent models of these tables with their casts and relations; `database/factories/` for them (synthetic)

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

- Schema change: PROPOSED — DATABASE §4.1 and §4.13 tables and the infrastructure stores of PERMISSIONS_MATRIX §7.2, expand-only
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
- Pre-change questions: which conventions the later schema Tasks must reuse unchanged
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: UUIDv7 generation in the installed framework or PostgreSQL version; exclusion constraints with btree_gist; `numeric` casts in Eloquent
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

1. Migrating an empty database creates exactly the 15 tables of §4.1 and §4.13 with every column, key and constraint DATABASE states, and the infrastructure stores named in scope — the session table of AU-06, the credential-token store of AU-11, `export_requests` of H6-04 and the failed-job table —, the stores' keys proven by tests that violate them: a second unused, unrevoked link for one account and purpose, a repeated token hash (IX-04) and an export request in an unknown state.
2. Each C- row the database enforces on these tables — C-32, C-46, C-47, C-48, C-53 and C-62–C-64 — is enforced where its row says, proven by a test that violates it, a row that also spans the tables of later schema Tasks built here for these tables; the schema parts of C-40 — the capabilities and `authority_class` — and of C-65 — the TOTP columns — are created here, their behaviour proven by TASK-011 and by TASK-018 and TASK-023 (DATABASE §18).
3. Each IX- index of these tables and stores — IX-03, IX-04, IX-05, IX-09, IX-13, IX-18 and IX-19, IX-14's partial `import_rows` and `security_events` indexes and IX-15's partial `import_batches` index — exists with the verification PERFORMANCE §12 asks.
4. Money, quantity and date columns use exactly the types of DATABASE §17.
5. The migrations are expand-only — nothing a running release still reads is dropped or renamed — with the mixed-version safeguards of PRQ-04, job payload versions and one-release command validity among them, and run again from empty in CI (TL-16; architecture challenge 11a).
6. The import tables `import_batches` and `import_rows` hold the committed import identity on which BD-11's keys rest, so the opening import run again creates no second lot, receivable, credit or project for one legacy row, every created fact carrying its `import_row_id` (DATABASE §24; AX-28; BD-11; architecture challenge 11b, with TASK-057).

**CRITERIA DISPOSITIONS (DIR-088):**

- Criterion 2, C-32: this Task builds C-32's part on its tables — the company keys `correction_cases` (`company_id`, `id`), `legacy_archive_items` (`company_id`, `id`) and `import_rows` (`id`, `company_id`) that later references carry their company in, each proven by a composite reference that refuses another company's row. The foreign keys of its columns that reference later tables are added, expand-only, by the Task that creates each referenced table, each proven by a referential-integrity test before that Task is DONE: TASK-006 (`companies`, `file_objects`), TASK-007 (`projects`), TASK-008 (`inventory_lots`). Those keys are not met by this Task and are not claimed.
- Criterion 3, IX-13: built here — the company and the entity history with the tiebreaker `id`, the actor's history, and the Owner review's partial index over the authority classes ADM_PLUS and OWNER_ONLY. Deferred to TASK-058 — the partial indexes over the acknowledged and over the routed action codes and the routed codes' `(command_id)` index — until the Tasks it depends on define those codes; no code is invented, and the obligation stays open until TASK-058 is DONE.
- Criteria 1, 4, 5 and 6 are unaffected. The export requests' key, a UUIDv7 that is their public identifier (DIR-088 (3)), is part of criterion 1's store.

**TEST REQUIREMENTS:**

- Lint: TL-01 on migrations and models
- Typecheck: TL-02
- Unit: casts and value conventions of DATABASE §16–§17 (TL-03)
- Feature: N/A — no endpoint
- Integration: each C- row of these tables violated and refused at its level; the stores' keys violated — a second unused, unrevoked link for one account and purpose, a repeated token hash, an export request in an unknown state (TL-05)
- Authorization: N/A — no route
- Route integrity: N/A — no route
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no copy
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13 still passes
- Security: the runtime role cannot run DDL; the migration role is used only by migrations (H9-01)
- Performance: each IX- index of these tables present and used by its query in `EXPLAIN` (TL-15; PERFORMANCE §12)
- Migration: migrate from empty, roll forward again, no destructive step — nothing a running release reads dropped or renamed (TL-16; PRQ-04; architecture challenge 11a)
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

- TASK-006 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add, drop or rename a table or column the DATABASE documents do not state, or relax a constraint to make a test pass
- write a destructive migration or a data migration against any shared environment
