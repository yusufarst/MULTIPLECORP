# TASK-027 — Catalog — products, services, units, barcodes, prices and images

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (4) keeps V1's approved scope for product images — an OUT OF SCOPE line records that no command removes an image; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the catalogue on PG-056 *Produk & Jasa*: products and services with SKU, the base unit and exact alternate units, manufacturer and internal barcodes with their label print, the serial policy, purchase, selling and SPJ-reference price defaults with their history, images and archive — with the barcode registration a scan offers (ROUTE-056, ROUTE-145–ROUTE-147; WF-MD-04; J-MD-04).

**WHY THIS EXISTS:**

CAP-03 and AC-03 require one catalogue in which a known code resolves the intended item, an unknown or ambiguous scan never selects one silently, serials are traced and quantity items carry no forced per-unit code, and price defaults stay distinct from what transactions snapshot (REF-012–REF-015; BR-INV-09; BR-SN-01). Base-unit quantities with exact conversions are the single quantity truth every stock and cost rule rests on (BR-INV-03; L-31; GAP-025), so the catalogue comes before projects, purchases, the import and scanning (TASK-032, TASK-037, TASK-057, TASK-059).

**DEPENDS ON:**

- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context
- TASK-022 — Jobs, files and the document renderer

**BLOCKS:**

- TASK-032 — Projects — list, form, journey page and amendment
- TASK-037 — Purchases — project and replenishment purchases
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-059 — Search and scanning

**PARALLEL SAFE:** NO — a serial-policy or scale change locks the product's guard (LK-13) that every movement of the product takes, and every quantity Task depends on its units

**IN SCOPE:**

- PG-056 with NAV-056 and its view ROUTE-056 (every active account; price defaults by family, PJ-14; stock figures `stock.view`; SCR-52): the products and services, search-first and addressable (D-IA-14; UXN-23), each product's units, barcodes, serial policy, price defaults with history, images and active state (REF-012), and with `stock.view` its pooled figures as CALC-01 defines them (OWN-04)
- ROUTE-145 with INT-145: create a product or service — `kind` GOODS or SERVICE deciding stock applicability, the SKU, the base unit as the smallest unit counted or sold and the quantity scale (L-31; DATABASE §7); the SKU and the base unit answered at their fields (J-MD-04)
- ROUTE-146 with INT-146: edit a product — alternate units as exact integer multiples of the base (C-09), the serial policy — switching to serialized only with zero stock on hand or the registration-count path, re-read under the product's guard (L-35; ST-09; REF-014) —, price defaults changing forward only with their history shown (CAP-03; REF-015), images uploaded first by ROUTE-228 under the image allowlist (FL-02; FL-03), and archive: not selectable for new demand or purchases, confirmed demand still fulfillable and stock still countable (WF-MD-04)
- ROUTE-147 with INT-147: "Daftarkan Barcode" from a scan (IP-15) and "Buat Barcode Internal" — a manufacturer code or an internal code from the database sequence, normalized to its `code_key`, resolving to at most one active product — the identity that receiving and dispatch scan (BR-INV-09; C-01; REF-013; REF-024; IMG-04); "Cetak Label", client-only, the browser's print of the label view writing nothing (ROUTE_CONTRACTS §4; DESIGN_SYSTEM §17; DEP-03)
- CAT's query classes for the product lookups, the exact code lookup (PF-19) and the thumbnails of lists (PF-31; PT-22) that other pages read

**OUT OF SCOPE:**

- the scan field and its lookup route ROUTE-282 (TASK-039), and the search and lookup route ROUTE-281 (TASK-016); the image pipeline, thumbnails and the upload pipeline (TASK-022); the upload route ROUTE-228 with INT-228 (TASK-030)
- rack locations and minimum stock (TASK-028); stock quantities and movements (TASK-038–TASK-043); the transaction snapshots of prices and units on each line (TASK-032, TASK-037 and the fulfilment Tasks)
- removing an image from its product: V1 keeps `AttachProductImage` alone, so an image stays with its product and only its order and primary flag change (DIR-089 (4); DATABASE §4.4 and §19)

**REFERENCES:**

