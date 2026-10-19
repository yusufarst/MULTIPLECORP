# TASK-009 — Schema — FUL and FIN tables, finance and tax representations

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create, expand-only, the FUL and FIN tables of DATABASE §4.9 and §4.12 with the financial and tax representations of §11 and §12.

**WHY THIS EXISTS:**

Fulfilment and finance are tier 6; the five financial concepts never share a row (DATABASE §11.1; BR-FIN-01).

**DEPENDS ON:**

- TASK-008 — Schema — INV tables, inventory and lot-cost representations

**BLOCKS:**

- TASK-012 — Field projection, read classes and cross-company links

**PARALLEL SAFE:** NO — the schema Tasks run their migrations in one order

**IN SCOPE:**

- the tables `dropship_confirmations`, `dropship_confirmation_lines`, `deliveries`, `delivery_lines`, `delivery_closures`, `service_handovers`, `service_handover_lines`; `invoices`, `invoice_versions`, `invoice_version_lines`, `invoice_tax_components`, `invoice_balances`, `invoice_billing_acts`, `payments`, `payment_claimed_deductions`, `application_source_balances`, `payment_applications`, `deduction_settlements`, `receivable_writeoffs`, `receivable_dispute_holds`, `opening_customer_credits`, `prepayment_kuitansi_links`, `disbursements`, `other_cash_receipts`, `financial_contras`, `money_fact_balances`, with their C- rows and IX- indexes
- the invoice, billing and receivable chain of §11.2, payments and applications of §11.3, settlements and write-offs of §11.4 and other cash of §11.5
- the migrate-from-empty test extended (TL-16)

**OUT OF SCOPE:**

- the behaviour that writes these tables — the module Tasks
- any table of another schema Task

**REFERENCES:**

- [DATABASE §4.9](../04-architecture/DATABASE/s04-09-ful.md#49-ful--fulfillment)
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance)
- [DATABASE §11–12](../04-architecture/DATABASE/s11-12-finance-tax.md#11-financial-architecture)
- [DATABASE §13–17](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
- [DATABASE §18–19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue)
- [DATABASE §20](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design)
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (45): D-DB-01–D-DB-14; C-13; C-15; C-44–C-45; IX-11; table dropship_confirmations; table dropship_confirmation_lines; table deliveries; table delivery_lines; table delivery_closures; table service_handovers; table service_handover_lines; table invoices; table invoice_versions; table invoice_version_lines; table invoice_tax_components; table invoice_balances; table invoice_billing_acts; table payments; table payment_claimed_deductions; table application_source_balances; table payment_applications; table deduction_settlements; table receivable_writeoffs; table receivable_dispute_holds; table opening_customer_credits; table prepayment_kuitansi_links; table disbursements; table other_cash_receipts; table financial_contras; table money_fact_balances

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

- Schema change: PROPOSED — DATABASE §4.9, §4.12, §11 and §12, expand-only
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
2. Each C- row of these tables is enforced where its row says, proven by a test that violates it.
3. Each IX- index of these tables exists and its query uses it.
4. The application conservation and outstanding ≥ 0 rules hold where their rows say (C-13, C-15).
5. The migrations are expand-only and run from empty in CI.

**TEST REQUIREMENTS:**

- Lint: TL-01 on migrations and models
- Typecheck: TL-02
- Unit: casts and derived-value conventions (TL-03)
- Feature: N/A — no endpoint
- Integration: each C- row of these tables violated and refused at its level (TL-05); C-13 and C-15 under two connections
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

- TASK-012 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add, drop or rename a table or column the DATABASE documents do not state, or relax a constraint to make a test pass
- write a destructive migration or a data migration against any shared environment
