# TASK-025 — Companies, identity assets, bank accounts and tax configuration

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the company master — PG-059 *Perusahaan* with the legal data, the identity assets (logo, stamp, signature, director), the bank accounts, deactivation and the derived onboarding readiness — and PG-062 *Pajak* with the effective-dated tax configuration — tax types, rate versions, company tax profiles and treatment rules —, both Owner-only (ROUTE-059, ROUTE-062, ROUTE-131–ROUTE-135, ROUTE-138; WF-MD-01; J-MD-01).

**WHY THIS EXISTS:**

Every document, project, purchase and invoice carries a legal company's identity and, for money, its bank destination (CAP-01; AC-01; O-01); the company master is OWN! authority (WORKFLOWS §4; W4-02) and its identity changes must never alter what was issued (BR-SN-01). DIR-024 D-1 makes tax treatment configured by company, context and effective date, never a hard-coded rate or formula (BR-FIN-17; FS-13; GAP-026). The document engine, projects and invoices (TASK-029, TASK-032, TASK-049) read what this Task maintains.

**DEPENDS ON:**

- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context
- TASK-022 — Jobs, files and the document renderer

**BLOCKS:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-049 — Invoices and the pre-payment Kuitansi

**PARALLEL SAFE:** NO — it owns the company rows (LK-06) and the master and configuration rows (LK-08) that every issuing and new-business command reads

**IN SCOPE:**

- PG-059 with NAV-059 and its view ROUTE-059 (`companies.manage`; SCR-54): the companies, each with its legal data, identity assets, bank accounts and state, and the onboarding readiness list derived each time from what exists — *Lengkap* or *Belum* per line with its command, no stored progress (ADMIN_FLOW §12.4; UXI-31); the numbering lines link to PG-061 (TASK-021) and *Akses Admin* to PG-060 (TASK-024), where CAP-01's roles and grants are kept (REF-002–REF-004)
- ROUTE-131 with INT-131: create a company — legal name, legal form, NPWP, address, contact, director — a new company being a legal entity independent of the product's brand (CAP-01; REF-005; IMG-01); a duplicate legal identity refused at the field
- ROUTE-132 with INT-132: the legal data, behind the step-up when the change touches an identity asset — NPWP, an address, a contact or the director (AU-15; PJ-19); the form saying that issued documents keep the identity they were issued with (BR-SN-01; J-MD-01)
- ROUTE-133 with INT-133: deactivation after its confirmation, stating what stops — new projects, invoices and purchases — and what continues — collection, returns and corrections on existing records (BR-ACC-03; C-54)
- ROUTE-134 with INT-134: add a bank account, or change one by adding the new account and deactivating the old — a referenced account's detail is never edited (DATABASE §4.2; BR-SN-02) —, behind the step-up
- ROUTE-135 with INT-135: the logo, stamp and signature files, each uploaded first by ROUTE-228 under the purpose's allowlist (FL-02; INT-013) and then set behind the step-up
- PG-062 with NAV-062, its view ROUTE-062 and ROUTE-138 with INT-138 (`tax.configure`; SCR-56): tax types, effective-dated rate versions with their base fraction and rounding mode, company tax profiles and treatment rules by context — configured values only, never overlapping, a company rule overriding the global one for the same context, tax type and date (DATABASE §12; C-52; BR-FIN-17; DIR-024 D-1; GAP-026)
- the company query class that labels and prints a company on every other page under PJ-19's projection — the identity assets every issued document embeds, so one verified company record serves every document (O-01; REF-006; REF-032; PJ-15)

**OUT OF SCOPE:**

- numbering schemes, starts and the raise on PG-061 (TASK-021); accounts and grants — CAP-01's other half, REF-002–REF-004 — (TASK-024)
- rendering the identity assets into documents (TASK-022, TASK-029); the tax snapshot on each transaction's own rows and the tax settlements (TASK-032, TASK-037, TASK-049, TASK-052)
- uploads themselves — the upload pipeline's types, limits and inspection (TASK-022) and the upload route ROUTE-228 with INT-228 (TASK-030)

**REFERENCES:**

