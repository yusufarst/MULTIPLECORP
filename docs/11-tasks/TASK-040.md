# TASK-040 — Stock views, stock history and movement reversal

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the stock views and the movement reversal: PG-018 *Stok* — pooled availability per product with its four quantities, the pending line and the restock advice —, PG-019 *Detail Stok Produk* — racks, lots, serials, reservations and recent movements of one product, the controls of the warehouse Tasks by capability —, PG-020 *Riwayat Stok* — the movement history itemized within scope —, all projected so that the pool is physical and cost stays with its family (PJ-01–PJ-04; PJ-22); and "Balikkan" on PG-020, the linked reversal of a wrong adjustment, condition change or count application (CM-28; NX-11; ROUTE-212).

**WHY THIS EXISTS:**

Staff must know quantities on hand, reserved, unusable and available, where they are and how they moved, and trace each lot's provenance — without seeing another company's costs or transactions in the shared pool (O-02; CAP-06; AC-06; AC-22; OS-02; OWN-04; OWN-05; BR-ACC-01; architecture challenge 8c). The views read the guard balances that reconcile with the ledger (BR-INV-08), and a wrong movement is corrected only by a linked reversal that keeps both visible and never restores more than was removed (BR-CR-02; C-55). Search and scanning (TASK-059) open these pages.

**DEPENDS ON:**

- TASK-039 — Receiving (Barang Masuk)

**BLOCKS:**

- TASK-059 — Search and scanning

**PARALLEL SAFE:** NO — ROUTE-212 writes the stock ledger, its reversal guard (LK-16) and the stock guards that every other inventory Task writes

**IN SCOPE:**

- PG-018 with NAV-018 and ROUTE-018 (`stock.view` · the pool; SCR-18; D-IA-17): per product, and per rack and condition, the quantity block *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia** with the line "Tersedia = Stok fisik − Direservasi − Tidak layak pakai", base units with the alternate unit as a hint (PT-17; DESIGN_SYSTEM §14.2); the pending unattributed quantity on its own line with MSG-41, outside every company's figures (UXS-39; DIR-027); the restock advice "Di bawah minimum …" over usable availability, linking to PG-017 with the product prefilled for `purchase.manage`, never buying by itself (BR-INV-10; J-MD-05; TASK-028; TASK-037); for `cost.view` holders the stock value of their own companies' lots only (PJ-22); the filter sheet, scan or search first
- PG-019 with NAV-019 and ROUTE-019 (`stock.view` · the pool; SCR-19): one product's racks, lots — the opaque code, rack, condition, remaining quantity and "Ambil Lebih Dulu" on the oldest as a picking aid only —, serials with their presence, lot, rack and condition, its reservations and recent movements (PJ-01–PJ-04; BR-INV-04); a lot of a company in scope names its source company, with its acquisition kind, date and acquired quantity, and its cost and flagged basis only with `cost.view` of that company (PJ-02); a lot of a company outside scope shows its code, product, remaining quantity, rack and condition with the relation marker and nothing more, and no link (UXS-20; PJ-04; architecture challenge 8c); the pending line for the product (MSG-41); "Pindah Lokasi" and "Ubah Kondisi" from a lot or serial, "Catat Penyesuaian Stok" and "Mulai Hitungan", each by its capability and once its Task exists (J-MD-05; J-INV-04; TASK-042; TASK-043; RC-06)
- PG-020 with NAV-020 and ROUTE-020 (`stock.view`; project `projects.view`; purchase `cost.view`; SCR-20): the movement history — direction, type, date, product, lot code, rack, condition, quantity, serial, reason and actor — itemized for movements of companies in scope and for pool movements without a company; a movement of a company outside scope never itemized, its quantity reaching the viewer only through the pooled figures; the supplier delivery reference with `projects.view` or `stock.receive`; no cost ever shown in the history (PJ-03; rows 50, 51); each original and its reversal linked by "Dikoreksi oleh" and "Mengoreksi" (ADMIN_FLOW §11); filters, sort and cursor, one branch per company in scope and one for pool movements (PF-13; PF-41); "Balikkan Penerimaan" (ROUTE-183, TASK-039) on a receipt
- ROUTE-212 with INT-212, "Balikkan" on PG-020 (NX-11; `stock.reverse`, ADM+): the reason (IP-09); CM-28's linked reversal equal and opposite to the wrong movement, at most the original net of earlier reversals, on the same lots, serials and condition (C-55): a MOVEMENT_REVERSAL for the types DATABASE §5.3 lists — a condition change, a surplus attribution, a pending case's recognition, finding or conversion among them — capped by the reversal guard, first-use at LK-16 (FU-03), or for lot-less entries by the case counters; for a movement that created consumptions — an adjustment decrease, a count loss — the capped restorations of its consumptions with the linked loss reversed exactly (SF-LOSS); SF-RSV-CUT re-evaluated in the same action (IP-20); a pending unexplained-loss case reversed exactly with its recognition — each effect posted by the service its own Task owns (TASK-038, TASK-042, TASK-043)
- INV's stock query classes for the pooled and lot views — physical-only classes that never select a cost table for the pool (PJ-01) — and the movement-history query class with its scope branches (AZ-04; CS-07), which TASK-059's search and scan results open

