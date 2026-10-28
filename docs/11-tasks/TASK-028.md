# TASK-028 — Rack locations and minimum stock

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build PG-057 *Lokasi Rak*: the rack and location lookups of the shared warehouse and each product's minimum stock, created and edited by one command, read by every holder of `stock.view` (ROUTE-057, ROUTE-148; WF-MD-05; J-MD-05).

**WHY THIS EXISTS:**

Receiving, counts and moves record a location for every unit (WF-MD-05; L-13; L-14), and the per-product minimum feeds the restock advice QS-17, which only advises and never creates a purchase (BR-INV-10); locations are pool records of no company (CS-05). Reservations and the opening import (TASK-038, TASK-057) need the locations to exist; no WMS is built (REF-023).

**DEPENDS ON:**

- TASK-008 — Schema — INV tables, inventory and lot-cost representations
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-038 — Reservations
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — its locations are master rows at LK-08 that every warehouse Task reads and the opening import seeds into

**IN SCOPE:**

- PG-057 with NAV-057 and its view ROUTE-057 (`stock.view`; SCR-53): the racks and locations — code, name, active state — and each product's minimum stock, a pool page of no company
- ROUTE-148 with INT-148 (`warehouse.manage`): create or edit a rack — its code unique, a duplicate answered at the field — and set a product's minimum stock in base units (`min_stock_qty_base`) through CAT's write service (ARCHITECTURE §3)
- the location lookup that receiving, counts and moves read, and the minimum that QS-17's restock advice reads — advice only, its row linking to PG-017 with the product prefilled, never creating a purchase (BR-INV-10; J-MD-05)

**OUT OF SCOPE:**

- the zero-net location move "Pindah Lokasi" (ROUTE-190; L-36; TASK-042); counts (TASK-043)
- QS-17's signal and its row on the home pages (TASK-058); the purchase it links to (TASK-037)

**REFERENCES:**

