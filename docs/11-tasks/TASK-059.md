# TASK-059 — Search and scanning

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build search and scanning: PG-009 *Cari* with the shell's "Cari atau pindai kode" and Ctrl K, on the search request `Reporting Search`, which TASK-016 builds for it and for every lookup inside a form (ROUTE-281; INT-005; IP-17) — an exact code first, then names and titles from three characters, scope-first, cut at 200 candidates —, and scanning into *Cari* through the scanner's exact lookup `INV ScanLookup`, which TASK-039 builds behind the scan field of the warehouse pages (ROUTE-282; INT-006; IP-15; SF-SCAN), keyboard-wedge scanners treated as keyboards and a scan never submitting a command.

**WHY THIS EXISTS:**

CAP-17 and AC-23 require fast authorized lookup of product, SKU, barcode, project, client and document, and AC-03 that a known code resolves the intended item while an unknown or ambiguous one is never selected silently (REF-050; BR-INV-09). Search over company-owned rows is a classic leak: a result, count or ranking computed before the scope filter reveals another company's records (CS-07; DP-06; architecture challenge 8b). The scanner sends Enter whatever the screen's state, so a scan must never record a fact (GAP-015), and the real devices — USB scanners, phones, no camera — are unproven until the device UAT (DEP-03; GAP-020).

**DEPENDS ON:**

- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-040 — Stock views, stock history and movement reversal

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**PARALLEL SAFE:** NO — it presents the two lookup routes every form picker and warehouse scan field shares (TASK-016, TASK-039), on their shared trigram and exact indexes

**IN SCOPE:**

- PG-009 with NAV-009 and its view ROUTE-009 (every active account, S0; `DIR-081` OB-03; SCR-08): results by type — products, clients, suppliers, projects, documents —, an exact code match first with "Kode Cocok", at most one page; the full-screen search on the phone (D-IA-04); the term never in the address, the title or the history (IA §11; DP-02; H7-10)
- ROUTE-281 with INT-005 and INT-281, `Reporting Search` — built by TASK-016, moved there in Part 4 because the forms built before PG-009 need their lookups (IP-17), and presented here — for PG-009, the shell's type-ahead and every lookup inside a form — product, client, unit, PIC, supplier, project and invoice pickers (PT-27; IA §10): a code — barcode or SKU, serial, project number, formatted document number — looked up exactly first, one hit opening or selecting it, several rows sharing the key within scope listed for the user (PF-19); a name of at least three consecutive letters or digits by the trigram indexes, names equal to or beginning with the term first, a type-ahead of at most 20 rows (PF-20; PF-21); a term without a trigram answered by exact and prefix lookups only with MSG-24 (PF-23); more than 200 candidates cut with MSG-23; at most 100 characters (WS-03)
- scope first (PF-22; CS-07): a search over company-owned rows carries the company filter in the same statement — the company as the index condition for a scope that is not every company —, S0 master names searched without a filter, a client unit's name only with `projects.view` and a company in scope (PJ-10; CS-06); each result under its view capability (PJ-10); no result, count or empty state hinting at an out-of-scope record (DP-06; IA §10)
- ROUTE-282 with INT-006 and INT-282, `INV ScanLookup` — built by TASK-039, moved there in Part 4 because the warehouse pages built before this Task scan through it, and used here for scanning into *Cari* — (`stock.view`, the pool, S1): the exact lookup of a scanned code under the scanner's burst-sized limiter, separate from search (QB-06; WS-12) — the product, lot or serial in place; an unknown code MSG-25 with "Daftarkan Barcode" for `products.edit`, nothing created; an ambiguous code MSG-37 with the candidates for the user's choice; a serial that does not fit the operation MSG-36, its state in words (SF-SCAN; BR-INV-09)
- the scan field (PT-20) and its behaviour, built with ROUTE-282 by TASK-039 for every page that hosts one — PG-019, PG-022, PG-024, PG-026, PG-027, PG-028, PG-034 (IA §10), each page placing it in its own Task — and placed on *Cari* here: one scan target saying "Siap Memindai", focused on entering the step and after each scan; Enter in it only looks up and adds or selects; a burst outside a field that accepts scans discarded with DESIGN_SYSTEM §13.2's notice; a serial scanned twice in one command counted once; quantity items scanned once with their quantity entered; with no scanner the same field taking a typed SKU, "Cari Produk" beside it; feedback in text and by a non-colour cue in a polite live region, errors assertive (IP-15; UXI-34; DESIGN_SYSTEM §13.2); on the phone fixed at the bottom above the action bar (D-DS-08)
- scanning into *Cari* — the desktop scanner typing into the field with focus, the phone's full-screen *Cari* —, an exact lookup only, the matched product, serial or lot opening its stock detail PG-019 (IA §10; QB-06)

