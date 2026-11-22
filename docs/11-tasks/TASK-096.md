# TASK-096 — Defects A–D — form input kept across an evidence upload; quantities never summed across products or units; the primary action on the phone; a non-matching invoice lookup told from an empty list (NV-04; IP-16; PT-03; IP-17)

Status: APPROVED | Updated: 2026-11-22 | Owner: Planning

Approval: the one consolidated defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 30, which the Owner approved explicitly ([APPR-041](../00-governance/DECISION_LOG.md#dir-132-and-appr-041--change-30-one-consolidated-defect-task-2026-11-22); DIR-132) from the four defects of [GAP-073](../00-governance/GAP_REGISTER.md#gap-073--four-verified-code-defects-found-by-the-ux-first-v2-review), found by the UX-first V2 review ([DIR-131](../00-governance/DECISION_LOG.md#dir-131--the-owners-review-of-the-ux-first-v2-blueprint-2026-11-22)), within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan, under DIR-111's release freeze and DIR-114's closure — the plan raised from 95 to 96 Tasks by this Task alone.

**STATUS:** VERIFYING

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

A: no typed input is lost when a form's user registers evidence — the `ProofSheet` hosts return in place, the plain links to PG-038 become the in-place sheet, and leaving a form with unsent input asks MSG-35 —, the evidence pool's authorization, its refusal and its redirect safety unchanged. B: no figure sums quantities of different products or units and no stock line merges distinct products by name, inventory accounting, postings and stored balances unchanged. C: PG-010's "Buat Proyek", PG-011's next step and PG-045's "Catat Pembayaran" operable on a phone as their journeys state. D: PG-041 tells a non-matching number lookup from an empty list, naming the term with MSG-25 and offering the reset (IP-17; PT-19), its search semantics unchanged.

**WHY THIS EXISTS:**

The UX-first V2 review (DIR-131) found, and a read-only verification on `main` 5a44a81 with an independent refuter per defect confirmed, four defects of DONE Tasks ([GAP-073](../00-governance/GAP_REGISTER.md#gap-073--four-verified-code-defects-found-by-the-ux-first-v2-review)). **A, release blocker (P1)** — `EvidenceController.php:207-217` returns in place only for seven routes, so PG-016, PG-017, PG-026, PG-029 and PG-049, which host `ProofSheet`, land on PG-038 with the form gone (`DispatchPagesTest.php:342` asserts that redirect for other addresses); PG-031, PG-032, PG-034 and PG-048 link to `/dokumen/bukti` with a plain `<a>` and no guard while their commands require evidence (`DeliveryController.php:75`, `DirectShipmentController.php:60`, `ClientReturnController.php:57`, `CustomerCreditController.php:48`); PG-033's guard is the browser's prompt, not MSG-35, and misses fields; the return list's query charset refuses `.` (PG-048's cursors, PG-027's `temukan=`). Only unsent input is lost — the forms send nothing before their own command, and the evidence registered before the redirect persists. **B (P2 by impact)** — `ReceivingQueries.php:63` (PG-021) and `PurchaseProject.php:28` (PG-011's procurement part) sum remainders across lines, products and base units and show them without a unit; `CostFigures.php:127` groups PG-018's exposure lines by product name, which is not unique. **C, release blocker (P1)** — `page-header.tsx:82` hides `primary` below 768 px with no phone path; PG-010, PG-011 and PG-045, all `[both]`, lose it (J-PRJ-01; J-FIN-03). **D (P2 by impact)** — `Invoices.tsx:89-90` answers a non-matching number with "Belum ada invoice di cabang ini.", locked by `invoices.spec.ts:118-120`.

**DEPENDS ON:**

- TASK-015 — Design system — tokens, components, patterns and states
- TASK-016 — Application shell, navigation and the company context
- TASK-030 — Evidence and uploaded documents
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-037 — Purchases — project and replenishment purchases
- TASK-039 — Receiving (Barang Masuk)
- TASK-040 — Stock views, stock history and movement reversal
- TASK-042 — Condition, location moves, adjustments and losses
- TASK-043 — Stock count (opname) and discrepancies
- TASK-045 — Deliveries (Pengiriman) and delivery closure
- TASK-046 — Direct shipment (drop-ship)
- TASK-047 — Service fulfillment and handover
- TASK-048 — Client returns (sales return)
- TASK-049 — Invoices and the pre-payment Kuitansi
- TASK-051 — Payments, applications and bank statement lines
- TASK-053 — Customer credit and refunds
- TASK-054 — Expenses, disbursements and other cash

**BLOCKS:**

- TASK-086 — UAT with the Owner and the validators

**PARALLEL SAFE:** YES — with the operator's TASK-078 alone: disjoint files apart from TASK_PLAN and CURRENT_HANDOFF, whose concurrent changes are kept by merging `main` into the branch before the merge; with no other AGENT Task

**IN SCOPE:**

- A: `EvidenceController`'s return list written as anchored literal paths with exact UUIDs — the `ProofSheet` hosts PG-016, PG-017, PG-026, PG-029 and PG-049 and the forms PG-031, PG-032, PG-033, PG-034 and PG-048 added to the seven already there, PG-027 among them, its query with a `.` admitted —, no other host, scheme, `//`, `\`, `:`, `#`, control character or array accepted, its length bound covering the routes' encrypted cursors, the query re-serialized on the server; PG-017's return carrying its chosen company
- A: PG-031, PG-032 (supplier and client targets), PG-033, PG-034 and PG-048 (over its refund dialog) opening `ProofSheet` in place of the link to PG-038; the evidence registered selected in the form; the upload offered only where the actor may register that type for the record's company (`EvidenceTypes::choosable`)
- A: MSG-35 guarding PG-031, PG-032, PG-033 and PG-034 with a dirty state covering every field, cleared after a committed outcome; their "Batal" through the router
- B: PG-021's row showing the open lines it already counts — "N baris belum diterima", plural through `tc()` —, no unitless sum; PG-011's procurement part showing remainders per line or product with their unit (`Quantity`), nothing for a closed or cancelled purchase; PG-018's exposures grouped by product identity, keyed by `public_id`, the SKU beside a repeated name
- C: an `ActionBar md:hidden` holding PG-010's, PG-011's and PG-045's primary action, placed and named as PG-014's is — after the page's content, its spacer keeping the page's end reachable above it —, absent while PG-010's empty state shows the same action (C1A-2); nothing else of the shell changed
- D: PG-041's states told apart — no company in scope, no invoice, a number that matches none —, the last naming the sent term with MSG-25 (IP-17) and offering "Atur Ulang" (PT-07; PT-19), announced by a live region present from the page's load (FA-22), focus back in the field after the reset; the field bounded to 100 characters (IP-17); the "Muat Lagi then search" race closed
- the tests below; the new copy key of B in both languages, proposed for the Owner (OB-04) and registered with TASK-096

**OUT OF SCOPE:**

- the UX-first redesign (DIR-131) — tokens, palette, typography, sidebar, navigation, routes; GF-01–GF-14; the blueprint's foundation items D1–D3
- any normative document change; IA's hosts column naming the in-place upload on the pages that host it (a Level 1 documentation follow-up for Planning, GAP-073)
- the follow-ups GAP-073 lists: PG-022's per-line "Sisa diterima" unit, PG-043's correction proof, the no-match state of PG-053, PG-018, PG-015, PG-025, PG-026 and PG-010, the Finance pages' "cabang" wording, rowActions on the phone
- inventory postings, balances, CALC rules, evidence storage and every capability decision

**REFERENCES:**

- IP-16 and IP-17 in [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md); IP-11 in [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s05-command-model.md); NV-02, NV-04 and NAV-031–NAV-034 in [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md); MSG-25, MSG-35 in [LOCALIZATION](../07-ux-design/LOCALIZATION.md)
- CALC-03; PJ-18; DESIGN_SYSTEM §16 and PT-06; [TASK-037](TASK-037.md)'s remaining quantities
- PT-03, §11, NAV-011, C1A-2; J-PRJ-01 and J-FIN-03 in [ADMIN_FLOW §10](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md)
- PT-19, PT-07, FA-22; DP-06
- [GAP-073](../00-governance/GAP_REGISTER.md#gap-073--four-verified-code-defects-found-by-the-ux-first-v2-review); [APPR-041](../00-governance/DECISION_LOG.md#dir-132-and-appr-041--change-30-one-consolidated-defect-task-2026-11-22)

**EXPECTED MODULES / FILE AREAS:**

- `app/Modules/DOC/Http/Controllers/EvidenceController.php`; the FUL and FIN queries giving a record's company and the types its actor may register
- `resources/js/Pages/FUL/RecordDelivery.tsx`, `DirectShipment.tsx`, `ClientReturn.tsx`, `ServiceHandover.tsx`; `resources/js/Pages/FIN/CustomerCredit.tsx`; `resources/js/Pages/PUR/RecordPurchase.tsx`
- `app/Modules/INV/Queries/ReceivingQueries.php`, `resources/js/Pages/INV/ReceivingWorklist.tsx`; `app/Modules/PUR/Queries/PurchaseProject.php`, `resources/js/Pages/PRJ/Journey.tsx`; `app/Modules/CST/Queries/CostFigures.php`, `resources/js/Pages/INV/Stock.tsx`
- `resources/js/Pages/PRJ/Projects.tsx`; `resources/js/Pages/FIN/Payments.tsx`; `resources/js/Pages/FIN/Invoices.tsx`
- `lang/id.json`, `lang/en.json`, `lang/registry/added-keys.json`; the tests below

**BUSINESS RULES:**

- unchanged — no command, payload, posting, balance, capability or search semantics changes

**ARCHITECTURE RULES:**

- the existing `ProofSheet`, `useCommand`, unsent-input dialog, `ActionBar`, `RegionNoMatch`, `Quantity` and `tc()`; no new component family, package or abstraction

**SECURITY / PERMISSION REQUIREMENTS:**

- the return list admits only the listed routes — no open redirect —; a refused registration stays in its form with nothing registered; the evidence pool's capability checks unchanged; the upload offered only where the actor may register that type for the record's company
- PG-041's echoed term rendered as text only; a number of another company and a fictitious number answered alike (DP-06)
- no primary action shown to an actor without its capability; every server admission unchanged

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged — the projections without prices stay without prices (PJ-18)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command changes; a retried evidence registration keeps its command identity

**AUDIT:**

- N/A — no audit event changes

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES
- Post-Task deactivation/cleanup: none

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — every refusal and admission as before
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE — read queries and presentation only

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: DOC's evidence controller; the FUL, FIN, INV, PUR and CST read queries; the named pages
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — the tests below on Chromium and WebKit, in CI (DIR-105); locally one Playwright worker and only MULTIPLECORP's services
- Other: none — Modern Web Guidance stays outside the repository
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: every host of `ProofSheet` and every link to `/dokumen/bukti`; every read that sums `qty_base` across lines; every caller of PageHeader's `primary`; every list answering a lookup with its empty sentence
- Expected affected areas: the pages named in IN SCOPE; the evidence controller's return

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys and B's proposed key
- English support: YES — both languages
- Plain-language Indonesian: YES
- Language switch impact: NONE
- Mobile-first: YES — C's action bar above the bottom bar and the safe area, ≥44 px; A's sheet usable at 320 and 390 px
- Responsive: YES — the existing compositions
- Adaptive: YES — the existing compositions
- Visual breathing room: YES — the existing tokens
- Comfortable density: YES — unchanged
- shadcn/ui reuse: YES — the existing components only
- Design reference: DESIGN_SYSTEM's own rows named in REFERENCES
- Primary action: one per action area (C1A-2)
- Secondary actions: unchanged
- Destructive actions: unchanged
- Redundancy constraints: no duplicate primary action on PG-010
- Loading state: unchanged
- Empty state: D's three states (PT-19)
- Error state: a refused evidence registration answered inside the sheet, the form kept

**ROUTE IMPACT:**

- none — no route added or changed

**INTERACTION CONTRACTS:**

- INT-013 and INT-229 as they stand; no row added or changed

**NAVIGATION CONTRACT:**

- Parent: unchanged
- Entry: unchanged
- Back action: unchanged
- Cancel: through the router, asking MSG-35 when input is unsent
- Success destination: unchanged
- Error behavior: a refused evidence registration stays in its form
- State preservation: the form's input kept across an evidence registration (NV-04)
- Mobile behavior: C's primary action in the action bar

**ACCEPTANCE CRITERIA:**

1. A: on PG-016, PG-017, PG-026, PG-027, PG-029, PG-031, PG-032, PG-033, PG-034, PG-048 and PG-049, registering evidence from the form keeps the address and every typed value, selects the new evidence and lets the form's own command succeed; a refused registration stays in the form with nothing registered; leaving with unsent input asks MSG-35, and nothing asks after a committed outcome — on Chromium and WebKit.
2. A: the return list admits exactly the listed routes with their real cursors and refuses every other value — another host, `//`, `\`, `:`, `#`, a control character, an array, an over-long value —, those answering PG-038; the evidence pool's capability tests unchanged and green.
3. B: no response or screen of PG-021, PG-011 or PG-018 contains a sum across products or units; PG-021 shows "N baris belum diterima" (the English singular for one); PG-011 shows remainders with their unit and nothing for a closed or cancelled purchase; PG-018 shows two products of one name as two lines; the query budgets unchanged; stored balances and postings untouched.
4. C: at 390×844 with a coarse pointer, PG-010 (with at least one project), PG-011 (with a next step) and PG-045 show exactly one primary action, at least 44 px, visible without scrolling, clear of the bottom bar, opening the address the desktop opens; none for an actor without the capability; no duplicate with PG-010's empty state; the 1280 px pages unchanged.
5. D: a number that matches none shows MSG-25 naming the sent term with "Atur Ulang", announced, focus back in the field after the reset; an empty company and an account without a company in scope keep their own states; 101 characters refused by the field; DP-06 holds.
6. No existing assertion weakened; the tests that encoded a defect rewritten to the contract — `invoices.spec.ts`'s lookup test; `journey-cj02.spec.ts` and `purchases.spec.ts` tightened to full text with units —; fresh CI PASS on the final head.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A
- Feature: the return list — every host in place, the hostile values refused, the cursor lengths —, a refusal kept in its form, the props' company and choosable types; PG-021 and PG-011 with two products of different units and with one product; PG-018's exposures of two products of one name; PG-041's DP-06 equality
- Integration: N/A — no write path changes
- Authorization: the existing evidence, projects, payments and receiving access suites unchanged and green (TL-07)
- Route integrity: N/A — no route added or changed
- Interaction: the tests above
- Browser E2E: TL-09 — A's in-place upload on PG-031 on a phone, PG-032's two targets, PG-017 and PG-029 kept in place, PG-048's dialog kept open, MSG-35 shown by the application and not after success; C's three pages at 390 with a coarse pointer, 767/768 and 1280, the capability negative and PG-010's empty list; D's no-match, reset, empty company, out of scope, 101 characters and the race — on Chromium and WebKit
- Localization/language switch: the key catalogue and copy lists green; B's key proposed, registered with TASK-096
- Visual breathing room/density: N/A — no visual change beyond the action bar
- Responsive: C's bar at 320, 390 and 767; A's sheet at 390
- Accessibility: the existing gate on the touched pages; D's live region; C's bar a region named for its page, as PG-014's
- Production build: TL-13
- Security: no open redirect; no capability change; the echoed term as text
- Performance: the worklist's and the procurement part's query budgets unchanged
- Migration: N/A
- Other: none

**TEST CHECKLIST:**

- [ ] Lint: Pint and ESLint in this head's CI (TL-01)
- [ ] Typecheck: PHPStan and TypeScript in this head's CI (TL-02)
- [ ] Relevant automated tests: `tests/Feature/DOC/EvidenceInPlaceTest.php` — every host in place with the routes' real cursors, a refusal and a field's error kept in the form, twenty-four hostile return values answering PG-038, the bound at 2,048 —; the in-place upload's company and types on PG-031–PG-034 and PG-048 (`DeliveryPagesTest`, `DirectShipmentPagesTest`, `HandoverTest`, `ClientReturnPagesTest`, `CustomerCreditPagesTest`); PG-021's open lines and PG-011's remainders at their units with two products (`ReceivingTest`, `PurchasesTest`); PG-018's two products of one name (`StockViewsTest`); PG-041's DP-06 equality (`InvoicesTest`); the Browser tests below — in this head's CI
- [ ] Authorization: the route audit (TL-07) and every denial suite unchanged and green; the evidence pool's capability tests unchanged
- [ ] Route integrity: N/A — no route added or changed
- [ ] Interaction behavior: the Feature and Browser tests above
- [ ] Browser E2E if required: `tests/Browser/E2E/evidence-in-place.spec.ts` (PG-031 on the phone with MSG-35, PG-032's two targets, PG-048's dialog, PG-017, PG-029), `tests/Browser/E2E/invoices.spec.ts` (no match, "Atur Ulang", an empty company, 101 characters, the race), `tests/Browser/E2E/journey-cj02.spec.ts` tightened to the unit — on Chromium and WebKit (TL-09); an account with no company in scope is `InvoicesTest`'s, no browser fixture holding `finance.view` without a company
- [ ] Responsive/adaptive if required: `tests/Browser/Responsive/primary-action.spec.ts` — PG-010, PG-011 and PG-045 at 390 px with a coarse pointer and at 320, 767, 768 and 1280 px, the capability negative and PG-010's empty list; A's sheet at 390 px in `evidence-in-place.spec.ts`
- [ ] Visual breathing room/density if UI: N/A — no visual change beyond the action bar, PG-014's
- [ ] Production build: TL-13 in this head's CI
- [ ] Security checks if required: the return list's refusals (`EvidenceInPlaceTest`); capability decisions unchanged — the upload now offered only where `EvidenceTypes::choosable` allows it —; the echoed term rendered as text
- [ ] Database safety review: no schema change, no migration, no write path; production not touched
- [ ] Final diff review: ER-06's independent review and Codex's review within DIR-096's bounded loop, their findings and dispositions in the pull request

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's runner, every job required to PASS before the merge, the head containing `main`'s tip (DIR-118)
- Build result: TL-13 in the same run
- Route evidence: N/A — no route added or changed
- Interaction evidence: the Feature tests above
- Browser evidence: the Browser tests above on Chromium and WebKit, as the pull request's checks show before the merge
- Localization evidence: B's key in both languages, registered and proposed
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/DOC/Http/Controllers/EvidenceController.php`; `app/Modules/FUL/Queries/Deliveries.php`, `DirectShipments.php`, `ServiceHandovers.php`, `ClientReturns.php`; `app/Modules/FIN/Queries/CustomerCredit.php`; `app/Modules/INV/Queries/ReceivingQueries.php`; `app/Modules/PUR/Queries/PurchaseProject.php`; `app/Modules/CST/Queries/CostFigures.php`; `resources/js/Pages/FUL/RecordDelivery.tsx`, `DirectShipment.tsx`, `ServiceHandover.tsx`, `ClientReturn.tsx`; `resources/js/Pages/FIN/CustomerCredit.tsx`, `Invoices.tsx`, `Payments.tsx`; `resources/js/Pages/PUR/RecordPurchase.tsx`; `resources/js/Pages/INV/ReceivingWorklist.tsx`, `Stock.tsx`; `resources/js/Pages/PRJ/Projects.tsx`, `Journey.tsx`; `resources/js/components/region-state.tsx`; `lang/id.json`, `lang/en.json`, `lang/registry/added-keys.json`; the tests named above and the fixtures `tests/Support/Fixtures/CountFixture.php`, `InvoiceFixture.php`, `JourneySessionFixture.php`, `tests/Browser/invoice-fixtures.ts`; this file, TASK_PLAN, CURRENT_HANDOFF and GAP_REGISTER
- Commit SHA: this branch's commits on `task/TASK-096-defect-corrections` from `main`, the Owner's identity, no AI attribution
- Notes: each defect's DIR-111 class and its evidence (GAP-073) — A P1, B P2, C P1, D P2 —, all four corrected. **A**: the return list is literal paths with exact UUIDs, its query parsed and re-serialized on the server, 2,048 characters its bound — PF-14's encrypted cursors and PG-048's `id.id` cursor among the values it keeps —; PG-031–PG-034 and PG-048 open `ProofSheet` in place, offered only for the types `EvidenceTypes::choosable` gives the viewer in the record's company (`can.upload`, a global check, removed from those pages); MSG-35 over every field on PG-031–PG-034, PG-034's against what it held after its last recorded return, which keeps line, condition, rack, proof and date; "Batal" through the router; PG-017's return carries its chosen company; the in-place button says "Unggah Bukti", the label of the other in-place uploads, PG-032's two beside their own fields. **B**: PG-021's row counts its open lines ("N baris belum diterima", one new key); PG-011's procurement part lists each open goods line at its product's base unit in its one statement (`json_agg`), nothing for a closed or cancelled purchase; PG-018's exposures by the product's identity, the SKU beside a shared name. **C**: PG-014's `ActionBar md:hidden` on PG-010, PG-011 and PG-045, after the page's content as PG-014's — IN SCOPE's wording aligned to it during execution, the bar's spacer needing the page's end —, none while PG-010's empty list offers the action. **D**: MSG-25 with "Atur Ulang" through `RegionNoMatch`'s optional text, a live region from the page's load, focus back in the field, `maxLength` 100, "Muat Lagi" cancelled by a lookup. Preflight on the branch before the push: Pint, PHPStan, TypeScript, ESLint, the frontend tests (59) and the affected Feature files run locally; binding evidence is the head's CI (DIR-105). The follow-ups found while correcting are added to GAP-073, outside this Task
- Notes, verification: VERIFYING after run 45043063243 on `8175ec2`: the repository gates, TL-01, TL-02, TL-03 Architecture, ESLint rules and frontend, TL-04, TL-06–TL-08 with the traceability check, TL-13, TL-14, TL-15/TL-16, the Coolify stack and the Browser accessibility and responsive layers PASS, the Browser localization layer PASS, the Browser e2e layer FAIL — 461 PASS, 4 FAIL: TASK-096's PG-048 test on both engines (its alert dialog covering the evidence sheet; corrected to PG-024's pattern), its PG-029 test on WebKit (pool evidence registered by both engines on one database; the issuer made unique) and `parties.spec.ts` on WebKit, its 30-second budget exceeded on a page TASK-096 does not touch; TL-03 Unit FAIL — TASK-096's key placed out of the registry's order (KeyCatalogueTest), moved after the last registered key —; TL-05 Integration FAIL — `BackupToolingTest`'s teardown refused at `drop database … with (force)`, "permission denied to terminate process", the same failure as `main`'s run 44908554838 on `94c3fe2` before TASK-096, which changes nothing of the backup tooling, its test, the database roles or the configuration; no privilege granted, the test unchanged. ER-06's review ran after the pull request opened, a deviation from ER-06's "before the pull request" recorded in pull request #101 — recovered by fixing or reporting each of its findings in the pull request and by its review of the corrected head —; the DONE record and its counters follow only once the corrected head's CI passes and its ER-06 review and Codex status are recorded (the Owner's directive on PR #101; ER-07; DIR-127). ER-06's findings — no P1 — fixed in the corrective batch: P2-1, MSG-35 before a command that answers on another page (PG-031's papers, PG-032's Surat Jalan); P2-2, `purchases.spec.ts` tightened to the unit; P2-3, PG-018's test reproducing the merge with one base unit; P3-1, the guarded pages' own links through the router; P3-3, C at 320 px and PG-011 across the widths, D's account with no company in scope; P3-4, PG-017's return carrying its supplier and priced products; P3-5, the guard re-armed after a departure that did not happen; P3-6, PG-032's two buttons described by their fields; P3-7, PG-041 announcing once; P3-8, the address sent back bounded too; P3-9, PG-011's remainders through `Quantity`; P3-10, PG-048's sheet nested in its dialog, Escape closing it alone. P3-2, IN SCOPE C's wording aligned during execution, is recorded as a Level 1 correction in Change 30 (ER-08); the shared page header's breadcrumb and phone back arrow and PG-024's sheet beside its dialog are left to GAP-073's follow-ups
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- the operator's TASK-078 continues on its own path; TASK-086 once its other inputs allow; the evidence-backed assessment of the remaining PRD gaps for TASK-086's UAT (DIR-132)

**DO NOT DO:**

- admit an unlisted return address, weaken the evidence pool's or any page's capability checks, or turn a refusal into a success
- change a posting, balance, CALC rule, search semantics, route, capability or V1 scope; invent a unit conversion
- change a normative document, redesign tokens, navigation or the shell, add a package, or install Modern Web Guidance in the repository
- weaken, skip or delete a test, or change an existing security test, to obtain a green run (DOD-05; TS-06)
- touch staging, production, the VPS, Coolify, Cloudflare, a live database or BantuKasir
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))