- [WORKFLOWS §5.1](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-md-01--company-master-desk--own) WF-MD-01; [§4](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix)
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots) BR-SN-01, BR-SN-02; [BR-ACC-03](../02-domain/BUSINESS_RULES.md#access-boundary-acc); [BR-FIN-17](../02-domain/BUSINESS_RULES.md#finance-fin); [FS-13](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts)
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-01; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-01, AC-13; [OS-03](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-01
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-001–REF-006, REF-032, REF-056; [IMG-01](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory); [OWN-02](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.2](../04-architecture/DATABASE/s04-02-org.md#42-org--organization); [§12](../04-architecture/DATABASE/s11-12-finance-tax.md#12-tax-architecture); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-11, C-32, C-52, C-54
- [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 8, 9 and 13–16; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-15, PJ-19; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-03, OD-04
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-15; [§5](../05-security/SECURITY.md#5-file-security) FL-02, FL-07
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#companies-and-settings--perusahaan-penomoran-dokumen-pajak-pg-059-pg-061-pg-062-owner_only-j-md-01) ROUTE-131–ROUTE-135, ROUTE-138; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-059, ROUTE-062
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#companies-and-settings--perusahaan-penomoran-dokumen-pajak-pg-059-pg-061-pg-062-owner_only-j-md-01) INT-131–INT-135, INT-138
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-059, NAV-062; [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-059, PG-062
- [ADMIN_FLOW J-MD-01](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data); [§12.4](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#124-company-onboarding); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-31
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01; [§4.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#42-lock-classes) LK-06–LK-08
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-09, SM-25
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-026--taxes-withheld-or-collected-at-payment-had-no-settlement-path) GAP-026
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (41): O-01; CAP-01; CAP-11; AC-01; AC-13; REF-002–REF-006; REF-016; REF-032; REF-034; IMG-01; OWN-02; OWN-05; WF-MD-01; ROUTE-059; ROUTE-062; ROUTE-131–ROUTE-135; ROUTE-138; INT-131–INT-135; INT-138; NX-01; PG-059; PG-062; SCR-54; SCR-56; UXI-31; J-MD-01; NAV-059; NAV-062; GAP-026

**EXPECTED MODULES / FILE AREAS:**

- the ORG module's company, bank-account, identity-asset and tax-configuration actions with their policies and query classes (ARCHITECTURE §3–§4); `resources/js/Pages/ORG` for PG-059 and PG-062; `tests/Feature/ORG`

**BUSINESS RULES:**

- BR-SN-01, BR-SN-02: identity changes never alter issued snapshots; the bank destination used on a document or payment stays the one recorded (AC-01)
- BR-ACC-03 and C-54: a deactivated company blocks new projects, invoices and purchases and keeps collection, returns and corrections
- BR-FIN-17 and FS-13: tax treatment configured by company, transaction context and effective date, with no hard-coded rate, treatment or formula; a transaction resolves the rule of its business date (WF-MD-01; DATABASE §12)

**ARCHITECTURE RULES:**

- ORG is tier 1 (ARCHITECTURE §3); configuration is data, never code constants (DATABASE §12); a bank account referenced by any fact is never updated — a change is a new row and a deactivation (DATABASE §4.2)
- identity-asset files are immutable `file_objects` stored through the storage abstraction of TASK-022 (ARCHITECTURE §12); the renderer reads them in system context (PJ-19; DP-17)

**SECURITY / PERMISSION REQUIREMENTS:**

- PG-059 and its commands `companies.manage`, PG-062 and ROUTE-138 `tax.configure` — both OWNER_ONLY (OD-03; OD-04); an Admin's address answers as IP-21 (AZ-11)
- the step-up on bank accounts and identity assets, and on a legal-data change that touches an identity asset (AU-15; ROUTE-132, ROUTE-134, ROUTE-135); uploads under FL-02's allowlist for the company-asset purpose; the raw stamp and signature files served only to the Owner and never to a draft's preview (PJ-19; DC-4)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the Owner's scope is every company, active or deactivated (CS-01); a new company is a new root chosen by the command (CS-03); a company outside an Admin's grants does not exist for that Admin (AZ-11)
- projection PJ-19 on every page that shows a company: name, code and state with `projects.view` in scope; NPWP, addresses, contacts, director and logo with `projects.view`, `finance.view` or `cost.view` in scope; bank accounts with `finance.view` (row 9); stamp and signature files Owner-only (row 8)
- tax rows 13–16 of PERMISSIONS_MATRIX §7.2: tax types, rate versions and global treatment rules read by every account (RD-01); company tax profiles and company rules with `finance.view` or `cost.view` in scope; changes Owner-only

**CONCURRENCY / IDEMPOTENCY:**

- every command is NX-01 with `command_id` (CI-01): lock set LK-00, LK-03, LK-06 (the company row), LK-07 (a bank account), LK-08 (the configuration rows in their fixed table order) — each row locked in phase B and changed by the conditional `lock_version` update, a stale edit answering CONFLICT (ST-01); a command changes the unique key of at most one row (LR-03); a lost race on a unique key is CONFLICT (BD-16; SQ-08)
- a deactivation takes the company row at LK-06, which every command that starts new business for the company also takes, so neither commits against the other's stale state (BR-ACC-03); replay returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- every company-master, bank-account, identity-asset and tax-configuration change is a business audit event in its command's transaction with before/after, the Owner as actor, its reason where given and authority class OWNER_ONLY (LG-01; BR-XC-01; AZ-10); no file contents in any record (LG-03)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: ORG, tier 1, read by DOC, PRJ, PUR and FIN through its query classes (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW — a new legal entity is a row, a new tax kind or rate is configuration (DATABASE §12)
- Speculative abstraction avoided: YES — no tax engine or formula, no generic rules engine (ARCHITECTURE §1; FS-13)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned; the step-up of TASK-017 reused
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — the OWNER_ONLY capabilities `companies.manage` and `tax.configure` of TASK-011 applied
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

- Context7: REQUIRED — PostgreSQL exclusion constraints over date ranges (btree_gist) and the framework's optimistic `lock_version` update, at the installed versions
- Graphify: OPTIONAL — once ORG's numbering code of TASK-021 exists
- Browser E2E: REQUIRED — J-MD-01's company and tax steps and CJ-19's company master
- Other: synthetic company fixtures only — no real NPWP, bank account or signature (DOD-21)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the ORG module
- Pre-change questions: which pages and payloads read a company's identity, bank accounts or tax configuration, and under which projection
- Expected affected areas: ORG; the company query class read by every page that labels or prints a company

**CONTEXT7:**

- Topics to verify: `EXCLUDE USING gist` over `daterange` with `coalesce(company_id, 0)`; the framework's file validation for the asset purposes
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the readiness lines and the deactivation's statement of what stops and what continues as ADMIN_FLOW §12.4 and J-MD-01 word them
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]` pages readable on the phone (NV-06)
- Responsive: YES
- Adaptive: YES — editing on the desktop; a phone shows the read-only state (J-MD-01)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the design system's forms (PT-09), the detail layout (PT-12), the readiness list (PT-29), the confirmation (PT-26) and the step-up dialog (PT-28)
- Design reference: PG-059 and PG-062 as INFORMATION_ARCHITECTURE §8.6 states them, the readiness list as ADMIN_FLOW §12.4; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: the form's save in each section of PG-059; the configuration form's save on PG-062
- Secondary actions: adding a bank account; replacing an identity asset; deactivation
- Destructive actions: deactivating a company and deactivating a bank account, each after its confirmation stating the consequence (IP-08)
- Redundancy constraints: one save per section; no control edits a referenced bank account in place
- Loading state: PT-19
- Empty state: a company without bank accounts, identity assets or a tax profile shows *Belum* on its readiness line with the command (ADMIN_FLOW §12.4)
- Error state: a duplicate legal identity at its field; a file outside the allowlist at its field (FL-02); an overlapping tax range at its field (C-52); CONFLICT on a stale `lock_version` (IP-03)

**ROUTE IMPACT:**

- ROUTE-059 (PG-059's view), ROUTE-062 (PG-062's view); ROUTE-131 (create), ROUTE-132 (legal data), ROUTE-133 (deactivate), ROUTE-134 (bank accounts), ROUTE-135 (identity assets), ROUTE-138 (tax configuration); the asset's file is uploaded first through ROUTE-228 (INT-013)

**INTERACTION CONTRACTS:**

- INT-131–INT-135, INT-138; INT-013 for the asset's upload and INT-011 for the step-up

**NAVIGATION CONTRACT:**

- Parent: none — *Menu lengkap* › *Pengaturan* › *Perusahaan* and › *Pajak* (NAV-059, NAV-062)
- Entry: *Pengaturan* › *Perusahaan*; *Pengaturan* › *Pajak*
- Back action: N/A — both are top-level settings pages (NAV-059, NAV-062 name none)
- Cancel: "Batal" in a form, MSG-35 first with unsent input (NV-02)
- Success destination: PG-059 or PG-062 with the change in place
- Error behavior: NV-04; a failed step-up answers in place (IP-10)
- State preservation: the open tab in the address; unsent input as a form's (NV-05)
- Mobile behavior: `[desk]` — read-only on the phone (J-MD-01)

**ACCEPTANCE CRITERIA:**

1. Only the Owner reaches PG-059, PG-062 and their commands; an Admin's request answers as IP-21 (OD-03; OD-04; AZ-11).
2. A company with a legal identity that already exists is refused at the field; a stale edit answers CONFLICT; one `command_id` replayed returns the recorded outcome (ROUTE-131; ST-01; CI-05).
3. A change of NPWP, an address, a contact, the director, an identity asset or a bank account asks the step-up once 15 minutes have passed (AU-15; REF-001).
4. A bank account referenced by a document or payment is never edited: a change adds the new account and deactivates the old, and the issued document and the payment keep the destination they recorded (DATABASE §4.2; BR-SN-02).
5. After a change of the company's name, NPWP, address, director or an identity asset, every version issued before it still renders the identity it was issued with (BR-SN-01; C-11; AC-01).
6. The raw stamp and signature files are served only to the Owner, never to an Admin and never to a draft's preview; a file outside the asset purpose's allowlist is refused (PJ-19; FL-02; SM-25).
7. After deactivation a new project, invoice or purchase of the company is REJECTED, while a payment, an application, a return and a correction on its existing records still commit (BR-ACC-03; C-54).
8. Two overlapping rate versions of one tax type, or two overlapping treatment rules for one company — or two global ones — of one context and tax type, are refused; a company rule overrides the global one for its date (C-52; DATABASE §12).
9. PG-059's readiness list is derived from the current state each time it is shown and stores no progress (ADMIN_FLOW §12.4).
10. An Admin with a view capability in a company's scope sees its identity assets where documents print them, its bank accounts only with `finance.view`, and nothing of a company outside scope (PJ-19; AC-13; OS-03).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the treatment-rule precedence of a company rule over the global one (TL-03)
- Feature: ROUTE-059, ROUTE-062, ROUTE-131–ROUTE-135 and ROUTE-138 with each refusal (TL-04)
- Integration: deactivation racing a command that starts new business (LK-06), and the C-52 exclusion constraints on real PostgreSQL (TL-05)
- Authorization: OWNER_ONLY on every route; an Admin's denial on every path (TL-06)
- Route integrity: ROUTE-059, ROUTE-062, ROUTE-131–ROUTE-135, ROUTE-138 audited (TL-07)
- Interaction: INT-131–INT-135 and INT-138, each with one `command_id` replayed and the step-up expiring where it applies (TL-08; IG-05)
- Browser E2E: J-MD-01's company, bank, asset and tax steps and CJ-19's company master (TL-09)
- Localization/language switch: both languages on PG-059 and PG-062 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 and SM-25 for PJ-19's projection on every path (REF-056; OWN-02), SM-04 for OD-03 and OD-04, SM-09 for the asset uploads (TL-14)
- Performance: PFM-01 and PFM-02 for PG-059 and PG-062; PFM-20 for the NX-01 commands (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-026's configured-treatment fixtures for TEST_STRATEGY §5 — the numeric annex's example 13 values set through PG-062 (BUSINESS_RULES numeric annex)

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
- hard-code a tax rate, treatment or formula, or compute a tax beyond the configured proposal DATABASE §12 allows (FS-13)
- edit a referenced bank account in place, or rewrite an issued document's identity after a master change (BR-SN-01)
- serve a raw stamp or signature file to anyone but the Owner, or put one in a draft's props (PJ-19; DC-4)