**OUT OF SCOPE:**

- building ROUTE-281's search request with its query classes per result type (TASK-016) and ROUTE-282's scan lookup with the scan field's shared behaviour (TASK-039), both moved in Part 4: this Task presents them on PG-009 and *Cari* and tests them there, the rules below being theirs as this page relies on them
- each warehouse page's command and its server-side scan checks inside the command (TASK-039, TASK-041–TASK-044, TASK-048); "Daftarkan Barcode" itself (TASK-027); the lists' own exact-number search fields on PG-010, PG-035 and PG-041 (TASK-032, TASK-029, TASK-049)
- camera barcode scanning — outside V1 (D-UX-09; V1_SCOPE D-05); the real-device checks of scanners and phones at the device UAT (TASK-070, TASK-086)
- CJ-07 and CJ-12 end to end (TASK-062, TASK-064)

**REFERENCES:**

- [WORKFLOWS §6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-SCAN; [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-09
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-03, CAP-17; [DEP-03](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-03, AC-23; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-050
- [PERFORMANCE §5](../06-api-performance/PERFORMANCE.md#5-search) PF-19–PF-23; [§6](../06-api-performance/PERFORMANCE.md#6-query-budgets) QB-03, QB-06; [§12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-17; [DATABASE §21](../04-architecture/DATABASE/s20-24-index-storage-migration.md#21-search)
- [PERMISSIONS_MATRIX §6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05–CS-07; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-03, PJ-10; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-02, DP-06
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-03, WS-12; [§8](../05-security/SECURITY.md#8-threat-model) TM-43
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#reading-search-scanning-and-exports-reporting-pg-009-pg-051pg-053) ROUTE-281, ROUTE-282; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-009
- [INTERACTION_CONTRACTS §2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-005, INT-006; [§3](../03-workflows/INTERACTION_CONTRACTS.md#reading-search-scanning-and-exports-reporting-pg-009-pg-051pg-053) INT-281, INT-282
- [NAVIGATION_CONTRACTS §2.1](../07-ux-design/NAVIGATION_CONTRACTS.md#21-system-account-and-beranda) NAV-009
- [INFORMATION_ARCHITECTURE §8.1](../07-ux-design/INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda) PG-009; [§10](../07-ux-design/INFORMATION_ARCHITECTURE.md#10-search-lookup-and-scanner-entry-points); [§11](../07-ux-design/INFORMATION_ARCHITECTURE.md#11-page-addressing); [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-08
- [ADMIN_FLOW §9](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-17--search-and-lookup) IP-17 and [IP-15](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-34, UXI-39; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#lists-search-scanner-and-exports) UXS-33–UXS-35; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-20, MSG-23–MSG-25, MSG-36, MSG-37
- [DESIGN_SYSTEM §4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-08; [§9.2](../07-ux-design/DESIGN_SYSTEM.md#92-lists-tables-and-filters) PT-27; [§9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-20; [§10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) Search and scan; [§13](../07-ux-design/DESIGN_SYSTEM.md#13-keyboard-and-scanner)
- [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-06; [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-07; [§12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat); [SECURITY_TEST_MATRIX §3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-14
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-009--dispatch-locks-alone-do-not-protect-stock-counts-and-serial-scans) GAP-009; [GAP-015](../00-governance/GAP_REGISTER.md#gap-015--scannersession-behavior-can-create-false-success-or-duplicate-intent); [GAP-020](../00-governance/GAP_REGISTER.md#gap-020--mobile-first-and-existing-usb-scanners-do-not-prove-device-compatibility)
- Architecture challenge — the sequence 8b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (44): O-01; CAP-03; CAP-17; DEP-03; AC-03; AC-23; REF-050; IMG-01; IMG-04; IMG-09; SF-SCAN; ROUTE-009; ROUTE-281–ROUTE-282; INT-005–INT-006; INT-281–INT-282; WS-03; TM-43; PF-19–PF-23; PG-009; SCR-08; UXI-34; UXI-39; IP-15; IP-17; UXS-33–UXS-35; D-DS-08; PT-20; PT-27; DA-06–DA-07; NAV-009; component Search and scan; GAP-009; GAP-015; GAP-020

**EXPECTED MODULES / FILE AREAS:**

- `ShowSearch` and PG-009's presentation of each result type (the search query classes TASK-016's, INV's exact scan lookup query class TASK-039's); the shared scan-field and search components (PT-20, PT-27) in `resources/js/Components`; PG-009 in `resources/js/Pages/Reporting`; `tests/Feature/Reporting`, `tests/Feature/INV` for ROUTE-282, the browser suite's scanner cases

**BUSINESS RULES:**

- SF-SCAN and BR-INV-09: identification by manufacturer or internal barcode, serial or manual lookup; unknown → a registration offer, never auto-created; ambiguous → never auto-selected; a repeated scan of one serial in one command counts once; the serial's state must fit the operation; quantity items are not forced into per-unit labels (AC-03)
- a search reads and changes nothing; codes are looked up exactly and names by prefix and similarity (PF-19–PF-21); no external search service exists (DATABASE §21; D-PF-03)

**ARCHITECTURE RULES:**

- PostgreSQL alone: exact B-tree lookups for codes, `pg_trgm` GIN indexes for names and titles, the pattern-class indexes of IX-17 for equality and prefix (DATABASE §21; PF-21; IX-17); scanner input always goes to the exact lookup, never to trigram search (PF-19; QB-06)
- a search over company-owned rows is scope-first in its plan as well as its result — a plan that lets the fragment choose first is refused by PERFORMANCE §12's procedure (PF-22)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-009 and ROUTE-281 every active account (S0), each result under its view capability (PJ-10); ROUTE-282 `stock.view` over the pool (S1); neither carries `command_id` — read-only (RC-04)
- WS-03's search input — at most 100 characters, trigram search from three, LIKE wildcards escaped; WS-12's search limiter, 60 per minute per account, and the scanner's own burst-sized limiter; a limit answered with MSG-20 (TM-43)
- the term sent in the request body, never in the address, the title or the history (DP-02; H7-10)

**COMPANY SCOPE AND FIELD PROJECTION:**

- company-owned results — projects, documents, invoices, purchases, serial history — filtered by the scope resolver inside the statement, before ranking and the candidate bound (CS-07; PF-22; architecture challenge 8b); a project or document number that encodes a company shown only within scope (PJ-23); S0 masters to every active account, master S2 relations by PJ-10 (CS-06)
- a scan answers the pooled physical view — product, lot code, location, condition, serial presence — to `stock.view` (PJ-01); another company's lot only with the relation marker (PJ-04), its history only within scope (PJ-03; DP-06)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — search and scan lookups are read-only requests outside SF-CMD and carry no `command_id` (RC-04); a page read takes no row lock (PF-12); a scan inside a command is re-checked by that command under its locks (SF-SCAN; GAP-009)

**AUDIT:**

- N/A — a lookup commits nothing, so no business audit event exists for it (BR-XC-01 covers committed actions); the security events of SECURITY LG-02 stay as TASK-010 writes them

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: Reporting, tier 7, reading every module's searchable records through their query classes; INV's scan lookup (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack; `pg_trgm` is a PostgreSQL contrib module
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no search engine, full-text index or ranking service (D-PF-03)

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

- Context7: REQUIRED — PostgreSQL `pg_trgm` operators, thresholds and GIN index use; shadcn's Command and Combobox; keyboard-event handling of a wedge scanner's burst in the browser
- Graphify: OPTIONAL — the pickers of the earlier Tasks call ROUTE-281
- Browser E2E: REQUIRED — UXS-33–UXS-35 and the scanner cases of CJ-07 and CJ-12 on the CI stack; the real scanners at Layer 3 (TEST_STRATEGY §12)
- Other: FX-07's Indonesian institution names and fragments; the scale fixtures FX-06 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the pickers and scan fields that call the two routes
- Pre-change questions: which pages host a scan field and which forms a picker; which result types each persona's projection admits
- Expected affected areas: Reporting's search, INV's scan lookup, the shared PT-20 and PT-27 components and every page that uses them

**CONTEXT7:**

- Topics to verify: `word_similarity` and `similarity` thresholds and the GIN and GiST trigram operator classes; pattern-operator-class B-tree indexes; shadcn Command's keyboard model
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG-23, MSG-24, MSG-25, MSG-36, MSG-37 and DESIGN_SYSTEM §13.2's scan notices as written
- Language switch impact: keeps the term in the field, never in the address
- Mobile-first: YES — `[both]`; full-screen search on the phone; the scan field fixed at the bottom on a scan step (D-DS-08)
- Responsive: YES
- Adaptive: YES — the top-bar field with Ctrl K on the desktop, the full-screen *Cari* on the phone (NAV-009)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: Command in a Dialog and Combobox for search and pickers (PT-27), the Input Group scan field (PT-20), Kbd for the shortcut (DESIGN_SYSTEM §10, Search and scan)
- Design reference: PG-009 and the entry points of INFORMATION_ARCHITECTURE §10; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: N/A — a search sends no command; choosing a result opens or selects it
- Secondary actions: "Cari Produk" beside the scan field; "Daftarkan Barcode" for `products.edit` on an unknown code
- Destructive actions: none
- Redundancy constraints: one search entry, "Cari atau pindai kode", for typed and scanned codes (D-IA-04); one scan field per scan step (IP-15)
- Loading state: the type-ahead's busy state after 300 ms (PT-19)
- Empty state: no result — the empty state with the term kept, and a search that matches nothing says so (ROUTE-281; NAV-009)
- Error state: MSG-24 for a short term, MSG-23 for a cut list, MSG-20 for a limit, MSG-25, MSG-36 and MSG-37 for scans; a failed lookup answering in place (IP-17)

**ROUTE IMPACT:**

- ROUTE-009 (PG-009's view); ROUTE-281 (search and in-form lookups, built by TASK-016) and ROUTE-282 (scan lookup, built by TASK-039), used here

**INTERACTION CONTRACTS:**

- INT-005 and INT-281 (its pointer), built with ROUTE-281 by TASK-016, and INT-006 and INT-282 (its pointer), built with ROUTE-282 by TASK-039, as PG-009 and *Cari* use them; INT-003's shell entries reaching *Cari* (TASK-016)

**NAVIGATION CONTRACT:**

- Parent: the page search was opened from (NAV-009)
- Entry: the top bar, Ctrl K, the phone's *Cari*
- Back action: "Kembali" or Esc → the page it was opened from (NAV-009; NV-01)
- Cancel: Esc closes the search without a result
- Success destination: the record chosen; a scanned product, serial or lot → PG-019 (IA §10)
- Error behavior: no result shows the empty state with the term kept; a failed lookup answers in place (IP-17)
- State preservation: the term never enters the address (IA §11; DP-02); the origin kept as the whitelisted return key (NV-01)
- Mobile behavior: `[both]` — full-screen search; the scan field at the bottom above the action bar (D-DS-08)

**ACCEPTANCE CRITERIA:**

1. A code — barcode, SKU, serial, project or document number — is looked up exactly first: one hit opens or selects it, several rows sharing the key within scope are listed for the user, and none is ever chosen by the system (PF-19; IP-17; AC-03).
2. A name of at least three consecutive letters or digits finds products, clients, suppliers and the titles of projects in scope, names equal to or beginning with the term first; a shorter term or one without a trigram gets exact and prefix matches only with MSG-24; more than 200 candidates show MSG-23 (PF-20; PF-21; PF-23; UXS-33).
3. A search or lookup by an Admin of Company A never returns, counts or ranks a record of Company B, and its plan reads no index entry of another company when the scope is not every company (CS-07; PF-22; DP-06; architecture challenge 8b).
4. The term never appears in the address, the page title or the browser history (DP-02; H7-10).
5. A scanned code goes only to the exact lookup: an unknown code shows MSG-25 with "Daftarkan Barcode" for `products.edit` and creates nothing; an ambiguous code lists its candidates for the user's choice; a serial that does not fit the operation is refused at the scan with its state in words (SF-SCAN; UXS-34).
6. A scanner burst never submits a command: Enter in the scan field only looks up, and a burst arriving while focus is outside a field that accepts scans is discarded with its notice (IP-15; DESIGN_SYSTEM §13.2; GAP-015).
7. Without a scanner the same field takes a typed SKU and "Cari Produk" sits beside it; on the phone the scan field stays at the bottom above the action bar and a multi-line receipt runs one line at a time, scan-led (UXS-34; UXS-35; D-DS-08).
8. The search and scanner limiters refuse with MSG-20 at their limits, the scanner's separate from search (WS-12; TM-43).
9. The type-ahead's and the exact scan lookup's query counts stay within QB-03 and QB-06 in CI; their times — 200 ms and 150 ms at 1x and 10x — are measured on staging by TASK-068's scripts in TASK-079, never reported as met from CI (PFM-06; QB-03; QB-06; PERFORMANCE_TARGETS §1).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: term classification — code, name, too short, no trigram — and wildcard escaping (TL-03)
- Feature: ROUTE-009, ROUTE-281 and ROUTE-282 per persona and result type (TL-04)
- Integration: the trigram thresholds and the 200-candidate bound on FX-07; the exact lookups with shared keys (TL-05)
- Authorization: each result under its view capability; `stock.view` for ROUTE-282 (TL-06)
- Route integrity: ROUTE-009, ROUTE-281 and ROUTE-282 audited (TL-07)
- Interaction: INT-005 and INT-006 with their *Test* — the term never in the address, the scanner's burst limit separate from search (TL-08)
- Browser E2E: UXS-33–UXS-35; the scanner cases of CJ-07 and the search cases of CJ-12 (TL-09)
- Localization/language switch: both languages on PG-009, the pickers and the scan messages (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12 — the scan feedback in a polite live region, errors assertive, focus held in the scan field (IP-15)
- Production build: TL-13
- Security: SM-01's search and exact lookup by SKU, barcode, serial and number against Company B; SM-14 for the search and scanner limiters (TL-14)
- Performance: PFM-06; QB-03 and QB-06 through PFM-01 and PFM-02; PFM-03's plans for the scoped title search and the type-ahead, the GiST and multicolumn GIN candidates measured (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: DEP-03's and GAP-020's real-device scanner and phone checks prepared for the device UAT (TEST_STRATEGY §12; TASK-070)

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

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- select a record for the user on an ambiguous code, create a product or barcode from a scan, or let a scanner's Enter send a command (SF-SCAN; IP-15)
- rank, count or cut a result list before the scope filter, or put a search term in the address (CS-07; DP-02)
- introduce an external search service, a full-text engine or camera scanning (D-PF-03; D-UX-09)