**OUT OF SCOPE:**

- the commands PG-019 hosts — the location move, condition change, adjustment and loss (TASK-042), counts (TASK-043) — and the movements they record; receiving and its reversal (TASK-039); dispatch, restorations and their reversal AX-36 (TASK-041; TASK-048); the opening facts' correction ROUTE-213 (TASK-057)
- the pending cases' recognition, resolution and PG-029 (TASK-043); QS-17 and QS-22 on the home pages (TASK-058); the stock reports and their exports (TASK-060); search and the scan lookup (TASK-059); rack and minimum-stock masters (TASK-028)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#53-inventory-and-purchasing-workflows) its quantities and pending-case display; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-28; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-RSV-CUT, SF-LOSS, SF-UNATTRIBUTED; [§11](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#11-cross-company-and-multi-admin-rules) the shared pool
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01, BR-INV-04, BR-INV-08, BR-INV-10, BR-INV-11; [Access boundary](../02-domain/BUSINESS_RULES.md#access-boundary-acc) BR-ACC-01, BR-ACC-02; [BR-CR-02](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy); [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), example 7
- [V1_SCOPE](../01-product/V1_SCOPE.md#physical-visibility-and-company-isolation) physical visibility; [reservation and usable stock](../01-product/V1_SCOPE.md#reservation-and-usable-stock); [CAP-06](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-16, AC-20, AC-22; [OS-02](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-02; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-021–REF-030, REF-058; [OWN-04, OWN-05](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §5.1](../04-architecture/DATABASE/s05-inventory.md#51-one-physical-pool-separate-economic-attribution); [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline) MOVEMENT_REVERSAL and the capped reversals; [§5.4](../04-architecture/DATABASE/s05-inventory.md#54-lots-and-lot-claims); [§5.8](../04-architecture/DATABASE/s05-inventory.md#58-unattributed-economic-loss-cases-dir-026-dir-027) the pending display; [§4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `stock_reversal_balances`; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-03, C-06, C-07, C-55, C-61
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-11; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol) NX-11's derivation; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-03; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.view`, `stock.reverse`; [§3](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model) AC-13's resolution; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05, CS-07, CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 22, 47–51, 57–61, 67, 73, 74; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01–PJ-04, PJ-07, PJ-22, PJ-23; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02
- [ROUTE_CONTRACTS §2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-018–ROUTE-020; [Warehouse](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-212; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-212
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-018–NAV-020; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-018–PG-020; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-17; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-18–SCR-20
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-04; [§10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-MD-05; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-28; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#visibility-and-authority) UXS-20; [UXS-39](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-41; [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-18](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-18--continuing-a-list)
- [DESIGN_SYSTEM §14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory) PT-17; [§9.2](../07-ux-design/DESIGN_SYSTEM.md#92-lists-tables-and-filters) PT-04–PT-08
- [PERFORMANCE §4](../06-api-performance/PERFORMANCE.md#4-pagination) PF-13, PF-41; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-05; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01–PFM-03, PFM-20; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-23, SM-25; [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-05, CJ-09
- Architecture challenge — the sequence 8c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (40): O-02; CAP-06; AC-06; AC-16; AC-20; AC-22; OS-02; REF-021–REF-030; REF-058; OWN-04–OWN-05; ROUTE-018–ROUTE-020; ROUTE-212; INT-212; NX-11; D-IA-17; PG-018–PG-020; SCR-18–SCR-20; J-INV-04; J-MD-05; UXS-20; UXS-39; NAV-018–NAV-020

**EXPECTED MODULES / FILE AREAS:**

- the INV module's ReverseMovement action, `InventoryPosting`'s MOVEMENT_REVERSAL path, the physical-only stock query classes and the movement-history query class, `ShowStock`, `ShowProductStock` and `ShowStockHistory`; `resources/js/Pages/INV` for PG-018–PG-020 with the quantity block in `resources/js/features/inv`; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-01 and CALC-01 with OWN-04: AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0, the three subtrahends disjoint, shown per product and per rack and condition (annex 7: 10/3/2 → 5; AC-22)
- BR-ACC-01 and BR-ACC-02: with `stock.view` an Admin sees the pooled physical availability needed for warehouse work and nothing of another company's costs, purchases, suppliers or transactions; lot costs are S3 company data although the pool is shared (OWN-05; REF-021; REF-022; architecture challenge 8c)
- BR-INV-04: the oldest-first order is a picking aid only, never a valuation rule; BR-INV-10: restock advice reads reconciled usable availability and never creates a purchase (REF-028; AC-22); BR-INV-11: drop-ship and services create no warehouse event, so they never appear in the stock views (AC-20; REF-030)
- BR-INV-08 and FS-11: every figure the views show is recomputable from the ledger, the guard rows they read reconciling with it (OS-02; REF-026)
- CM-28 with BR-CR-02 and C-55: a wrong adjustment, condition change or count application is corrected by a linked reversal equal and opposite to it, at most the original net of earlier reversals, on the same lots, serials and condition; a pending case is reversed exactly with it; SF-RSV-CUT is re-evaluated; a linked loss is reversed exactly (SF-LOSS); both records stay visible (AC-06; REF-025; REF-027)
- REF-021–REF-030 as the views show them: one shared pool (REF-021) with each lot's source company within scope (REF-022), stock located by rack (REF-023), damaged and quarantined units apart from usable ones (REF-024), dispatches, returns, counts and reversals in the history with their reasons (REF-025; REF-027; REF-029), the ledger and its balances (REF-026), the minimum and its advice (REF-028), nothing of drop-ship (REF-030)
- DIR-027's display (SF-UNATTRIBUTED; UXS-39): a pending unattributed quantity is shown on its own line, outside every company's figures, on the pooled views; a company's stock view shows only its own exposure; it blocks no work

**ARCHITECTURE RULES:**

- the pool is served by physical-only query classes that never select a cost table and never return a lot's acquisition kind, source references, acquired quantity, date, supplier, price or cost for a source company outside scope (PJ-01; DATABASE §5.1); cost columns come only from CST's query classes, by family (TASK-036)
- page reads take no row lock and never wait on one (PF-12); a list is keyset-paginated with the scope inside the query, one branch per company in scope and one for pool movements (PF-13; PF-41; CS-07)
- a reversal is a new movement naming what it reverses, never an edit of the ledger (DATABASE §5.3); a movement that created consumptions is undone only by restorations of those consumptions, capped by the consumption guard, every other correctable movement by a MOVEMENT_REVERSAL capped by the reversal guard

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-018 and ROUTE-019 `stock.view` · the pool (S1), lot cost `cost.view`; ROUTE-020 `stock.view`, its project `projects.view` and its purchase `cost.view`; ROUTE-212 `stock.reverse` (ADM_PLUS, W4-18), a cut also `stock.reserve` (IP-20)
- an account without `stock.view` is denied every pooled view, facet and prop, whatever its company grants (PERMISSIONS_MATRIX §3's AC-13 resolution); a record outside scope is indistinguishable from a nonexistent one (AZ-11; SM-23); every control absent without its capability (IG-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- pool records — racks, physical balances, lots' physical attributes, serials, counts and pending quantities — belong to no company and are read with `stock.view` (CS-05; rows 47–49, 57–60); reservations show their quantity with the relation marker and their project with `projects.view` (row 61); a pending case shows its physical result, never its candidates to a non-Owner (PJ-07; row 67)
- a lot of a company in scope names that company; for a lot of a company outside scope the path ends at the physical fields with "perusahaan lain" (PJ-02; PJ-04); lot cost and flagged basis CV(source c) (rows 73, 74); lot codes opaque (PJ-23)
- movement history per PJ-03 (rows 50, 51): itemized for companies in scope and pool movements, never for another company; costs never shown; a company's stock value is `cost.view` of that company and never combined with another company's lots (PJ-22); no total across companies for an Admin (CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-212: NX-11 — LK-00, LK-01, LK-03, LK-10, LK-11, LK-12, LK-13–LK-19, LK-21–LK-24 at their positions —, capped by LK-16's reversal guard, a first-use row by insert-or-lock carrying the reversed movement's original quantity (FU-03), or by the case counters for lot-less entries; DATA-DEPENDENT when the reversed movement belongs to a pending case, a count or a finding, re-derived after LK-13 and LK-14 (CONCURRENCY_IDEMPOTENCY §5, §7.3); refusals C-55, C-03, C-06, AZ-08
- a reversal racing a movement of the same scope serializes at the product and scope guards; two reversals of one movement never exceed its original (C-55); every command carries `command_id` (CI-01), a replay re-authorized and returning the recorded outcome (CI-05; REF-058; AC-16)

**AUDIT:**

- each reversal is one business audit event in its transaction with the individual actor, the reason and the reversed movement (BR-XC-01; BR-CR-01; LG-01); ADM_PLUS, reaching the Owner review queue (AZ-10; QS-14)
- the MOVEMENT_REVERSAL row names the reversed movement and its type (DATABASE §4.7); the history shows both, "Dikoreksi oleh" on the original and "Mengoreksi" on the reversal (ADMIN_FLOW §11); audit keys projected by family (PJ-11; LG-05)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5, its query classes read by Reporting's search, scanning and reports (ARCHITECTURE §3, §14)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no WMS views, valuation or forecasting (V1_SCOPE out of scope; D-04)

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

- Context7: OPTIONAL — PostgreSQL row-value keyset predicates and their plans, Inertia deferred props, at the installed versions
- Graphify: OPTIONAL — once the warehouse commands exist
- Browser E2E: REQUIRED — PG-018–PG-020 with a Company-A-only Admin and the Owner (UXS-20; CJ-09's pooled view), the pending line (UXS-39) and "Balikkan" on PG-020
- Other: the synthetic group FX-02 with a shared pool of two companies; fixture movements of each reversible type, pending cases and counts posted through `InventoryPosting` until TASK-042 and TASK-043 add the commands that record them; the scale fixtures FX-06 for QB-05
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV's query classes and posting service
- Pre-change questions: which query classes could select a cost table for the pool, and which movement types each reversal path covers
- Expected affected areas: INV's query classes and pages; the MOVEMENT_REVERSAL path of `InventoryPosting`

**CONTEXT7:**

- Topics to verify: row-value comparisons as index conditions for keyset pagination (PF-18); Inertia's deferred and partial props for the lot and serial group
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the quantity block's labels and formula line (DESIGN_SYSTEM §14.2), MSG-41's two texts, "perusahaan lain", the restock advice's wording, MSG-40 for a reversal's reason
- Language switch impact: keeps the filters and the open section
- Mobile-first: YES — PG-018 and PG-019 `[both]`, scan or search first; PG-020 `[both]`, dense (NAV-018–NAV-020)
- Responsive: YES
- Adaptive: YES — tables on the desktop (PT-05), dense rows on the phone (PT-06), the quantity block stacked and lots as rows on the phone (PT-17)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the quantity block (PT-17), the relation marker and company label (PT-04), the data table, dense rows, filters and "Muat Lagi" (PT-05–PT-08), the detail layout (PT-12), the timeline links (PT-18), the confirmation with its reason (PT-26), status badges (PT-13)
- Design reference: PG-018–PG-020 as INFORMATION_ARCHITECTURE §8.3 states them, the inventory presentation of DESIGN_SYSTEM §14.2; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: none — PG-018 and PG-020 are lists, PG-019 a record whose commands are their Tasks'
- Secondary actions: on PG-019 "Pindah Lokasi", "Ubah Kondisi", "Catat Penyesuaian Stok" and "Mulai Hitungan" by capability; the restock advice's link to PG-017
- Destructive actions: "Balikkan" on PG-020, with its reason and a destructive confirming button (IP-09; PT-11)
- Redundancy constraints: one reversal per wrong movement up to its original; no edit of a movement or balance (BR-INV-07; FS-11); no cost in the history
- Loading state: PT-19, PG-019's lot and serial group deferred (QB-05)
- Empty state: a product without stock — one sentence (PT-19); a history without movements for the filters — the filters kept
- Error state: C-55's and C-06's refusals in words; PG-005 for a record outside scope (IP-21); a group that fails shows its own error and retry

**ROUTE IMPACT:**

- ROUTE-018, ROUTE-019, ROUTE-020 (the views); ROUTE-212 (reverse a movement); PG-019's entries to ROUTE-190, ROUTE-191, ROUTE-194 and ROUTE-195 and PG-020's to ROUTE-183, each built by its Task; the filters and "Muat Lagi" client-only through the views (ROUTE_CONTRACTS §4)

**INTERACTION CONTRACTS:**

- INT-212; INT-007 and INT-008 on PG-018 and PG-020; INT-190, INT-191, INT-194 and INT-195 from PG-019 (their Tasks)

**NAVIGATION CONTRACT:**

- Parent: — (*Gudang* › *Stok*) for PG-018; PG-018 for PG-019 and PG-020 (NAV-018–NAV-020)
- Entry: *Gudang* › *Stok* (NAV-018); *Stok*, search and scan (NAV-019); *Gudang* › *Lainnya* (NAV-020)
- Back action: PG-019 "Kembali" → PG-018 at its address, or the search it came from; PG-020 "Kembali" → PG-018, *Gudang*'s first page (NAV-019; NAV-020)
- Cancel: the reason step's "Kembali" → the history, the input kept (IP-09; NV-02)
- Success destination: the reversal in place on PG-020, linked to its original (ROUTE-212; NV-03)
- Error behavior: NV-04; a record outside scope → PG-005 (IP-21)
- State preservation: PG-018's and PG-020's filters, sort, tab and cursor in the address (IP-18); PG-019's open section or tab (NV-05)
- Mobile behavior: `[both]` — PG-018 scan or search first, PG-019 readable, PG-020 dense (NAV-018–NAV-020; NV-06)

**ACCEPTANCE CRITERIA:**

1. With ON HAND 10, RESERVED 3 and UNUSABLE 2 PG-018 shows *Stok fisik* 10, *Direservasi* 3, *Tidak layak pakai* 2 and **Tersedia** 5 with the formula line, per product and per rack and condition (annex 7; AC-22; OWN-04; D-IA-17).
2. A Company-A-only Admin with `stock.view` sees a pooled lot of Company B with its code, product, remaining quantity, rack and condition and "perusahaan lain" — no name, code, supplier, date, kind, cost or link —; an account without `stock.view` is denied every pooled view, facet and prop (UXS-20; PJ-01; PJ-04; architecture challenge 8c).
3. A lot of a company in scope shows its source company, acquisition kind, date and acquired quantity with `stock.view`, and its cost and flagged basis only with `cost.view` of that company; no prop, partial reload or deferred group of the pooled views carries a cost (PJ-02; DP-15; SM-25).
4. PG-018's stock value for a `cost.view` holder covers only the lots of its own companies in scope, labelled per company, never another company's and never a total across companies for an Admin (PJ-22; CS-08).
5. A pending unattributed quantity shows on PG-018 and PG-019 as its own line with MSG-41, outside every company's figures, and a company's stock view shows only its own exposure, never the case's pending quantity or another candidate company (UXS-39; PJ-07; PJ-22).
6. A product below its minimum shows "Di bawah minimum …" over its usable availability, with a link to PG-017 with the product prefilled for `purchase.manage`; nothing is bought by itself (BR-INV-10; J-MD-05; AC-22).
7. PG-020 itemizes a movement of a company in scope and a pool movement — direction, type, date, product, lot code, rack, condition, quantity, serial, reason and actor —, its project only with `projects.view` and its purchase only with `cost.view`; a movement of a company outside scope is never itemized, and no cost appears (PJ-03; AC-06).
8. The oldest lot of a product carries "Ambil Lebih Dulu" and nothing else depends on that order (BR-INV-04).
9. Drop-ship and service quantities never appear on PG-018–PG-020 (BR-INV-11; AC-20; REF-030).
10. "Balikkan" on a wrong condition change records a MOVEMENT_REVERSAL equal and opposite on the same lots, serials and condition, and a second reversal beyond the original net of earlier reversals is REJECTED (CM-28; C-55; FU-03).
11. "Balikkan" on a wrong adjustment decrease restores its consumptions within their caps and reverses the linked loss exactly; the movement itself is never MOVEMENT_REVERSAL'd (DATABASE §5.3; SF-LOSS; C-27).
12. A reversal that would leave usable supply below the reservations cannot commit without the actor's cut (IP-20; C-06).
13. Without `stock.reverse` "Balikkan" is absent and ROUTE-212 refused; a reversal's audit event carries ADM_PLUS and reaches the Owner review queue (IG-01; AZ-10).
14. ROUTE-212 replayed with one `command_id` returns the recorded outcome, and with a changed payload is CONFLICT (CI-05; REF-058; AC-16).
15. The original and its reversal both stay in PG-020, linked by "Dikoreksi oleh" and "Mengoreksi" (BR-CR-02; ADMIN_FLOW §11).
16. Every figure of PG-018 and PG-019 equals its guard rows, and the reconciliation suite finds the guards equal to the ledger after every scenario (BR-INV-08; OS-02; C-61).
17. PG-019's "Pindah Lokasi", "Ubah Kondisi", "Catat Penyesuaian Stok" and "Mulai Hitungan" appear only with their capabilities and once their routes exist (RC-06; IG-01).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: AVAILABLE and the alternate-unit hint from base quantities (annex 7; TL-03)
- Feature: ROUTE-018–ROUTE-020 props per viewer, ROUTE-212 with each refusal — C-55, C-03, C-06, AZ-08 (TL-04)
- Integration: ROUTE-212 on fixture movements of each reversible type — a condition change, an adjustment decrease with its loss, a count application, a surplus attribution and a pending case's recognition —, each re-run end to end by the Task that records it (TASK-042, TASK-043); two reversals of one movement racing (C-55); the reconciliation suite (TL-05)
- Authorization: `stock.view` pool-wide and its absence, `cost.view` per source company, `projects.view` for a movement's project, `stock.reverse` as ADM_PLUS (TL-06)
- Route integrity: ROUTE-018–ROUTE-020 and ROUTE-212 audited (TL-07)
- Interaction: INT-212 with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: UXS-20 and CJ-09's pooled view as a Company-A-only Admin, UXS-39's pending line, CJ-05's reversal on PG-020; the phone's scan-or-search entry (TL-09)
- Localization/language switch: both languages on PG-018–PG-020 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-018–PG-020
- Responsive: TL-11
- Accessibility: TL-12, the quantity block's figures read with their labels
- Production build: TL-13
- Security: SM-01 for a foreign lot, movement or product route; SM-23 for identical not-found and out-of-scope answers; SM-25 for no cost in the pooled props and PJ-22's stock value; SM-04 for W4-18 (TL-14)
- Performance: QB-05 — at most four queries, the lot and serial group at most three, 300 ms — for PG-018, PG-019 and PG-020; PFM-02 with one row and a full page; PFM-03 for the movement history of one product within a company; PFM-20 for ROUTE-212 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the physical-only query classes checked never to join a cost table (SM-03); GAP-009's history after concurrent movements

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

- TASK-059 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- select a cost table in a pooled query class, or show another company's name, supplier, date, kind or cost for its lot or movement (PJ-01; PJ-03; PJ-04)
- edit a movement or a balance, or reverse a consumption-creating movement by a MOVEMENT_REVERSAL (BR-INV-07; DATABASE §5.3)
- reverse more than the original net of earlier reversals, or commit a reversal below the reservations without a cut (C-55; C-06)
- total figures across companies for an Admin, or count a pending case inside a company's stock (CS-08; PJ-22)
