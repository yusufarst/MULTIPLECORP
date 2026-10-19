# TASK-026 — Clients and suppliers

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the shared party masters: PG-054 *Klien* — client organization → unit → its addresses and PICs, search-first so that no duplicate organization is created — and PG-055 *Pemasok* — suppliers chosen directly, with each company's own derived purchase-price history —, their create, edit and archive commands, and the inline creation from the project and purchase forms (ROUTE-054, ROUTE-055, ROUTE-141–ROUTE-144; WF-MD-02, WF-MD-03; J-MD-02, J-MD-03).

**WHY THIS EXISTS:**

CAP-02 and AC-02 need one generic organization → unit → PIC hierarchy in which UNY, UGM and every other client are ordinary rows with unit-specific addresses, and suppliers selected directly with their previous actual price and date (REF-009–REF-011); the masters are shared across companies, so their S0 identity is open while their relations and price history must never leak another company's business (CS-06; PJ-09; PJ-10; GAP-006). Projects, purchases and the opening import (TASK-032, TASK-037, TASK-057) pick these records.

**DEPENDS ON:**

- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-032 — Projects — list, form, journey page and amendment
- TASK-037 — Purchases — project and replenishment purchases
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — its party rows are master rows at LK-08 that the project and purchase forms create inline, so the Tasks building those forms rely on its commands

**IN SCOPE:**

- PG-054 with NAV-054 and its view ROUTE-054 (every active account, S0 names; units, addresses and PICs with `projects.view`; SCR-50): search-first — the name typed for a new organization lists the equal and prefix names first, then the ranked ones (PF-21; J-MD-02) —, the organization's detail with its units, unit addresses and PICs, and its related projects only as a scope-filtered list without a count (UXN-23; PJ-10; D-IA-14)
- ROUTE-141 and ROUTE-142 with INT-141 and INT-142 (`parties.manage`): create an organization, a unit with its address, or a PIC — also inline from PG-013, returning to the form with the new value chosen —, edit, and archive: an archived organization or unit not selectable for new projects, open projects continuing, issued outputs keeping the address as used (WF-MD-02; BR-SN-02)
- PG-055 with NAV-055 and its view ROUTE-055 (S0 names; tax id, address and contact with `projects.view` or `cost.view`; SCR-51): the supplier chosen directly — no comparison, scoring or ranking step (CAP-02; AC-02) —, and its purchase-price history derived per company from that company's own purchases, shown only with `cost.view` for companies in scope, never pooled (PJ-09; REF-011; BR-ACC-02)
- ROUTE-143 and ROUTE-144 with INT-143 and INT-144 (`parties.manage`): create a supplier — also inline from PG-017, returning with the supplier chosen —, edit, and archive, which blocks new purchases only while open purchases, receipts and returns continue (WF-MD-03; J-MD-03)
- PTY's query classes that the lookups inside the project and purchase forms read (IP-17), under the same projection

**OUT OF SCOPE:**

- the project form and the purchase form themselves (TASK-032, TASK-037), and the purchase facts the price history reads (TASK-037)
- opening masters from the legacy data (TASK-057); the search page and the search and lookup route ROUTE-281 with INT-005 (TASK-059)

**REFERENCES:**