- [WORKFLOWS §5.1](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-md-05--locations-and-minimum-stock-desk) WF-MD-05; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-17
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-10
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `locations`; [§4.4](../04-architecture/DATABASE/s04-04-cat.md#44-cat--catalog) `products.min_stock_qty_base`
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `warehouse.manage`; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 22 and 47
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) ROUTE-148; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-057
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#master-data--klien-pemasok-produk--jasa-lokasi-rak-pg-054pg-057-j-md-02j-md-05) INT-148
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-057; [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-057
- [ADMIN_FLOW J-MD-05](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data)
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-16
- [PERFORMANCE §7](../06-api-performance/PERFORMANCE.md#7-dashboards-reports-and-exports) QS-17's statement
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (18): O-02; CAP-06; AC-22; REF-023; REF-028; IMG-03–IMG-04; OWN-04–OWN-05; WF-MD-05; ROUTE-057; ROUTE-148; INT-148; NX-01; PG-057; SCR-53; J-MD-05; NAV-057

**EXPECTED MODULES / FILE AREAS:**

- the INV module's `SaveLocation` and `SetMinimumStock` actions with their policy and query class (RC-02); `resources/js/Pages/INV` for PG-057; `tests/Feature/INV`

**BUSINESS RULES:**

- BR-INV-10: the minimum is advisory over reconciled usable availability and never creates a purchase; locations are lookups with no WMS (WF-MD-05)

**ARCHITECTURE RULES:**

- INV is tier 5 and writes the product's minimum only through CAT's write service, never CAT's tables directly (ARCHITECTURE §3); a location is MD with a unique code (DATABASE §4.7)

**SECURITY / PERMISSION REQUIREMENTS:**

- the view `stock.view`; ROUTE-148 `warehouse.manage`, whose prerequisite is `stock.view` (PERMISSIONS_MATRIX §4); a pool command still needs at least one company in scope (CS-05)

**COMPANY SCOPE AND FIELD PROJECTION:**

- POOL records of no company (CS-05): `locations` S1 to SV (row 47) and `min_stock_qty_base` SV (row 22); a rack carries no company, so no company label applies to it; an account without `stock.view` is denied the page (AZ-11)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-148 is NX-01 with `command_id` (CI-01): the location at LK-08 in the fixed table order — parties, catalogue, locations — and, for a minimum, the product's catalogue row there, each changed by the conditional `lock_version` update (ST-01); a duplicate code lost in a race is CONFLICT (BD-16; SQ-08); replay re-authorized (CI-05)

**AUDIT:**

- every location change and minimum change is a business audit event in its transaction with before/after and the individual actor (BR-XC-01; LG-01); a pool event carries no company (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5, calling CAT's write service for the minimum (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no WMS, slotting or replenishment engine (WF-MD-05; BR-INV-10)

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

- Context7: OPTIONAL — the framework's unique validation and optimistic update at the installed version
- Graphify: OPTIONAL — once the warehouse Tasks exist
- Browser E2E: REQUIRED — J-MD-05's maintenance part and CJ-19's locations and minimum stock
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the INV and CAT modules
- Pre-change questions: which commands read a location, and which signal reads the minimum (QS-17)
- Expected affected areas: INV's location master; CAT's product row for the minimum

**CONTEXT7:**

- Topics to verify: unique validation against a normalized code; the conditional `lock_version` update
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the minimum explained as advice that never buys anything (BR-INV-10)
- Language switch impact: keeps unsent input
- Mobile-first: YES — a `[desk]` page readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one list layout, readable on the phone; the move between racks is TASK-042's phone flow
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), filters and continuation (PT-07, PT-08), a dialog form of at most five fields (PT-16), quantity presentation (PT-23)
- Design reference: PG-057 as INFORMATION_ARCHITECTURE §8.6 states it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: creating a rack
- Secondary actions: editing a rack; setting a product's minimum
- Destructive actions: none — a rack is deactivated, never deleted
- Redundancy constraints: one command for both, ROUTE-148
- Loading state: PT-19
- Empty state: no rack yet — one sentence with the create action (PT-19)
- Error state: a duplicate code at the field; CONFLICT on a stale `lock_version`

**ROUTE IMPACT:**

- ROUTE-057 (PG-057's view); ROUTE-148 (save a rack, set a product's minimum)

**INTERACTION CONTRACTS:**

- INT-148

**NAVIGATION CONTRACT:**

- Parent: none — *Menu lengkap* › *Data Master* › *Lokasi Rak* (NAV-057)
- Entry: *Data Master* › *Lokasi Rak*
- Back action: N/A — a top-level list (NAV-057 names none)
- Cancel: "Batal" in a form, MSG-35 first with unsent input (NV-02)
- Success destination: the rack's row (NAV-057)
- Error behavior: NV-04 — validation at the field, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. Only holders of `stock.view` see PG-057, and only holders of `warehouse.manage` create or edit a rack or a minimum; an account without them finds neither page nor control (AZ-01; IG-01).
2. A rack's code is unique: a duplicate is answered at the field, and two concurrent creations of one code give one COMMITTED and one CONFLICT (BD-16).
3. A product's minimum is stored in base units on the product and a set minimum creates no purchase, reservation or movement (BR-INV-10).
4. A stale edit answers CONFLICT, and one `command_id` replayed returns the recorded outcome (ST-01; CI-05).
5. A rack carries no company: it appears to every `stock.view` holder, whatever their company grants (CS-05).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A — no calculation in this Task
- Feature: ROUTE-057 and ROUTE-148 with their refusals (TL-04)
- Integration: two concurrent creations of one code (BD-16) (TL-05)
- Authorization: `stock.view` for the view and `warehouse.manage` for the command, both present and absent (TL-06)
- Route integrity: ROUTE-057 and ROUTE-148 audited (TL-07)
- Interaction: INT-148 with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-MD-05's maintenance and CJ-19's locations and minimum stock (TL-09)
- Localization/language switch: both languages on PG-057 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-04's `warehouse.manage` positive and denial cases (TL-14)
- Performance: PFM-01 and PFM-02 for PG-057 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: N/A — nothing beyond the layers above

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Authorization/LocationsAccessTest.php` — PG-057 denied without `stock.view` and shown with it; the command refused without `warehouse.manage` or a company in scope, writing nothing; the control's hint present only with it; `tests/Interaction/LocationsTest.php`
- Criterion 2: `tests/Feature/INV/LocationsTest.php` — a rack's code upper-cased and unique, a duplicate answered at its field; `tests/Integration/Inventory/LocationRaceTest.php` — two concurrent creations of one code give one COMMITTED and one CONFLICT under real sessions (BD-16)
- Criterion 3: `tests/Feature/INV/LocationsTest.php` — a minimum in base units at the product's scale through CAT's `ProductMinimum`, a fraction of a counted product refused at its field, cleared with an empty value; no purchase, reservation or movement created (BR-INV-10)
- Criterion 4: `tests/Feature/INV/LocationsTest.php` — a stale rack or minimum edit CONFLICT, a minimum of a product chosen without its version set only while it has none; `tests/Interaction/LocationsTest.php` — each `command_id` replayed answering its recorded outcome (ST-01; CI-05)
- Criterion 5: `tests/Feature/INV/LocationsTest.php` — racks and minimums to a `stock.view` holder of another company, no company on the rack or its audit (CS-05)

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS in CI (TL-02)
- [x] Relevant automated tests: Feature — ROUTE-057 and ROUTE-148 with their refusals, both lists' continuation and the chosen product's loading (TL-04); Integration — two concurrent creations of one code (TL-05); PASS in CI and locally
- [x] Authorization: `stock.view` for the view and `warehouse.manage` for the command, present and absent (TL-06)
- [x] Route integrity: ROUTE-057 and ROUTE-148 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-148 with one `command_id` replayed (TL-08)
- [x] Browser E2E if required: J-MD-05's maintenance and CJ-19's racks and minimum stock, on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-057 at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll (TL-11)
- [x] Visual breathing room/density if UI: TASK-015's lists, sheets, lookup and form fields, nothing hand-styled
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-04's `warehouse.manage` positive and denial cases (TL-14)
- [x] Database safety review: no migration; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources; Codex's P1 and P2 fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `1a37e5b`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40615596119 (push) and 40615599681 (pull request #34), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration"; the PHP suites and PHPStan also PASS on the workstation's restored stack, whose browser layer timed out at sign-in while it answered each request in four to seven seconds — CI ran the browser layers
- Build result: `TL-13 Frontend build: PASS — Evidence: 1a37e5b, CI ubuntu-24.04 run 40615596119`
- Route evidence: TL-07's route audit PASS — ROUTE-057 `GET /data-master/lokasi-rak` (`stock.view`) and ROUTE-148 `POST /data-master/lokasi-rak` (`warehouse.manage`)
- Interaction evidence: `tests/Interaction/LocationsTest.php` (INT-148)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 1a37e5b, CI ubuntu-24.04 run 40615596119`; TL-10, TL-11 and TL-12 for PG-057 in the same run
- Localization evidence: PG-057 in both languages (`tests/Browser/Localization/locations.spec.ts`) (DOD-16); 27 catalogue keys added, proposed copy
- Performance evidence: PFM-01 and PFM-02 (`tests/Performance/LocationsQueriesTest.php`) (TL-15)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/INV/` — `Actions/SaveLocation`, `Queries/LocationDirectory` (both lists after their cursors, the chosen product, the active racks' lookup), `Http/Controllers/LocationsController` —; CAT's write service `Services/ProductMinimum`; `BackgroundRequest::READS_SESSION_ONLY` and ROUTE-281 reading the session without writing it back; the routes; PG-057 with the navigation and `components/form-sheet.tsx` shared with PG-056; 27 catalogue keys; the tests and browser specs; this record
- Commit SHA: `54dceaf`, `9f6e17b`, `fa85636` and `1a37e5b`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-028-locations`
- Notes: criteria as CRITERIA DISPOSITIONS states. Codex reviewed `9f6e17b`: its P1 — a sheet's reload taking a refreshed version with stale values — and its P2 — both lists capped with no way to their tail — fixed and tested in `fa85636`; no review of the later commits arrived within ten minutes of their push — CODEX_TIMEOUT recorded for them, never a pass (DIR-096). The browser run of `fa85636` showed a sheet holding MSG-07 again: ROUTE-281's type-ahead, running beside the command, wrote back the session it had loaded before the answer was stored, erasing it — TASK-027's flash keeping covered only a search after the command; the search route now reads the session and never writes it (`1a37e5b`), which closes it for every page with a lookup. Readings within the approved text: a rack is named by its code, unique, upper-cased and fixed once made, its name and active state editable; a minimum of a product chosen by the lookup is loaded with its current value and version by the address key `produk`, and one sent without a version is set only while the product has none. Not applicable: Unit tests (TL-03) — no calculation; Migration tests (TL-16) — no migration

**SAFE NEXT TASK:**

- TASK-038 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let the minimum create a purchase, a reservation or any movement, or add a WMS, slotting or automatic replenishment (BR-INV-10)
- give a rack a company, or delete a rack that a movement has used (CS-05; FS-02)