- [WORKFLOWS §5.1](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-md-04--catalog-units-barcodes-serial-policy-and-price-defaults-desk) WF-MD-04; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-SCAN; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-31, L-35
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-03; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-03, AC-16; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-012–REF-015, REF-024; [IMG-04](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory); [OWN-04](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage); [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-03
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-03, BR-INV-09; [§1](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots) BR-SN-01–BR-SN-03; [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 10–11
- [DATABASE §4.4](../04-architecture/DATABASE/s04-04-cat.md#44-cat--catalog); [§7](../04-architecture/DATABASE/s07-10-representations.md#7-multi-unit-representation); [§8](../04-architecture/DATABASE/s07-10-representations.md#8-serial-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01, C-09
- [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 22–27; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-10, PJ-14; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05, CS-06
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) ROUTE-145–ROUTE-147; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-056; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own) "Cetak Label"
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) INT-145–INT-147
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-056; [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-056; [§2](../07-ux-design/INFORMATION_ARCHITECTURE.md#2-derived-navigation-requirements) UXN-23; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-14
- [ADMIN_FLOW J-MD-04](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data); [IP-15](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner)
- [DESIGN_SYSTEM §17](../07-ux-design/DESIGN_SYSTEM.md#17-print-views); [PERFORMANCE §5](../06-api-performance/PERFORMANCE.md#5-search) PF-19–PF-21; [§8](../06-api-performance/PERFORMANCE.md#8-images-and-inertia-props) PF-31, PF-32
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01, ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-16
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-09, SM-25
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-025--multi-unit-conversion-and-cost-split-arithmetic-can-silently-break-reconciliation) GAP-025
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (31): O-01; CAP-03; AC-03; AC-16; REF-012–REF-015; REF-024; REF-049; IMG-01; IMG-03–IMG-04; OWN-01; OWN-04; WF-MD-04; ROUTE-056; ROUTE-145–ROUTE-147; INT-145–INT-147; NX-01; UXN-23; D-IA-14; PG-056; SCR-52; J-MD-04; NAV-056; GAP-025

**EXPECTED MODULES / FILE AREAS:**

- the CAT module's actions — CreateProduct, UpdateProduct, AddAlternateUnit, ChangeDefaultPrice, AttachProductImage, RegisterBarcode (RC-02) — with their policies and query classes; `resources/js/Pages/CAT` for PG-056 and its label view; `tests/Feature/CAT`

**BUSINESS RULES:**

- BR-INV-03 and L-31: base-unit truth, alternates as exact integer multiples, quantities never rounded; a ratio change never rewrites a transaction's snapshot (numeric annex 11; GAP-025)
- BR-INV-09: a barcode resolves to at most one active product; an unknown or ambiguous scan is never auto-created or auto-selected; a serial's traceability survives corrections
- BR-SN-01–BR-SN-03: price defaults answer "true now" and never change a saved transaction or document value (AC-03); L-35 for the serial switch; archive with live history, never delete (FS-02)

**ARCHITECTURE RULES:**

- CAT is tier 1 (ARCHITECTURE §3); the product's guard row is born with the product, and a serial-policy or scale switch is made only under it (NX-01; ST-09); a scale change meeting rows that use the old scale is REJECTED through the scale key (DATABASE §7; C-09)
- price defaults are append-only history rows, the current default the latest (DATABASE §4.4); internal codes come from a database sequence, gaps harmless

**SECURITY / PERMISSION REQUIREMENTS:**

- the view is every active account's (RC-03; `DIR-081` OB-03); the commands `products.edit`, and a price default's change also its view capability — purchase `cost.view`, selling and SPJ-reference `finance.view` (PJ-14); every command needs a company in scope (CS-05)
- image uploads under the purpose's allowlist, stored stripped (FL-02; FL-03); "Daftarkan Barcode" offered only with `products.edit` (IP-15)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the catalogue is MASTER, no company (CS-06): `products`, `units`, `product_units`, `product_barcodes` and `product_images` S0 to every account; `min_stock_qty_base` SV; `product_price_history` S3 by kind — PURCHASE_DEFAULT CV(any), SELLING_DEFAULT and SPJ_REFERENCE FV(any) (PERMISSIONS_MATRIX §7.2 rows 22–27; PJ-14); stock figures on PG-056 only with `stock.view` (RD-02)
- a family the viewer lacks is absent, never masked or shown as zero (IA §8)

**CONCURRENCY / IDEMPOTENCY:**

- every command is NX-01 with `command_id` (CI-01): the catalogue rows at LK-08 in the fixed table order, changed by the conditional `lock_version` update (ST-01); a serial-policy switch or a scale change also locks the product's guard at LK-13 and re-reads ON HAND under it, an open form meeting the changed policy as CONFLICT (ST-09; L-35)
- SKU and active barcode uniqueness: a lost race is CONFLICT (BD-16; SQ-08; C-01); a command changes the unique key of at most one row (LR-03); replay re-authorized (CI-05)

**AUDIT:**

- every create, edit, price change, barcode registration and archive is a business audit event in its transaction with before/after and the individual actor — prices are a critical audit area (BR-XC-01; LG-01); price-default keys projected by their family in timelines (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: CAT, tier 1, read by PRJ, PUR, INV and Reporting through its query classes (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no valuation method or price engine (FS-13), no repository class per model (ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL generated columns and partial UNIQUE indexes, `numeric` scale checks, and the framework's file validation, at the installed versions
- Graphify: OPTIONAL — once the first quantity-bearing Tasks exist
- Browser E2E: REQUIRED — J-MD-04 and CJ-19's catalogue
- Other: the numeric annex's examples 10 and 11 as fixtures (FX-01); the label view printed by the browser
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the CAT module
- Pre-change questions: which rows carry the product's scale key and which commands re-read its serial policy (ST-09)
- Expected affected areas: CAT; the lookups and thumbnails other pages read

**CONTEXT7:**

- Topics to verify: generated `code_key` columns, partial UNIQUE indexes with a WHERE clause, the composite scale key; browser print styles for the label view
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the refusals of J-MD-04 in words: a barcode of another product, the serial switch with stock on hand and its registration-count path
- Language switch impact: keeps unsent input
- Mobile-first: YES — a `[desk]` page; the phone does lookup, detail and barcode registration from a scan (J-MD-04)
- Responsive: YES
- Adaptive: YES — editing on the desktop, lookup and registration on the phone (J-MD-04)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), filters and continuation (PT-07, PT-08), the detail layout (PT-12), forms (PT-09), thumbnails (PT-22), money and quantity presentation (PT-23)
- Design reference: PG-056 as INFORMATION_ARCHITECTURE §8.6 states it, the label as DESIGN_SYSTEM §17's print view; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: creating a product or service
- Secondary actions: "Buat Barcode Internal", "Cetak Label", adding a unit, changing a price default, adding an image, archive
- Destructive actions: none — archive keeps history and states what continues (WF-MD-04)
- Redundancy constraints: one registration command for both entries — the scan's "Daftarkan Barcode" and PG-056's "Buat Barcode Internal" (ROUTE-147)
- Loading state: PT-19; a thumbnail's neutral placeholder until its derivative is ready (PT-22)
- Empty state: a product without images or alternate units says so with its action (PT-19)
- Error state: the SKU and base unit at their fields; a barcode of another product; the serial switch with stock on hand; CONFLICT on a stale `lock_version` or a changed policy (ST-09)

**ROUTE IMPACT:**

- ROUTE-056 (PG-056's view); ROUTE-145 (create), ROUTE-146 (edit: units, conversions, serial policy, price defaults, images, archive), ROUTE-147 (register a barcode); the image's file uploaded first through ROUTE-228 (INT-013); "Cetak Label" client-only (ROUTE_CONTRACTS §4)

**INTERACTION CONTRACTS:**

- INT-145–INT-147; INT-013 for the image upload (TASK-022, TASK-030)

**NAVIGATION CONTRACT:**

- Parent: none — *Menu lengkap* › *Data Master* › *Produk & Jasa* (NAV-056)
- Entry: *Data Master* › *Produk & Jasa*; "Daftarkan Barcode" from a scan on the warehouse pages
- Back action: N/A — a top-level list (NAV-056 names none); the registration from a scan returns to the scan, which continues (ROUTE-147)
- Cancel: "Batal" in a form, MSG-35 first with unsent input (NV-02)
- Success destination: the product's record; after a registration from a scan, the scan continuing
- Error behavior: NV-04 — validation at the field, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — lookup, detail and registration from a scan on the phone (J-MD-04)

**ACCEPTANCE CRITERIA:**

1. A manufacturer or internal code, however typed or scanned — white space removed, letters upper-cased, leading zeros kept — resolves to at most one active product, and a code already active on another product is refused (BR-INV-09; C-01; AC-03).
2. "Buat Barcode Internal" gives the product a code from the database sequence, and "Cetak Label" prints its label from the browser writing nothing (ROUTE-147; ROUTE_CONTRACTS §4).
3. An alternate unit is an exact integer multiple of the base; an entered quantity converts by multiplication only, and a fractional base quantity of a counted or serialized product cannot be stored (C-09; L-31; numeric annex 11).
4. Switching a product to serialized with stock on hand is REJECTED with the registration-count path; switching it off keeps its serial history; an open form meets a changed policy as CONFLICT (L-35; ST-09).
5. A changed price default or unit ratio leaves every saved transaction's and document's values unchanged, and the history lists each default with its kind, amount and date (AC-03; BR-SN-01; REF-015).
6. Purchase defaults appear only with `cost.view`, selling and SPJ-reference defaults only with `finance.view`, and stock figures only with `stock.view`, each absent otherwise (PJ-14; SM-25).
7. An archived product is not offered for new demand or purchases while its confirmed demand stays fulfillable and its stock countable (WF-MD-04).
8. Two concurrent creations with one SKU, or two registrations of one code, give one COMMITTED and one CONFLICT, and one `command_id` replayed returns the recorded outcome (BD-16; CI-05; AC-16).
9. A service cannot be serialized, and a serialized product's base quantities stay integer pieces (DATABASE §4.4; BR-INV-03).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the code normalization; the conversion arithmetic against the numeric annex's examples 10–11 (TL-03; FX-01)
- Feature: ROUTE-056 and ROUTE-145–ROUTE-147 with each refusal (TL-04)
- Integration: concurrent SKU creations and code registrations (BD-16); the serial switch against seeded stock on hand under LK-13 (L-35) (TL-05)
- Authorization: S0 to every active account; `products.edit` for the commands; PJ-14's families on the price defaults (TL-06)
- Route integrity: ROUTE-056 and ROUTE-145–ROUTE-147 audited (TL-07)
- Interaction: INT-145–INT-147, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-MD-04 and CJ-19's catalogue — units, barcodes and the label, serial policy, price defaults, an image (TL-09)
- Localization/language switch: both languages on PG-056 and the label (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01's exact lookup by SKU and barcode, SM-09 for image uploads, SM-25 for the price defaults (TL-14)
- Performance: QB-03 and QB-04 through PFM-01 and PFM-02; PFM-06 for product search; PFM-09 for the uncached thumbnails (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: DEP-03 — the label-print method confirmed at the device UAT without a printer purchase (TEST_STRATEGY §12)

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Unit/CAT/CatalogArithmeticTest.php` — a code's key with white space removed, letters upper-cased and leading zeros kept; `tests/Feature/CAT/ProductsTest.php` — a code typed with spaces registered by its key, the same code refused at its field on another product and again on its own (BR-INV-09; C-01)
- Criterion 2: `tests/Feature/CAT/ProductsTest.php` — "Buat Barcode Internal" twice gives two distinct `MC` codes from the sequence; `tests/Frontend/code39.test.ts` — the label's Code 39 symbol; `tests/Browser/E2E/products.spec.ts` — "Cetak Label" calls the browser's print of the label view, nothing sent
- Criterion 3: `tests/Unit/CAT/CatalogArithmeticTest.php` — conversion by multiplication only against the numeric annex's examples 10–11, a base quantity storable only at its scale; `tests/Feature/CAT/ProductsTest.php` — an alternate unit an integer multiple of at least 2, a fraction refused, its ratio corrected at the version read
- Criterion 4: `tests/Integration/Catalog/CatalogRacesTest.php` — under real sessions the serial switch waits on a receipt holding the product's guard (LK-13), reads ON HAND under it and is REJECTED under L-35 with the registration-count path; without stock it commits, and switching it off keeps the serial history; a stale form CONFLICT (ST-01; ST-09)
- Criterion 5: `tests/Feature/CAT/ProductsTest.php` — price defaults forward only, the history listed by kind, amount and date; a saved purchase line's price and base quantity unchanged by a price or ratio change (BR-SN-01; numeric annex 11)
- Criterion 6: `tests/Authorization/ProductsAccessTest.php` — purchase defaults with `cost.view`, selling and SPJ-reference defaults with `finance.view`, stock figures with `stock.view`, each absent otherwise; a price change also needs its kind's family, re-read in the command (PJ-14; SM-25)
- Criterion 7: `tests/Feature/CAT/ProductsTest.php` — an archived product kept, no longer offered by CAT's lookup and taking no new barcode (WF-MD-04)
- Criterion 8: `tests/Integration/Catalog/CatalogRacesTest.php` — two creations of one SKU and two registrations of one code give one COMMITTED and one CONFLICT; `tests/Interaction/ProductsTest.php` — each `command_id` replayed answering its recorded outcome (BD-16; CI-05)
- Criterion 9: `tests/Feature/CAT/ProductsTest.php` — a service created at scale 0 and refused serialization (BR-INV-11); the serialized flag only at scale 0 (BR-INV-03; DATABASE §4.4)

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS in CI (TL-02)
- [x] Relevant automated tests: Unit — the code key and the conversion arithmetic (TL-03); Feature — ROUTE-056 and ROUTE-145–ROUTE-147 with each refusal (TL-04); Integration — concurrent SKU creations and code registrations, the serial switch against stock under LK-13 (TL-05); PASS in CI
- [x] Authorization: S0 to every active account, `products.edit` for the commands, PJ-14's families on the price defaults (TL-06)
- [x] Route integrity: ROUTE-056 and ROUTE-145–ROUTE-147 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-145–INT-147, each with one `command_id` replayed (TL-08)
- [x] Browser E2E if required: J-MD-04 and CJ-19's catalogue — units, barcodes and the label, serial policy, a price default, an image — on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-056 at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll (TL-11)
- [x] Visual breathing room/density if UI: TASK-015's lookup, sheets, data lists, confirmations, upload field and form fields, nothing hand-styled
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-01's exact lookup by SKU and barcode, SM-09 for the image uploads through ROUTE-228, SM-25 for the price defaults (TL-14)
- [x] Database safety review: no migration; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources; Codex's two P1 and one P2 fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `99bfacc`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40590487018 (push) and 40590493240 (pull request #33), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". The workstation's Docker engine answered HTTP 500 throughout the Task; nothing was restarted, and CI ran every suite
- Build result: `TL-13 Frontend build: PASS — Evidence: 99bfacc, CI ubuntu-24.04 run 40590487018`
- Route evidence: TL-07's route audit PASS — ROUTE-056 `GET /data-master/produk` (every active account, S0), ROUTE-145 `POST /data-master/produk`, ROUTE-146 `PUT /data-master/produk/{id}` and ROUTE-147 `POST /data-master/produk/{id}/barcode` (`products.edit`); "Cetak Label" client-only
- Interaction evidence: `tests/Interaction/ProductsTest.php` (INT-145–INT-147)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 99bfacc, CI ubuntu-24.04 run 40590487018`; TL-10, TL-11 and TL-12 for PG-056 in the same run
- Localization evidence: PG-056 in both languages (`tests/Browser/Localization/products.spec.ts`) (DOD-16); 96 catalogue keys added, proposed copy
- Performance evidence: PFM-01, PFM-02 and PFM-20 (`tests/Performance/ProductsQueriesTest.php`) (TL-15)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/CAT/` — `Actions/` (CreateProduct, UpdateProduct, AddAlternateUnit with the ratio's correction, ChangeDefaultPrice, AttachProductImage with the image's arrangement, RegisterBarcode), `Data/` (CodeKey, Quantity), `Services/CatalogCommands`, `Queries/ProductCatalog`, `Http/Controllers/ProductsController` —; INV's `Queries/ProductStock`; `GuardUpdate::bear`; the routes; PG-056 with the navigation, the Code 39 label and its print style; the explicit "Muat Versi Terbaru" after a stale answer, also on PG-054's and PG-055's sheets; each browser layer's own artifact folder; 96 catalogue keys; the tests and browser specs; TOOLCHAIN's row; this record
- Commit SHA: `b722ea4`, `9b3d20c`, `095dd8a`, `7704750`, `a3ce621`, `55119ca` and `99bfacc`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-027-catalog`
- Notes: criteria as CRITERIA DISPOSITIONS states. Codex reviewed `095dd8a`: its two P1 — no correction of an alternate unit's ratio, no change of the primary image or the order — and its P2 — a stale answer silently advancing the form's version — fixed and tested in `7704750`, the P2's pattern also removed from PG-054's and PG-055's sheets; no review of the later commits arrived within ten minutes of their push — CODEX_TIMEOUT recorded for them, never a pass (DIR-096). A WebKit run of `parties.spec.ts`'s supplier creation failed once on the push run of `55119ca` while its pull request run passed; its evidence had been overwritten by the next browser layer, so each layer now keeps its own artifacts and the spec asserts the creation sheet closes — both runs of `99bfacc` passed; the cause is not yet identified and is watched. Readings within the approved text: the product's guard row is born with it through `GuardUpdate::bear`, CAT writing no INV model; units are the dictionary's by code, a unit the form names created in the same command when none has it — no unit command is contracted; an internal code prints as `MC` and eight digits; the label is Code 39, which keyboard-wedge scanners read without configuration (DEP-03, the device UAT confirms the print method); a scale still referenced is REJECTED under C-09 through a savepoint, not left to the generic foreign-key failure. Not applicable: Migration tests (TL-16) — no migration

**SAFE NEXT TASK:**

- TASK-032 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- auto-create or auto-select a product from an unknown or ambiguous code (BR-INV-09; SF-SCAN)
- store a rounded alternate-unit quantity, a non-integer ratio, or a fractional quantity of a counted product (L-31; C-09)
- backfill a changed price default or ratio into any committed transaction or document (FS-06)