- [WORKFLOWS §5.1](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-md-02--client-organization--unit--pic-both) WF-MD-02; [WF-MD-03](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-md-03--supplier-master-desk)
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-02; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-02; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-009–REF-011
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots) BR-SN-01–BR-SN-03; [BR-ACC-02](../02-domain/BUSINESS_RULES.md#access-boundary-acc)
- [DATABASE §4.3](../04-architecture/DATABASE/s04-03-pty.md#43-pty--parties); [§14](../04-architecture/DATABASE/s13-17-history-scope-types.md#14-snapshot-matrix); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-11
- [PERMISSIONS_MATRIX §6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05–CS-07; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 17–21; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-09, PJ-10; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-01
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) ROUTE-141–ROUTE-144; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-054, ROUTE-055
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) INT-141–INT-144
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-054, NAV-055; [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-054, PG-055; [§2](../07-ux-design/INFORMATION_ARCHITECTURE.md#2-derived-navigation-requirements) UXN-23; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-14
- [ADMIN_FLOW J-MD-02, J-MD-03](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data); [IP-17](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-17--search-and-lookup)
- [PERFORMANCE §5](../06-api-performance/PERFORMANCE.md#5-search) PF-19–PF-23
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-16
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-12, SM-25
- Architecture challenge — the sequence 1b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (30): O-01; CAP-02; AC-02; REF-009–REF-011; IMG-01; WF-MD-02–WF-MD-03; ROUTE-054–ROUTE-055; ROUTE-141–ROUTE-144; INT-141–INT-144; NX-01; UXN-23; D-IA-14; PG-054–PG-055; SCR-50–SCR-51; J-MD-02–J-MD-03; NAV-054–NAV-055

**EXPECTED MODULES / FILE AREAS:**

- the PTY module's actions, policies and query classes — the party lookup and the supplier price-history query reading PUR's purchase lines through PUR's query class (ARCHITECTURE §3); `resources/js/Pages/PTY` for PG-054 and PG-055; `tests/Feature/PTY`

**BUSINESS RULES:**

- BR-SN-01–BR-SN-03: masters answer "true now", snapshots "asserted then" — a changed unit, address or PIC never rewrites an issued quotation, document or committed record, and applies to later drafts only (architecture challenge 1b; C-11)
- BR-ACC-02: supplier price history is S3 company data, never reached through the shared master
- no institution-specific module — UNY and UGM are ordinary rows (WF-MD-02; REF-009); archive, never delete, a master with live history (BR-CR-06 ARCHIVE; FS-02)

**ARCHITECTURE RULES:**

- PTY is tier 1 (ARCHITECTURE §3); no stored supplier-price table — the history is derived per company from purchases (DATABASE §4.3); no UNIQUE on an organization's name — duplicates are prevented by search-first creation and its warning, while a unit's name is unique within its organization (DATABASE §4.3)

**SECURITY / PERMISSION REQUIREMENTS:**

- the views are every active account's for S0 names (RC-03; `DIR-081` OB-03), each other part under its own read class; the commands `parties.manage`, whose prerequisite is `projects.view` (PERMISSIONS_MATRIX §4); every command also needs a company in scope (CS-05)
- no count, total or facet reveals a relation outside the actor's scope (PJ-10; CS-07)

**COMPANY SCOPE AND FIELD PROJECTION:**

- masters belong to no company (CS-06): `client_organizations` name ALL, `npwp` PV(any); `client_units`, `client_addresses` and `client_contacts` PV(any); `suppliers` name ALL, tax id, address and contact PV(any) or CV(any) (PERMISSIONS_MATRIX §7.2 rows 17–21; PJ-10)
- related projects and purchases only through CS-07-filtered queries; the price history only from purchases of companies in scope, per company, with `cost.view` (PJ-09; RD-05)

**CONCURRENCY / IDEMPOTENCY:**

- every command is NX-01 with `command_id` (CI-01): the master rows locked at LK-08 in the fixed table order — parties first — and changed by the conditional `lock_version` update, a stale edit answering CONFLICT (ST-01); a lost race on a unit's name is CONFLICT (BD-16; SQ-08); replay re-authorized (CI-05)
- the inline creation is its own command with its own answer before the form that opened it continues (IP-19)

**AUDIT:**

- every create, edit and archive is a business audit event in its transaction with before/after, the individual actor and authority class ADM (BR-XC-01; LG-01; AZ-10); the audit's keys projected like their columns, so a PIC's details reach only `projects.view` holders (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PTY, tier 1, read by PRJ and PUR through its query classes; the price-history query reads PUR through PUR's query class (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no institution-specific module, no supplier comparison or scoring (WF-MD-02; WF-MD-03)

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

- Context7: REQUIRED — PostgreSQL `pg_trgm` word similarity and the pattern-class index of IX-17 at the installed versions
- Graphify: OPTIONAL — once the project and purchase forms exist
- Browser E2E: REQUIRED — J-MD-02, J-MD-03 and CJ-19's party masters
- Other: FX-07's Indonesian names and fragments for the search-first lists (TEST_STRATEGY §4)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the PTY module
- Pre-change questions: which forms open the inline creation and which outputs snapshot a party's identity
- Expected affected areas: PTY; the lookups of the project and purchase forms

**CONTEXT7:**

- Topics to verify: `pg_trgm` operators and thresholds; escaping of `ILIKE` wildcards in the framework's query builder
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — J-MD-02's and J-MD-03's archive statements of what continues
- Language switch impact: keeps unsent input
- Mobile-first: YES — PG-054 `[both]`, search-first; PG-055 `[desk]`, readable on the phone (NV-06)
- Responsive: YES
- Adaptive: YES — on the phone the lookup and a one-column create form inside the project flow; on the desktop list, detail and inline editing (J-MD-02)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), filters (PT-07), list continuation (PT-08), search and type-ahead with "Buat {Objek}" (PT-27), forms (PT-09)
- Design reference: PG-054 and PG-055 as INFORMATION_ARCHITECTURE §8.6 states them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: the search-first creation — the lookup's "Buat {Objek}" (PT-27)
- Secondary actions: edit; archive
- Destructive actions: none — archive keeps history and states what continues (WF-MD-02; WF-MD-03)
- Redundancy constraints: one creation path per record, inline or on the page, the same command (ROUTE-141, ROUTE-143)
- Loading state: PT-19
- Empty state: a search without a match says so and offers "Buat {Objek}" with the term kept (PT-27; D-DS-21)
- Error state: the duplicate list at the creation (INT-141); a duplicate supplier name answered as J-MD-03 states; a unit name already in its organization; CONFLICT on a stale `lock_version`

**ROUTE IMPACT:**

- ROUTE-054 (PG-054's view), ROUTE-055 (PG-055's view); ROUTE-141 (create a client organization, unit, address or PIC), ROUTE-142 (edit or archive a client), ROUTE-143 (create a supplier), ROUTE-144 (edit or archive a supplier)

**INTERACTION CONTRACTS:**

- INT-141–INT-144; the lists' INT-007 and INT-008 as TASK-015 and TASK-016 build them

**NAVIGATION CONTRACT:**

- Parent: none — *Menu lengkap* › *Data Master* › *Klien* and › *Pemasok* (NAV-054, NAV-055)
- Entry: *Data Master* › *Klien*; *Data Master* › *Pemasok*; inline from PG-013 and PG-017
- Back action: N/A — both are top-level lists (NAV-054, NAV-055 name none); the inline creation returns to its form
- Cancel: "Batal" in a form, MSG-35 first with unsent input (NV-02)
- Success destination: the client's or the supplier's record; for an inline creation the form it was opened from with the new value chosen
- Error behavior: NV-04 — validation at the field, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: PG-054 `[both]`, search-first; PG-055 `[desk]`

**ACCEPTANCE CRITERIA:**

1. Creating an organization first lists the existing ones whose names equal or begin with the term, then the ranked matches within PF-21's bound, and a creation that matches answers with that list (J-MD-02; PF-21; INT-141).
2. UNY, UGM and another client follow the same organization → unit → PIC flow, each project choosing its unit address (AC-02; REF-009; REF-010).
3. After a unit, address or PIC changes, a quotation or document issued before shows the values it was issued with, and only a later draft shows the new ones (architecture challenge 1b; BR-SN-01; C-11).
4. An archived organization or unit is not offered for a new project while its open projects continue; an archived supplier is not offered for a new purchase while open purchases, receipts and returns continue (WF-MD-02; WF-MD-03).
5. A supplier is chosen directly with no comparison step, and its previous actual price and date appear per company only with `cost.view` for companies in scope, never pooled (AC-02; REF-011; PJ-09).
6. Every active account sees organization and supplier names; units, addresses and PICs need `projects.view` with a company in scope, supplier details `projects.view` or `cost.view`, and no list, count or facet reveals a relation of a company outside scope (PJ-10; UXN-23; CS-07).
7. A unit name already in the organization is refused, and two concurrent creations of it give one COMMITTED and one CONFLICT (BD-16).
8. An inline creation from PG-013 or PG-017 returns to the form with the new value chosen and the form's input kept (ROUTE-141; ROUTE-143; IP-19).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the name normalization and ranking order of the search-first list (TL-03)
- Feature: ROUTE-054, ROUTE-055 and ROUTE-141–ROUTE-144 with each refusal (TL-04)
- Integration: two concurrent creations of one unit name (BD-16); the price-history query over purchases of two companies (TL-05)
- Authorization: S0 to every active account, S2 relations by capability, `parties.manage` for the commands (TL-06)
- Route integrity: ROUTE-054, ROUTE-055, ROUTE-141–ROUTE-144 audited (TL-07)
- Interaction: INT-141–INT-144, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-MD-02 inline from PG-013 on the phone and the desktop, J-MD-03 inline from PG-017, CJ-19's party masters (TL-09)
- Localization/language switch: both languages on PG-054 and PG-055 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01's masters reached through a shared master and the search probes, SM-12's count oracle, SM-25's projection of the price history (TL-14)
- Performance: PFM-01 and PFM-02 for both pages; PFM-06 for the search-first lists on FX-07 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: N/A — nothing beyond the layers above

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

- TASK-032 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add a supplier comparison, scoring, ranking or automatic supplier selection, or store a supplier price table (CAP-02; SH-01; DATABASE §4.3)
- delete a party with live history, or rewrite an issued output when a master changes (FS-02; BR-SN-01)
- pool one company's supplier prices into another company's view, or count relations outside scope (PJ-09; PJ-10)
